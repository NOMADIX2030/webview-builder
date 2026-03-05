# Android 빌더 템플릿 및 Capacitor 분석

> **작성일**: 2026년 3월 6일  
> **목적**: 안드로이드 빌더 설정 변경 전 코드베이스·Capacitor 구조 정확 파악

---

## 1. 프로젝트 구조 요약

### 1.1 템플릿 경로

```
webview-builder/storage/app/build-templates/webview-app/
├── capacitor.config.json      # Capacitor 설정 (빌드 시 치환)
├── www/index.html              # 진입점 (window.location.href → WEB_URL)
├── package.json                # @capacitor/core, @capacitor/android 등
├── android/                    # Android 네이티브 프로젝트
│   ├── app/
│   │   ├── build.gradle        # applicationId, versionCode, versionName
│   │   ├── capacitor.build.gradle
│   │   ├── src/main/
│   │   │   ├── AndroidManifest.xml
│   │   │   ├── java/com/webview/app/
│   │   │   │   ├── MainActivity.java
│   │   │   │   ├── OAuthWebViewClient.java
│   │   │   │   ├── OnCreateWindowWebChromeClient.java
│   │   │   │   ├── DownloadBridge.java
│   │   │   │   ├── DownloadHelper.java
│   │   │   │   └── AppFirebaseMessagingService.java (FCM 시 주입)
│   │   │   └── res/
│   │   └── google-services.json (FCM 시 복사)
│   ├── build.gradle
│   ├── variables.gradle        # minSdk, compileSdk, targetSdk
│   └── settings.gradle
└── ios/                        # iOS (본 문서는 Android 중심)
```

### 1.2 빌드 시 주입(치환)되는 플레이스홀더

| 파일 | 플레이스홀더 | 설명 |
|------|-------------|------|
| capacitor.config.json | `{{PACKAGE_ID}}`, `{{APP_NAME}}`, `{{WEB_URL}}`, `{{APP_HOST}}` | 앱 ID, 이름, 웹 URL, 앱 도메인 |
| www/index.html | `{{WEB_URL}}` | 초기 리다이렉트 URL |
| android/app/build.gradle | `applicationId`, `versionCode`, `versionName` | 패키지 ID, 버전 |
| android/app/src/main/res/values/strings.xml | `app_name`, `title_activity_main` | 앱 표시명 |
| MainActivity.java | `{{EXTRA_PERMISSIONS_ARRAY}}`, `{{FCM_*}}` | 추가 권한, FCM 블록 |
| OAuthWebViewClient.java | `{{APP_DOMAIN_PATTERN}}`, `{{APP_BASE_URL}}`, `{{FCM_BRIDGE_INJECT}}` | 앱 도메인 패턴, FCM 브릿지 |
| AndroidManifest.xml | `{{EXTRA_PERMISSIONS}}`, `{{SPLASH_THEME_NAME}}` | 추가 권한, 스플래시/즉시실행 테마 |

---

## 2. Capacitor Android 웹뷰 하이브리드 앱 구조

### 2.1 Capacitor 기본 동작

- **BridgeActivity**: `MainActivity`가 상속. Capacitor Bridge(JS↔네이티브) 제공.
- **WebView**: `getBridge().getWebView()`로 접근. 기본적으로 `server.url` 또는 로컬 `www/` 로드.
- **server.url**: 외부 URL을 WebView에 직접 로드. 이 프로젝트는 **프로덕션에서 server.url = 사용자 웹사이트** 사용.

### 2.2 capacitor.config.json (현재 템플릿)

```json
{
  "appId": "{{PACKAGE_ID}}",
  "appName": "{{APP_NAME}}",
  "webDir": "www",
  "android": {
    "adjustMarginsForEdgeToEdge": "disable"
  },
  "server": {
    "url": "{{WEB_URL}}",
    "cleartext": true,
    "allowNavigation": [
      "kauth.kakao.com", "kapi.kakao.com", "accounts.kakao.com",
      "accounts.google.com", "www.google.com",
      "nid.naver.com", "naver.com",
      "www.facebook.com", "facebook.com",
      "appleid.apple.com", "apple.com",
      "{{APP_HOST}}"
    ]
  }
}
```

| 옵션 | 의미 |
|------|------|
| **server.url** | WebView가 로드할 URL. 사용자 웹사이트(https://example.com 등). |
| **server.cleartext** | HTTP 허용. API 28+ 기본값은 cleartext 비활성. |
| **server.allowNavigation** | 이 도메인들은 **외부 브라우저로 열지 않고** WebView 내에서 로드. OAuth, 앱 도메인 포함. |

### 2.3 Capacitor 공식 문서 요약 (server)

| 옵션 | 설명 | 기본값 |
|------|------|--------|
| server.url | WebView에 로드할 외부 URL | - |
| server.cleartext | HTTP(cleartext) 허용 | false |
| server.allowNavigation | WebView 내 네비게이션 허용 도메인 목록 | [] |
| server.hostname | 로컬 호스트명 (localhost 등) | localhost |
| server.androidScheme | Android scheme (http/https) | https |
| server.errorPath | 에러 시 표시할 로컬 HTML 경로 | null |

**주의**: `allowNavigation`에 포함된 URL로 이동 시 Android에서 Capacitor 플러그인(JS Bridge) 접근이 제한될 수 있다는 이슈가 있음([#7454](https://github.com/ionic-team/capacitor/issues/7454)). 이 프로젝트는 **server.url 자체가 사용자 웹사이트**이므로, 초기 로드부터 해당 URL에서 동작. OAuth 등은 `OAuthWebViewClient`로 네이티브에서 처리.

### 2.4 Android 전용 config 옵션 (Capacitor)

| 옵션 | 설명 |
|------|------|
| android.path | Android 프로젝트 경로 |
| android.overrideUserAgent | WebView User-Agent |
| android.appendUserAgent | User-Agent에 추가할 문자열 |
| android.allowMixedContent | Mixed content 허용 |
| android.captureInput | 단순 키보드 모드 |
| android.webContentsDebuggingEnabled | 릴리스에서도 웹 디버깅 |
| android.minWebViewVersion | 최소 WebView 버전 (기본 60) |
| android.useLegacyBridge | addJavascriptInterface 사용 (구 방식) |
| android.adjustMarginsForEdgeToEdge | Edge-to-edge 마진 조정 (css/disable) |

---

## 3. Android 네이티브 컴포넌트

### 3.1 MainActivity (BridgeActivity 상속)

| 기능 | 구현 |
|------|------|
| 뒤로가기 | `goBack()` → 서브 경로면 origin 이동 → 루트에서 2회 연속 시 종료 |
| Edge-to-edge | `WindowCompat.setDecorFitsSystemWindows(false)`, 상태바 숨김 |
| 권한 요청 | 저장소, 알림(POST_NOTIFICATIONS), 2단계 추가 권한 |
| WebView 핸들러 | DownloadBridge, DownloadListener, OAuthWebViewClient, OnCreateWindowWebChromeClient |
| FCM | 토큰 → `window.onFcmTokenReady`, 알림 탭 시 URL 로드 |

### 3.2 OAuthWebViewClient

- **역할**: OAuth/소셜 로그인 URL, 앱 도메인 URL을 **WebView 내에서** 로드 (외부 브라우저 이탈 방지).
- **패턴**: 카카오, 구글, 네이버, 페이스북, 애플 + `{{APP_DOMAIN_PATTERN}}`.
- **app-token**: `/auth/app-token?token=...&redirect=...` 처리 → SharedPreferences 저장, redirect로 이동.
- **주입 스크립트**: `saveImageToDevice`, blob 훅, FCM 브릿지.

### 3.3 OnCreateWindowWebChromeClient

- `window.open` / `target="_blank"` 시 **부모 WebView에 로드** (새 창 대신).

### 3.4 DownloadBridge / DownloadHelper

- `data:` URL, `blob:` URL → 갤러리(Pictures) 또는 다운로드(Downloads) 저장.
- `AndroidBridge` JS 인터페이스로 `saveDataUrl`, `saveBlobData` 노출.

### 3.5 AppFirebaseMessagingService (FCM 사용 시)

- FCM 메시지 수신 → 알림 표시, 탭 시 `MainActivity`로 URL 전달.
- `fcm_click_url` 또는 `data[action_url]` 사용.

---

## 4. CapacitorBuildService 주입 로직

### 4.1 injectConfig()

- `capacitor.config.json`, `www/index.html`: `{{PACKAGE_ID}}`, `{{APP_NAME}}`, `{{WEB_URL}}`, `{{APP_HOST}}`
- `android/app/build.gradle`: applicationId, versionCode, versionName
- `strings.xml`: app_name, title_activity_main
- iOS: Info.plist, project.pbxproj

### 4.2 injectSplashConfig()

- `splash_image_path` 유무에 따라 `{{SPLASH_THEME_NAME}}` 치환: `AppTheme.NoActionBarLaunch` / `AppTheme.NoActionBar`
- 업로드 있음: `copySplash()` → drawable, drawable-port-*, drawable-land-*에 splash.png 복사
- 업로드 없음: Launch 테마 미사용 → 즉시 앱 실행

### 4.3 injectSplashConfigIos()

- iOS: 업로드 있으면 Splash.imageset에 복사, 없으면 흰색 빈 이미지

### 4.4 injectAppDomainToOAuthWebViewClient()

- `web_url`에서 host 추출 → `Pattern.compile("^https?://([^/]*\\.)?{host}(/.*)?")`
- `APP_BASE_URL`: scheme + host + port

### 4.5 injectExtraPermissions()

- `config_json.extra_permissions`: ACCESS_FINE_LOCATION, CAMERA, RECORD_AUDIO 등
- AndroidManifest `uses-permission`, MainActivity `EXTRA_PERMISSIONS_ARRAY`

### 4.6 injectFcmConfig()

- FCM 사용 시: firebase-messaging 의존성, Manifest Service, MainActivity FCM 블록, OAuthWebViewClient FCM 브릿지, AppFirebaseMessagingService.java 생성

### 4.7 copyGoogleServicesJson()

- `config_json.google_services_path` → `android/app/google-services.json`
- 패키지명을 `build.package_id`로 치환

---

## 5. variables.gradle (SDK 버전)

```gradle
minSdkVersion = 22
compileSdkVersion = 34
targetSdkVersion = 34
coreSplashScreenVersion = '1.0.1'
```

- **minSdk 22**: Android 5.1+ (대부분 기기 지원)
- **targetSdk 34**: Android 14
- **coreSplashScreenVersion 1.0.1**: compileSdk 34, AGP 8.2.1 호환 (1.2.0은 compileSdk 35 필요)

---

## 6. 패키지/네임스페이스

| 구분 | 템플릿 기본값 | 빌드 시 변경 |
|------|---------------|-------------|
| **applicationId** | com.webview.app | ✅ `build.package_id` |
| **namespace** | com.webview.app | ❌ 유지 |
| **Java 패키지** | com.webview.app | ❌ 유지 (디렉터리 구조 동일) |

- `applicationId`: 앱스토어/기기 식별자.
- `namespace`: Android 리소스/ R 클래스 패키지. Java 소스 경로와 일치.
- Java 패키지 경로 `com/webview/app/`는 모든 빌드에서 동일. `applicationId`만 사용자 패키지로 변경.

---

## 7. 설정 변경 시 참고 사항

### 7.1 capacitor.config.json 수정

- `server.url`, `server.allowNavigation`, `server.cleartext` 등은 `injectConfig()`에서 `capacitor.config.json` 치환 시 반영됨.
- **새 allowNavigation 도메인** 추가 시: 템플릿 `capacitor.config.json`에 추가하거나, `injectConfig()`에서 동적으로 확장.

### 7.2 AndroidManifest / build.gradle

- 권한, `android:configChanges`, `launchMode` 등은 템플릿 파일을 직접 수정.
- `injectExtraPermissions()`는 `{{EXTRA_PERMISSIONS}}` 주변만 교체.

### 7.3 MainActivity / OAuthWebViewClient

- 로직 변경 시 템플릿 Java 파일 수정.
- 플레이스홀더(`{{...}}`) 추가 시 `CapacitorBuildService`에 치환 로직 추가.

### 7.4 minSdk / targetSdk

- `variables.gradle` 수정. API 24+ 권장(Capacitor 공식).

---

## 8. 참조 문서

| 문서 | 용도 |
|------|------|
| [Capacitor Config](https://capacitorjs.com/docs/config) | capacitor.config 전체 스키마 |
| [Capacitor Android Configuration](https://capacitorjs.com/docs/android/configuration) | AndroidManifest, 패키지 ID, 권한 |
| docs/CAPACITOR_LEARNING.md | Capacitor 학습, 웹뷰 설정 |
| docs/DEV_SPEC.md | 빌드 프로세스, API 명세 |
