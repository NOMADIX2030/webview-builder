# FCM 푸시 알림 (iOS) — 웹 개발자 연동 가이드

> **대상**: 우리 앱 생성기로 만든 **iOS** 웹뷰 앱에 푸시 알림을 적용하려는 웹사이트 개발자  
> **목적**: iOS 푸시 알림이 정상 동작하도록 웹·서버에서 해야 할 작업 안내  
> **참조**: Android용은 `FCM_WEB_DEVELOPER_GUIDE.md` 참조. 웹·서버 로직은 **동일**합니다.

---

## 1. 개요

### 1.1 역할 구분

| 구분 | 담당 | 하는 일 |
|------|------|---------|
| **앱 생성기** | 우리 | FCM이 포함된 IPA 빌드, 앱에서 토큰을 웹에 전달하는 브릿지 제공 |
| **웹 개발자** | 귀사 | 토큰 수신 → 서버 등록, 푸시 발송 로직 구현 |

앱 생성기는 **앱 빌드만** 제공합니다. 푸시 발송은 **귀사 서버**에서 수행해야 합니다.

### 1.2 Android와의 공통점

| 항목 | Android | iOS |
|------|---------|-----|
| **토큰 콜백** | `window.onFcmTokenReady(token)` | **동일** |
| **서버 API** | FCM API로 발송 | **동일** (같은 FCM 토큰) |
| **data 페이로드** | `action_url`, `title`, `body` | **동일** |
| **Cold start** | app-token / app-login | **동일** |

웹 코드와 서버 코드는 **Android와 iOS에서 동일**합니다. `platform` 값만 `ios_app`으로 구분하면 됩니다.

### 1.3 앱이 제공하는 기능 (자동 적용)

우리 앱 생성기로 빌드한 iOS 앱은 아래를 **자동으로** 처리합니다.

| 기능 | 설명 |
|------|------|
| **토큰 → 웹 전달** | FCM 토큰 발급 후 `window.onFcmTokenReady(token)` 콜백 호출 |
| **알림 탭 시 URL** | 2단계에서 설정한 키(`action_url` 등)로 `data`에 URL 포함 시, 탭하면 WebView에서 해당 URL 로드 (포그라운드·백그라운드·Cold start 모두 지원) |
| **Cold start 세션 복원** | 앱 전용 인증 토큰(app-token/app-login) 방식으로 앱 재시작 시에도 로그인 상태 유지 (섹션 6 참조) |
| **Bundle ID 치환** | GoogleService-Info.plist의 Bundle ID를 2단계 Bundle ID로 자동 치환 |

### 1.4 전체 흐름

```
[1] Firebase 프로젝트 생성 (귀사) — Android와 동일 프로젝트
    ↓
[2] Firebase에 iOS 앱 등록 + APNs 키 업로드 (귀사)
    ↓
[3] 앱 생성 시 GoogleService-Info.plist 업로드 (귀사)
    ↓
[4] 앱 설치·실행 → FCM 토큰 발급 (자동)
    ↓
[5] 앱이 웹에 토큰 전달 (자동)
    ↓
[6] 웹이 서버에 토큰 등록 (귀사 구현) — Android와 동일
    ↓
[7] 서버가 FCM API로 푸시 발송 (귀사 구현) — Android와 동일
```

---

## 2. 앱 생성 전 준비 (Firebase · Apple 설정)

### 2.1 사전 요구사항

| 항목 | 내용 |
|------|------|
| **Apple Developer Program** | 연회비 $99. 푸시 알림은 유료 계정 필요 |
| **실기기 테스트** | iOS 시뮬레이터는 APNs 미지원. **실제 iPhone**에서만 푸시 수신 가능 |

### 2.2 Firebase 프로젝트

Android 앱을 이미 등록했다면 **동일 Firebase 프로젝트**를 사용합니다. 새 프로젝트라면 [Firebase Console](https://console.firebase.google.com)에서 생성합니다.

### 2.3 iOS 앱 등록 (Firebase)

1. Firebase 프로젝트 대시보드에서 **+ 앱 추가** 버튼 클릭
2. **Apple (iOS)** 플랫폼 선택
3. **Apple 번들 ID** 입력  
   - **중요**: 앱 생성기 2단계에서 사용하는 **Bundle ID**와 **동일**해야 함  
   - 예: `com.example.myapp`
4. (선택) 앱 닉네임, App Store ID
5. **앱 등록** 클릭

### 2.4 GoogleService-Info.plist 다운로드

1. iOS 앱 등록 완료 후 **GoogleService-Info.plist 다운로드** 버튼 클릭
2. 파일 저장

> **참고**: Firebase 콘솔 가이드에 "Xcode에 추가" 등이 나올 수 있습니다.  
> **우리 앱 생성기를 사용하는 경우 이 단계는 건너뛰세요.**  
> GoogleService-Info.plist 적용은 **앱 생성기가 빌드 시 자동으로 처리**합니다.

### 2.5 APNs 인증 키 업로드 (필수)

iOS 푸시는 **APNs(Apple Push Notification service)**를 거칩니다. Firebase가 APNs로 전달하려면 **APNs 인증 키**를 Firebase에 등록해야 합니다.

#### 1) APNs 키 생성 (Apple Developer)

1. [Apple Developer](https://developer.apple.com/account) → **Certificates, Identifiers & Profiles**
2. **Keys** → **+** (새 키 생성)
3. **Key Name** 입력 (예: `APNs Key`)
4. **Apple Push Notifications service (APNs)** 체크
5. **Continue** → **Register**
6. **Key ID** 복사 (나중에 필요)
7. **.p8 파일 다운로드** — **한 번만** 다운로드 가능. 안전하게 보관

#### 2) Firebase에 키 업로드

1. Firebase Console → **프로젝트 설정** (톱니바퀴)
2. **Cloud Messaging** 탭
3. **Apple 앱 구성** 섹션에서 **APNs 인증 키** → **업로드**
4. .p8 파일 선택, **Key ID** 입력, **팀 ID** (Apple Developer 팀 ID) 입력
5. **업로드** 클릭

> **개발/배포**: 개발용·배포용 키를 각각 업로드할 수 있습니다. 둘 다 업로드하면 개발·배포 빌드 모두 푸시 수신 가능합니다.

### 2.6 앱 생성 시 업로드

앱 생성기 **2단계**에서 (iOS 선택 시):

1. **푸시 알림 사용** 체크
2. **GoogleService-Info.plist** 파일 업로드
3. **Bundle ID**가 Firebase에 등록한 값과 일치하는지 확인  
   - 참고: 빌드 시 Bundle ID를 자동 치환하므로, Firebase에 다른 Bundle ID로 등록된 plist를 업로드해도 빌드는 성공합니다.  
     단, **FCM 토큰 발급**을 위해 Firebase에는 2단계 Bundle ID와 동일하게 등록해야 합니다.

---

## 3. 웹에서 해야 할 일 (상세)

> **Android와 동일합니다.** `platform` 값만 `ios_app`으로 변경하면 됩니다.

### 3.1 콜백 정의

`window.onFcmTokenReady` — Android와 **동일한 함수**를 사용합니다. 앱이 iOS든 Android든 토큰을 받으면 이 콜백을 호출합니다.

### 3.2 예시 코드 (iOS용 platform만 구분)

```html
<script>
(function() {
  window.onFcmTokenReady = function(token) {
    if (!token) return;
    fetch('/api/register-device', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fcmToken: token,
        userId: getCurrentUserId(),
        platform: 'ios_app',   // iOS: ios_app, Android: android_app
      }),
    });
  };

  function getCurrentUserId() {
    return window.__USER_ID__ || sessionStorage.getItem('userId') || null;
  }
})();
</script>
```

### 3.3 Android·iOS 통합 예시

동일 페이지에서 두 플랫폼 모두 지원하려면, `platform`을 런타임에 판별합니다.

```javascript
// Capacitor 또는 User-Agent로 플랫폼 판별
const platform = (typeof Capacitor !== 'undefined' && Capacitor.getPlatform?.() === 'ios')
  ? 'ios_app'
  : 'android_app';

window.onFcmTokenReady = function(token) {
  if (!token) return;
  fetch('/api/register-device', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ fcmToken: token, userId: null, platform }),
  });
};
```

> **참고**: 앱 생성기 웹뷰에서는 `Capacitor`가 없을 수 있습니다. 서버에서 User-Agent 또는 별도 파라미터로 플랫폼을 받아 저장하는 방식도 가능합니다.

---

## 4. 서버에서 해야 할 일

### 4.1 토큰 저장

Android와 **동일**합니다. `platform` 컬럼으로 `ios_app` / `android_app` 구분만 추가하면 됩니다.

```sql
CREATE TABLE device_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  fcm_token VARCHAR(255) NOT NULL,
  platform VARCHAR(20) DEFAULT 'android_app',  -- 'ios_app' 또는 'android_app'
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (fcm_token)
);
```

### 4.2 푸시 발송

**Android와 동일한 FCM API**를 사용합니다. iOS 토큰이든 Android 토큰이든 `admin.messaging().send({ token: fcmToken, ... })`로 발송하면 됩니다.

```javascript
// Node.js — Android·iOS 동일
await admin.messaging().send({
  token: fcmToken,
  data: {
    title: '알림 제목',
    body: '알림 내용',
    action_url: 'https://example.com/notifications/123',
  },
  android: { priority: 'high' },
  apns: { payload: { aps: { 'content-available': 1 } } },  // iOS 선택
});
```

서비스 계정 키, Firebase Admin SDK 설치 등은 `FCM_WEB_DEVELOPER_GUIDE.md` 4절과 동일합니다.

---

## 5. Cold start 세션 복원 (앱 전용 인증 토큰)

> **Android와 동일**합니다. app-token, app-login 흐름이 iOS에서도 동일하게 적용됩니다.

| 상황 | 앱 동작 |
|------|---------|
| 로그인 완료 | `/auth/app-token?token=...` URL에서 token 추출 후 저장 (UserDefaults) |
| FCM 클릭 (토큰 있음) | `/auth/app-login?token=...&redirect=...` 로드 |
| FCM 클릭 (토큰 없음) | `/login?redirect=...` 로드 |

자세한 내용은 `FCM_WEB_DEVELOPER_GUIDE.md` 6절을 참조하세요.

---

## 6. 체크리스트

### 앱 생성 전 (iOS)

- [ ] Apple Developer Program 가입 ($99/년)
- [ ] Firebase 프로젝트 생성 (또는 기존 프로젝트 사용)
- [ ] Firebase에 iOS 앱 등록 (Bundle ID = 앱 생성기 2단계 Bundle ID)
- [ ] GoogleService-Info.plist 다운로드
- [ ] APNs 인증 키(.p8) 생성 (Apple Developer)
- [ ] APNs 키를 Firebase Cloud Messaging에 업로드
- [ ] 앱 생성기 2단계에서 푸시 사용 체크 + GoogleService-Info.plist 업로드

### 웹 개발

- [ ] `window.onFcmTokenReady` 정의 (가능한 한 페이지 로드 직후)
- [ ] 콜백에서 `platform: 'ios_app'` 포함하여 서버에 토큰 전송

### 서버 개발

- [ ] FCM 토큰 저장 (platform 구분)
- [ ] Firebase Admin SDK 연동 (Android와 동일)
- [ ] 푸시 발송 로직 (Android와 동일 API)

### Cold start 세션 복원 (선택, 권장)

- [ ] 로그인 완료 시 `/auth/app-token?token=...&redirect=...` 리다이렉트
- [ ] `/auth/app-login` 엔드포인트 구현

---

## 7. 자주 묻는 질문

**Q. iOS 시뮬레이터에서 푸시가 안 와요.**  
A. iOS 시뮬레이터는 APNs를 지원하지 않습니다. **실제 iPhone**에서 테스트해야 합니다.

**Q. Android와 다른 서버 API를 써야 하나요?**  
A. 아니요. **동일한 FCM API**를 사용합니다. 토큰 형식만 다르며, Firebase가 플랫폼에 맞게 전달합니다.

**Q. GoogleService-Info.plist와 google-services.json을 둘 다 업로드해야 하나요?**  
A. Android 빌드 시에는 google-services.json, iOS 빌드 시에는 GoogleService-Info.plist를 업로드합니다. 두 플랫폼 모두 빌드한다면 **둘 다** Firebase에서 다운로드하여 각각 업로드합니다.

**Q. APNs 키를 안 올리면 어떻게 되나요?**  
A. FCM 토큰은 발급되지만, **푸시가 전달되지 않습니다**. Firebase가 APNs로 전달하려면 반드시 APNs 키가 등록되어 있어야 합니다.

---

## 8. 참고

- [Firebase Cloud Messaging (iOS)](https://firebase.google.com/docs/cloud-messaging/ios/client)
- [APNs 개요](https://developer.apple.com/documentation/usernotifications)
- `FCM_WEB_DEVELOPER_GUIDE.md` — Android용 상세 가이드 (웹·서버 로직 공통)
