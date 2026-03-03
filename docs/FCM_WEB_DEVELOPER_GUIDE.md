# FCM 푸시 알림 — 웹 개발자 연동 가이드

> **대상**: 우리 앱 생성기로 만든 웹뷰 앱에 푸시 알림을 적용하려는 웹사이트 개발자  
> **목적**: 푸시 알림이 정상 동작하도록 웹·서버에서 해야 할 작업 안내

---

## 1. 개요

### 1.1 역할 구분

| 구분 | 담당 | 하는 일 |
|------|------|---------|
| **앱 생성기** | 우리 | FCM이 포함된 APK 빌드, 앱에서 토큰을 웹에 전달하는 브릿지 제공 |
| **웹 개발자** | 귀사 | 토큰 수신 → 서버 등록, 푸시 발송 로직 구현 |

앱 생성기는 **앱 빌드만** 제공합니다. 푸시 발송은 **귀사 서버**에서 수행해야 합니다.

### 1.2 앱이 제공하는 기능 (자동 적용)

우리 앱 생성기로 빌드한 APK는 아래를 **자동으로** 처리합니다.

| 기능 | 설명 |
|------|------|
| **토큰 → 웹 전달** | FCM 토큰 발급 후 `window.onFcmTokenReady(token)` 콜백 호출 |
| **알림 아이콘** | 1단계에서 업로드한 앱 아이콘에서 흰색 실루엣 생성 → 트레이에 앱 로고 표시 |
| **헤드업 알림** | 푸시 수신 시 소리·진동과 함께 화면 상단에 알림 카드 표시 (실시간) |
| **알림 탭 시 URL** | 2단계에서 설정한 키(`action_url` 등)로 `data`에 URL 포함 시, 탭하면 WebView에서 해당 URL 로드 (포그라운드·백그라운드 모두 지원) |
| **Cold start 세션 복원** | 앱 전용 인증 토큰(app-token/app-login) 방식으로 앱 재시작 시에도 로그인 상태 유지 (섹션 6 참조) |
| **패키지명 치환** | google-services.json의 패키지명을 1단계 패키지 ID로 자동 치환 (Firebase 등록 패키지와 달라도 빌드 성공) |

### 1.3 전체 흐름

```
[1] Firebase 프로젝트 생성 (귀사)
    ↓
[2] 앱 생성 시 google-services.json 업로드 (귀사)
    ↓
[3] 앱 설치·실행 → FCM 토큰 발급 (자동)
    ↓
[4] 앱이 웹에 토큰 전달 (자동)
    ↓
[5] 웹이 서버에 토큰 등록 (귀사 구현)
    ↓
[6] 서버가 FCM API로 푸시 발송 (귀사 구현)
```

---

## 2. 앱 생성 전 준비 (Firebase 설정)

### 2.1 Firebase 프로젝트 생성

1. [Firebase Console](https://console.firebase.google.com) 접속
2. **프로젝트 추가** → 이름 입력 → 생성

### 2.2 Android 앱 등록

1. 프로젝트 대시보드에서 **+ 앱 추가** 버튼 클릭
2. **Android** 플랫폼 선택
3. **Android 패키지 이름** 입력  
   - **중요**: 앱 생성기 2단계에서 사용하는 **패키지 ID**와 **동일**해야 함  
   - 예: `com.example.myapp`
4. (선택) 앱 닉네임, SHA-1
5. **앱 등록** 클릭

### 2.3 google-services.json 다운로드

1. 등록 완료 후 **google-services.json 다운로드** 버튼 클릭
2. 파일 저장

> **참고**: Firebase 콘솔 가이드에 "3단계: Firebase SDK 추가, Gradle 동기화"가 나올 수 있습니다.  
> **우리 앱 생성기를 사용하는 경우 이 단계는 건너뛰세요.**  
> Gradle 플러그인, Firebase SDK, google-services.json 적용은 **앱 생성기가 빌드 시 자동으로 처리**합니다.  
> (3단계는 Android Studio에서 직접 앱을 빌드할 때만 필요합니다.)

### 2.4 앱 생성 시 업로드

앱 생성기 **2단계**에서:

1. **푸시 알림 사용** 체크
2. **google-services.json** 파일 업로드
3. **패키지 ID**가 Firebase에 등록한 값과 일치하는지 확인  
   - 참고: 빌드 시 패키지명을 자동 치환하므로, Firebase에 다른 패키지로 등록된 json을 업로드해도 빌드는 성공합니다.  
     단, **FCM 토큰 발급**을 위해 Firebase에는 1단계 패키지 ID와 동일하게 등록해야 합니다.

---

## 3. 웹에서 해야 할 일 (상세)

> **핵심**: 앱이 WebView로 귀사 웹페이지를 띄웁니다. 그 페이지의 JavaScript에서 **함수 하나만 정의**하면, 앱이 FCM 토큰을 받았을 때 자동으로 그 함수를 호출해 줍니다. 그때 서버에 토큰을 보내면 됩니다.

### 3.1 개념 정리

| 용어 | 설명 |
|------|------|
| **FCM 토큰** | Firebase가 각 기기·앱에 부여하는 고유 ID. 푸시를 보낼 때 "어느 기기에 보낼지" 지정하는 값 |
| **onFcmTokenReady** | 앱이 토큰을 받으면 호출하는 **콜백 함수 이름**. 귀사가 `window.onFcmTokenReady = function(token){ ... }` 형태로 정의 |
| **앱의 역할** | 토큰 발급 → 웹에 전달 (이미 구현됨) |
| **웹의 역할** | 토큰 수신 → 서버에 전송 (귀사가 구현) |

### 3.2 어디에 코드를 넣나요?

**앱이 로드하는 웹페이지**에 넣습니다.  
예: `https://yoursite.com/` 또는 `https://yoursite.com/app` 등, 앱이 처음 열 때 보여주는 페이지.

- **SPA(React, Vue 등)**: 앱 진입점(예: `App.js`, `main.tsx`) 또는 공통 레이아웃
- **일반 HTML**: `<head>` 또는 `<body>` 하단의 `<script>` 태그
- **중요**: `onFcmTokenReady`는 **페이지가 로드된 후, 가능한 빨리** 정의되어야 합니다.  
  앱이 토큰을 먼저 준비해 두었다가, 페이지 로드 시점에 바로 콜백을 호출할 수 있기 때문입니다.

### 3.3 타이밍: 언제 콜백이 호출되나요?

```
[앱 실행] → [Firebase에서 토큰 발급] → [WebView에 웹페이지 로드]
                                              ↓
                                    [페이지 JS 실행]
                                              ↓
                                    [onFcmTokenReady 정의]
                                              ↓
                                    [앱이 token과 함께 콜백 호출]
```

- **케이스 A**: 페이지 로드 시점에 토큰이 이미 준비됨 → 페이지 로드 직후 곧바로 `onFcmTokenReady(token)` 호출
- **케이스 B**: 토큰이 아직 준비 중 → 준비되는 즉시 `onFcmTokenReady(token)` 호출
- **주의**: `onFcmTokenReady`를 **나중에** 정의하면, 그 시점 이전에 준비된 토큰은 놓칠 수 있습니다.  
  → **가능한 한 페이지 최상단/초기화 단계에서 정의**하세요.

### 3.4 전체 예시 코드 (복사해서 사용 가능)

```html
<!-- 귀사 웹페이지 (예: index.html 또는 React 앱의 index) -->
<script>
(function() {
  // 1. 콜백 정의 — 앱이 토큰을 주면 이 함수가 호출됨
  window.onFcmTokenReady = function(token) {
    console.log('[FCM] 토큰 수신:', token ? '성공' : '없음');
    if (!token) return;
    sendTokenToServer(token);
  };

  // 2. 서버에 토큰 전송
  function sendTokenToServer(token) {
    // 로그인 사용자만 등록하려면: if (!isLoggedIn()) return;
    fetch('/api/register-device', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fcmToken: token,
        userId: getCurrentUserId(),   // 예: sessionStorage.getItem('userId') 또는 null
        platform: 'android_app',
      }),
    }).then(function(res) {
      if (res.ok) console.log('[FCM] 토큰 등록 완료');
    }).catch(function(err) {
      console.error('[FCM] 토큰 등록 실패:', err);
    });
  }

  // 사용자 ID 예시 (실제 구현에 맞게 수정)
  function getCurrentUserId() {
    return window.__USER_ID__ || sessionStorage.getItem('userId') || null;
  }
})();
</script>
```

### 3.5 React / Vue / SPA 예시

**SPA 권장**: `index.html`에 인라인 스크립트를 넣으면 번들 로드 전에 콜백이 정의되어, 토큰을 놓치지 않습니다.

```html
<!-- public/index.html (Create React App 등) -->
<!DOCTYPE html>
<html>
<head>...</head>
<body>
  <div id="root"></div>
  <!-- React/Vue 번들보다 먼저 실행되도록 상단에 배치 -->
  <script>
  window.onFcmTokenReady = function(token) {
    if (!token) return;
    fetch('/api/register-device', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fcmToken: token, userId: null, platform: 'android_app' }),
    });
  };
  </script>
  <script src="/static/js/main.xxx.js"></script>
</body>
</html>
```

**React (예: App.js 또는 _app.js)** — index.html에 넣기 어려울 때

```javascript
import { useEffect } from 'react';

useEffect(() => {
  window.onFcmTokenReady = function(token) {
    if (!token) return;
    fetch('/api/register-device', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fcmToken: token,
        userId: user?.id ?? null,
        platform: 'android_app',
      }),
    });
  };
  return () => { delete window.onFcmTokenReady; };
}, [user?.id]);
```

**Vue (예: App.vue 또는 main.js)**

```javascript
mounted() {
  window.onFcmTokenReady = (token) => {
    if (!token) return;
    this.$http.post('/api/register-device', {
      fcmToken: token,
      userId: this.user?.id ?? null,
      platform: 'android_app',
    });
  };
},
beforeUnmount() {
  delete window.onFcmTokenReady;
}
```

### 3.6 자주 묻는 질문

**Q. 일반 브라우저(Chrome, Safari)에서도 이 코드가 실행되나요?**  
A. 네. 하지만 `onFcmTokenReady`는 **앱(WebView)에서만** 호출됩니다. 브라우저에서는 호출되지 않으므로, `fetch`도 실행되지 않습니다. 그대로 두셔도 됩니다.

**Q. 로그인하지 않은 사용자는 어떻게 하나요?**  
A. `userId`를 `null`로 보내고, 서버에서 익명/세션 기반으로 저장할 수 있습니다. 로그인 후 같은 토큰에 `userId`를 연결(업데이트)하는 방식으로 처리하면 됩니다.

**Q. 토큰이 여러 번 바뀌나요?**  
A. 앱 재설치, 데이터 삭제 등으로 바뀔 수 있습니다. 서버에서는 `fcm_token` 기준으로 upsert(같은 토큰이면 업데이트)하는 것을 권장합니다.

**Q. 콜백이 아예 호출되지 않아요.**  
A. (1) 앱에서 푸시 사용 + google-services.json 업로드했는지 확인 (2) `onFcmTokenReady`를 페이지 로드 직후에 정의했는지 확인 (3) 앱이 WebView로 **귀사 도메인**을 로드하는지 확인 (다른 URL이면 해당 페이지에 코드가 없음) (4) **SPA(React/Vue)**: 번들 로딩이 늦으면 토큰 전달 시점에 콜백이 아직 없을 수 있음 → `index.html`에 인라인 `<script>`로 `onFcmTokenReady`를 **가장 먼저** 정의하는 것을 권장

### 3.7 서버 API 요청 형식 (참고)

웹에서 보내는 요청 예시:

```http
POST /api/register-device
Content-Type: application/json

{
  "fcmToken": "dAbc123...긴_문자열",
  "userId": 12345,
  "platform": "android_app"
}
```

서버는 이 값을 DB에 저장해 두었다가, 푸시 발송 시 `fcmToken`을 사용합니다.

---

## 4. 서버에서 해야 할 일

### 4.1 토큰 저장

FCM 토큰을 DB에 저장합니다. 사용자별로 여러 기기가 있을 수 있으므로, 사용자 ID와 토큰을 매핑해 저장하세요.

**예시 테이블 (SQL)**

```sql
CREATE TABLE device_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  fcm_token VARCHAR(255) NOT NULL,
  platform VARCHAR(20) DEFAULT 'android_app',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (fcm_token)
);
```

### 4.2 FCM Admin SDK 설치

**Node.js**

```bash
npm install firebase-admin
```

**PHP**

```bash
composer require kreait/firebase-php
```

**Python**

```bash
pip install firebase-admin
```

### 4.3 서비스 계정 키 발급

1. Firebase Console → **프로젝트 설정** (톱니바퀴)
2. **서비스 계정** 탭
3. **새 비공개 키 생성** → JSON 파일 다운로드
4. 이 파일을 서버에 안전하게 보관 (절대 공개 저장소에 올리지 마세요)

### 4.4 푸시 탭 시 URL 로드 (선택)

앱 생성기 2단계에서 **푸시 탭 시 URL 키**를 설정할 수 있습니다 (기본: `action_url`).

푸시 발송 시 `data` 페이로드에 해당 키로 URL을 포함하면, 사용자가 알림을 탭했을 때 WebView가 해당 URL을 로드합니다.

**권장: data-only 메시지 (앱 내 WebView 보장)**

`notification`+`data` 조합은 앱이 백그라운드일 때 Android가 알림을 처리하며, 일부 환경에서 클릭 시 외부 브라우저로 열릴 수 있습니다. **앱 내 WebView로 확실히 열리게 하려면 data-only 메시지를 사용하세요.**

```json
{
  "data": {
    "title": "알림 제목",
    "body": "알림 내용",
    "action_url": "https://example.com/notifications/123"
  }
}
```

- `title`, `body`: 알림에 표시할 제목·내용 (data에 포함)
- `action_url`: 탭 시 WebView에 로드할 URL (앱 생성기 2단계에서 설정한 키와 일치)

**대안: notification + data**

```json
{
  "notification": { "title": "알림", "body": "새 메시지가 있습니다." },
  "data": { "action_url": "https://example.com/notifications/123" }
}
```

> **주의**: `notification` 객체에 `click_action`, `link`, `url` 등 URL 필드를 넣지 마세요. 외부 브라우저로 열릴 수 있습니다.

### 4.5 푸시 발송 코드 예시

**Node.js (Firebase Admin SDK) — data-only (권장)**

```javascript
const admin = require('firebase-admin');
const serviceAccount = require('./serviceAccountKey.json');

admin.initializeApp({ credential: admin.credential.cert(serviceAccount) });

// data-only: 앱 내 WebView로 확실히 열림 (포그라운드·백그라운드 모두)
async function sendPushNotification(fcmToken, title, body, actionUrl) {
  await admin.messaging().send({
    token: fcmToken,
    data: {
      title: title,
      body: body,
      action_url: actionUrl,
    },
    android: { priority: 'high' },
  });
}

await sendPushNotification(
  '사용자_FCM_토큰',
  '알림 제목',
  '알림 내용',
  'https://example.com/notifications'
);
```

**PHP (kreait/firebase-php) — data-only**

```php
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

$factory = (new Factory)->withServiceAccount('/path/to/serviceAccountKey.json');
$messaging = $factory->createMessaging();

$message = CloudMessage::withTarget('token', $fcmToken)
    ->withData([
        'title' => '알림 제목',
        'body' => '알림 내용',
        'action_url' => 'https://example.com/notifications',
    ]);

$messaging->send($message);
```

---

## 5. WebSocket + FCM 함께 사용하는 경우

이미 WebSocket으로 실시간 알림을 쓰고 있다면:

| 상황 | 알림 전달 방식 |
|------|----------------|
| 앱 포그라운드 | WebSocket (기존 로직 유지) |
| 앱 백그라운드/종료 | FCM |

### 5.1 서버 발송 로직

알림을 보낼 때 **WebSocket 연결된 클라이언트**와 **FCM 토큰이 등록된 앱 사용자**를 모두 대상으로 합니다.

```
알림 발송 시:
  1. WebSocket으로 연결된 클라이언트에게 전송 (인앱 UI, 배지 등)
  2. 앱 사용자(DB에 FCM 토큰 있음)에게 FCM으로 전송
```

---

## 6. Cold start 세션 복원 (앱 전용 인증 토큰)

> **문제**: FCM 알림 클릭 시 앱이 cold start되면 WebView 쿠키가 유지되지 않아 로그인 페이지가 표시될 수 있습니다.  
> **해결**: 앱 전용 인증 토큰을 SharedPreferences에 저장하고, FCM 클릭 시 토큰으로 세션을 복원합니다.

### 6.1 서버에서 구현할 내용

#### 1) 로그인 완료 시 — app-token 리다이렉트

사용자가 웹 로그인(카카오/네이버/이메일 등)을 완료하면, 서버가 다음 형태로 리다이렉트합니다.

```
https://yoursite.com/auth/app-token?token={64자_랜덤_토큰}&redirect={이동할_경로}
```

| 파라미터 | 설명 |
|----------|------|
| `token` | 64자 랜덤 토큰. 30일 유효. 앱이 SharedPreferences에 저장 |
| `redirect` | 로그인 후 이동할 경로 (예: `/`, `/chat`) |

**앱 동작**: WebView가 위 URL을 로드할 때, 앱이 URL을 가로채 `token`을 추출해 저장하고 `redirect` 경로로 이동합니다.

#### 2) FCM 클릭 시 — app-login (토큰 있음)

앱에 저장된 토큰이 있으면, 앱이 아래 URL을 로드합니다.

```
https://yoursite.com/auth/app-login?token={저장된_토큰}&redirect={목적지_URL}
```

| 파라미터 | 설명 |
|----------|------|
| `token` | 로그인 시 저장한 토큰 |
| `redirect` | **URL 인코딩 필수**. 로그인 처리 후 이동할 경로 (예: `/chat?conversation=123`) |

**서버 동작**:
1. 토큰 검증
2. 세션 생성
3. `redirect` 경로로 리다이렉트
4. **새 토큰 발급** — `app-login` 성공 시 `/auth/app-token?token=NEW&redirect=...` 형태로 리다이렉트하여 앱이 새 토큰을 저장 (매 cold start마다 재로그인 불필요)
5. 토큰은 1회 사용 후 삭제 (재사용 불가)

**redirect 인코딩 예시**:
- 원본: `/chat?conversation=123`
- 인코딩: `%2Fchat%3Fconversation%3D123`

#### 3) FCM 클릭 시 — 토큰 없음

저장된 토큰이 없으면 앱이 `/login?redirect={목적지}` 를 로드합니다.

### 6.2 FCM data 페이로드 예시

```json
{
  "data": {
    "title": "새 메시지",
    "body": "채팅 메시지가 도착했습니다.",
    "action_url": "/chat?conversation=123"
  }
}
```

- `action_url`이 상대 경로(`/chat?conversation=123`) 또는 전체 URL 모두 지원됩니다.
- 앱이 `redirect` 파라미터를 **반드시 URL 인코딩**하여 전달합니다.

### 6.3 플로우 요약

| 상황 | 앱 동작 |
|------|---------|
| 로그인 완료 | `/auth/app-token?token=...` URL에서 token 추출 후 저장 |
| FCM 클릭 (토큰 있음) | `/auth/app-login?token=...&redirect=...` 로드 |
| FCM 클릭 (토큰 없음) | `/login?redirect=...` 로드 |

---

## 7. 체크리스트

앱 생성기 사용 전/후로 아래를 확인하세요.

### 앱 생성 전

- [ ] Firebase 프로젝트 생성
- [ ] Android 앱 등록 (패키지 ID = 앱 생성기 2단계 패키지 ID)
- [ ] google-services.json 다운로드
- [ ] 앱 생성기 2단계에서 푸시 사용 체크 + 파일 업로드

### 웹 개발

- [ ] 앱이 로드하는 페이지에 `window.onFcmTokenReady` 정의 (가능한 한 페이지 로드 직후)
- [ ] 콜백에서 `fetch` 등으로 `/api/register-device`(또는 귀사 API) 호출
- [ ] `fcmToken`, `userId`, `platform` 등을 JSON으로 전송

### 서버 개발

- [ ] FCM 토큰 저장 테이블/스키마
- [ ] Firebase Admin SDK (또는 HTTP API) 연동
- [ ] 서비스 계정 키 보관
- [ ] 푸시 발송 로직 구현

### Cold start 세션 복원 (선택, 권장)

- [ ] 로그인 완료 시 `/auth/app-token?token=...&redirect=...` 리다이렉트
- [ ] `/auth/app-login` 엔드포인트: 토큰 검증 → 세션 생성 → redirect 이동 → 새 토큰 발급

---

## 8. 참고

- [Firebase Cloud Messaging 문서](https://firebase.google.com/docs/cloud-messaging)
- **iOS 앱**: `FCM_IOS_WEB_DEVELOPER_GUIDE.md` 참조 (GoogleService-Info.plist, APNs 키 등)
- [FCM HTTP v1 API](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages)
- FCM은 **무료**이며, 메시지 수 제한이 없습니다.
