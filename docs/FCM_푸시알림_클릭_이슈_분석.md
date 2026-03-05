# FCM 푸시 알림 클릭 시 잘못된 페이지 이동 이슈 분석

> 실제 발생 사례를 기반으로 작성한 문서. 웹뷰 앱 빌드 시 FCM 연동 점검 항목으로 활용.
>
> **발생 프로젝트**: optiflow.kr  
> **발생일**: 2026-03-06  
> **해결**: 웹 서버(Laravel + Nginx) 수정으로 완전 해결

---

## 1. 현상

| 조건 | 알림 클릭 결과 |
|---|---|
| 일반 계정으로 앱 로그인 후 채팅 알림 클릭 | 해당 채팅방으로 정상 이동 ✅ |
| 관리자 계정으로 앱 로그인 후 채팅 알림 클릭 | 관리자 페이지(`/admin`)로 이동 ❌ |

---

## 2. 원인 (2가지 복합 작용)

### 2.1 근본 원인 — www 도메인 불일치

웹 사용자가 `www.optiflow.kr`로 접속하여 채팅 메시지를 보내면, Laravel의 `url()` 함수가 **현재 요청의 호스트를 그대로 따라가** FCM `action_url`이 `www` 도메인으로 생성됩니다.

```
❌ https://www.optiflow.kr/chat?conversation=12   ← www 포함
✅ https://optiflow.kr/chat?conversation=12       ← 올바른 값
```

앱 WebView는 `optiflow.kr` 세션으로 동작하기 때문에:

```
www.optiflow.kr ≠ optiflow.kr (브라우저/WebView는 별개 도메인으로 인식)
→ 쿠키/세션 공유 안 됨
→ 앱이 해당 URL 로드 시 로그인 안 된 상태로 인식
→ 로그인 페이지로 리다이렉트
```

### 2.2 2차 원인 — 관리자 전용 기본 리다이렉트

세션이 없어 로그인 페이지로 리다이렉트되면, 앱의 app-token 자동 로그인이 실행됩니다. 이때 원래 가려던 URL(`url.intended`)이 도메인 불일치로 유실되어 기본 리다이렉트가 적용됩니다.

```php
// 서버 로그인 컨트롤러에 존재했던 코드
$default = $user->isAdmin() ? route('dashboard') : '/';
//           관리자 → /admin        일반 사용자 → /
```

**결과:**

```
일반 사용자 → 기본값 / → 홈으로 이동 (채팅이 있어 눈에 안 띔)
관리자       → 기본값 /admin → 관리자 대시보드로 이동 (명확히 이상)
```

### 2.3 전체 흐름 요약

```
[문제 발생 경로]

웹(www.optiflow.kr)에서 채팅 발송
→ Laravel url()이 www.optiflow.kr/chat?conversation=12 생성
→ FCM action_url = https://www.optiflow.kr/chat?conversation=12
→ 앱 WebView가 www.optiflow.kr 로드
→ 세션 없음 → 로그인 리다이렉트
→ app-token 자동 로그인 실행
→ url.intended 유실 → 기본 리다이렉트 적용
→ 관리자: /admin 이동 ❌ / 일반 사용자: / 이동 (눈에 안 띔)
```

---

## 3. 해결 방법 (3가지 수정)

### 수정 1 — FCM 서비스: `url()` → `config('app.url')` 변경

```php
// 수정 전: 현재 요청 호스트를 따라감 (www가 될 수 있음)
$dataPayload['action_url'] = url($dataPayload['action_url']);

// 수정 후: 항상 APP_URL(https://optiflow.kr) 사용
$dataPayload['action_url'] = rtrim(config('app.url'), '/') . $dataPayload['action_url'];
```

> `.env`의 `APP_URL`이 `https://optiflow.kr` (www 없이)로 설정되어 있어야 합니다.

### 수정 2 — Nginx: www → non-www 301 리다이렉트

```nginx
if ($host = www.optiflow.kr) {
    return 301 https://optiflow.kr$request_uri;
}
```

www로 접속하면 자동으로 non-www로 이동시켜 도메인 불일치 자체를 원천 차단합니다.

### 수정 3 — 로그인 기본 리다이렉트 통일

```php
// 수정 전
$default = $user->isAdmin() ? route('dashboard') : '/';

// 수정 후
$default = '/';
```

관리자도 일반 사용자와 동일하게 `/`로 이동. 이슈가 재발하더라도 관리자 페이지 이동을 방지하는 안전장치입니다.

---

## 4. 수정 후 흐름 비교

| 단계 | 수정 전 | 수정 후 |
|---|---|---|
| FCM action_url | `https://www.optiflow.kr/chat?...` | `https://optiflow.kr/chat?...` |
| 앱이 URL 로드 | 세션 없음 → 로그인 리다이렉트 | 세션 유지 → 채팅방 바로 표시 ✅ |
| 로그인 후 기본 이동 | 관리자 → `/admin` | 관리자 → `/` (안전장치) |

---

## 5. 앱 측 역할

앱은 FCM 데이터의 `action_url` 값을 WebView에 **그대로 로드**합니다. 앱 코드에서 역할(관리자/일반)에 따른 별도 처리가 없으며, 이는 **의도된 설계**입니다.

```java
// AppFirebaseMessagingService.java
String url = data.get(CLICK_URL_KEY);  // action_url 값을 그대로 사용
intent.putExtra("fcm_click_url", url);
```

따라서 `action_url`이 올바른 값으로 서버에서 설정된다면 앱은 정상 동작합니다.

---

## 6. 다른 웹 빌드 시 필수 점검 항목

FCM 연동 시 아래 항목을 반드시 확인하세요:

### 6.1 서버 APP_URL 확인

```bash
# .env
APP_URL=https://optiflow.kr   # www 없이, https, 끝에 / 없이
```

### 6.2 FCM action_url 생성 방식 확인

```php
// ❌ 위험: 요청 호스트를 따라감
$url = url('/chat?conversation=' . $id);

// ✅ 안전: APP_URL 고정 사용
$url = rtrim(config('app.url'), '/') . '/chat?conversation=' . $id;
```

### 6.3 Nginx www 리다이렉트 설정 여부 확인

www 도메인과 non-www 도메인 중 하나로 통일되어 있는지 확인합니다. 앱 WebView는 정확히 `APP_URL`과 동일한 도메인으로 세션이 유지됩니다.

### 6.4 로그인 후 기본 리다이렉트 확인

역할(role)에 따라 다른 URL로 리다이렉트하는 코드가 있다면, 앱에서 FCM 클릭 시 의도하지 않은 페이지로 이동할 수 있습니다.

---

## 7. 디버깅 방법

앱에서 FCM 클릭 시 어떤 URL이 로드되는지 확인하려면:

```bash
# ADB 실시간 로그에서 WebView URL 로드 확인
adb logcat | grep -E "optiflow|fcm_click|loadUrl|action_url"
```

서버에서 실제로 어떤 `action_url`이 발송되는지는 **서버 로그 또는 FCM 테스트 도구**로 확인해야 합니다.
앱 로그에는 FCM 데이터 내용이 출력되지 않습니다.
