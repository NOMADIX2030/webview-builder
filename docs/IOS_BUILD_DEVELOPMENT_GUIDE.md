# iOS 빌드 개발 가이드

> **목적**: AI(Cursor 에이전트)가 이 문서를 참고하여 webview-builder에 Apple(iOS) 빌드 기능을 추가할 수 있도록 작성된 개발 문서  
> **작성일**: 2026년 2월 27일  
> **참조**: Capacitor 공식 문서, Android 구현 현황

---

## 1. 문서 개요

### 1.1 이 문서의 역할

| 대상 | 용도 |
|------|------|
| **AI 에이전트** | iOS 빌드 구현 시 참조할 핵심 개발 가이드. Capacitor 표준 방식·코드로 구현 |
| **개발자** | 구현 가이드, 아키텍처 이해 |

### 1.2 목표

- **현재 Android에 구현된 기능**을 **iOS에 동일하게** 구현
- **Capacitor 공식 문서** 기반의 표준 방식·코드로 먼저 구현
- UI/UX 프로세스, 파일/폴더 트리 포함하여 AI가 개발할 수 있도록 문서화

### 1.3 사전 필수 읽기

개발 시작 전 **반드시** 아래 문서를 읽을 것:

1. `docs/PROJECT_STATUS.md` — 구현 현황, 알려진 이슈
2. `docs/DEV_SPEC.md` — 전체 명세 (필요 시)
3. `AGENTS.md` — 프로젝트 규칙, 환경

---

## 2. 프로젝트 맥락

### 2.1 webview-builder란

여러 웹사이트를 **웹뷰 앱**으로 감싸 APK/IPA를 생성하는 서비스. 사용자는 생성된 파일을 직접 앱스토어에 출시한다.

### 2.2 현재 상태 (Android)

| 구분 | 내용 |
|------|------|
| **1단계** | 웹 URL, 앱 아이콘, 스플래시 업로드 |
| **2단계** | 앱 이름, 패키지 ID, FCM(google-services.json) 등 자동 생성·수정 |
| **3단계** | 시뮬레이션, APK 아이콘 미리보기, 빌드 요청 |
| **빌드** | Capacitor 템플릿 → Android APK, Keystore 자동 생성 (동기 2~5분) |
| **기능** | WebView URL 로드, FCM 푸시, OAuth 인앱, 다운로드, 뒤로가기 2회 종료 |

### 2.3 iOS 추가 시 목표

| Android 기능 | iOS 대응 |
|--------------|----------|
| WebView URL 로드 | WKWebView + capacitor.config server.url |
| FCM 푸시 | APNs + Firebase (GoogleService-Info.plist) |
| OAuth 인앱 | WKWebView 내 로드 (외부 브라우저 이탈 방지) |
| 다운로드 (PDF/이미지) | WKDownloadDelegate 또는 JS 브릿지 |
| 뒤로가기 2회 종료 | 네비게이션 바/제스처 처리 |

---

## 3. Capacitor iOS 구조 (공식 문서 기반)

### 3.1 iOS 플랫폼 추가

```bash
# 템플릿 프로젝트 루트에서
npm install @capacitor/ios
npx cap add ios
```

### 3.2 생성되는 ios/ 폴더 구조 (Capacitor 6 + SPM)

**SPM 사용 시** (`npx cap add ios --packagemanager SPM`):

```
ios/
├── App/
│   ├── App/
│   │   ├── AppDelegate.swift
│   │   ├── Info.plist
│   │   ├── Assets.xcassets/
│   │   └── capacitor.config.json
│   ├── App.xcodeproj/        # SPM은 .xcworkspace 없음
│   └── CapApp-SPM/           # Swift Package Manager 패키지
└── .gitignore
```

**CocoaPods 사용 시**: Podfile, App.xcworkspace, Pods/ 존재.

### 3.3 Capacitor iOS 요구사항

| 항목 | 요구사항 |
|------|----------|
| **OS** | macOS (iOS 빌드는 Mac에서만 가능) |
| **Xcode** | 15.0+ (Capacitor 6 기준). `/Applications/Xcode.app` 설치 |
| **Xcode Command Line Tools** | `xcode-select --install` 또는 Xcode 앱 포함 |
| **패키지 매니저** | SPM(권장, CocoaPods 불필요) 또는 CocoaPods |
| **Node.js** | 18+ (프로젝트에 이미 있음) |

> **실제 환경 검증 (2026-02)**: SPM 사용 시 CocoaPods 설치 없이 iOS 빌드 가능. `xcode-select`가 Command Line Tools만 가리키면 Xcode 앱 경로를 `DEVELOPER_DIR`로 지정.

### 3.4 빌드 명령 (Capacitor 공식)

```bash
# 동기화
npx cap sync ios

# 빌드 (IPA 생성)
npx cap build ios
# 또는 xcodebuild 직접 사용
xcodebuild -workspace ios/App/App.xcworkspace -scheme App -configuration Release -archivePath build/App.xcarchive archive
```

> **참고**: `npx cap build ios`는 내부적으로 xcodebuild를 호출. 서명·프로비저닝 프로파일 설정 필요.

---

## 4. 파일/폴더 트리

### 4.1 현재 템플릿 (Android만)

```
webview-builder/storage/app/build-templates/webview-app/
├── android/
│   ├── app/
│   │   ├── build.gradle
│   │   ├── src/main/
│   │   │   ├── AndroidManifest.xml
│   │   │   ├── java/com/webview/app/
│   │   │   │   ├── MainActivity.java
│   │   │   │   ├── OAuthWebViewClient.java
│   │   │   │   ├── DownloadBridge.java
│   │   │   │   ├── AppFirebaseMessagingService.java  # FCM
│   │   │   │   └── ...
│   │   │   └── res/
│   │   └── google-services.json  # 빌드 시 주입
│   └── ...
├── www/
│   └── index.html
├── assets/                  # @capacitor/assets용
├── capacitor.config.json
└── package.json
```

### 4.2 iOS 추가 후 예상 구조

```
webview-builder/storage/app/build-templates/webview-app/
├── android/                 # 기존
├── ios/                      # 신규 (npx cap add ios로 생성)
│   └── App/
│       ├── App/
│       │   ├── AppDelegate.swift
│       │   ├── Info.plist
│       │   ├── GoogleService-Info.plist  # FCM 시 빌드 시 주입
│       │   └── Assets.xcassets/          # 아이콘, 스플래시
│       ├── App.xcworkspace/
│       └── Podfile
├── www/
├── assets/
├── capacitor.config.json
└── package.json
```

### 4.3 Laravel/Blade 관련 경로

```
webview-builder/
├── app/
│   ├── Http/Controllers/
│   │   ├── Api/BuildController.php
│   │   └── Web/BuildController.php
│   ├── Jobs/ProcessBuildJob.php
│   └── Services/
│       ├── BuildConfigService.php
│       └── CapacitorBuildService.php
├── resources/views/
│   ├── step1.blade.php
│   ├── step2.blade.php
│   └── step3.blade.php
├── routes/
│   ├── web.php
│   └── api.php
└── storage/app/
    ├── builds/               # APK, IPA, Keystore 저장
    └── build-templates/webview-app/
```

---

## 5. Android 기능 → iOS 대응 매핑

### 5.1 핵심 기능 목록

| # | Android 구현 | iOS 대응 (Capacitor 표준) |
|---|--------------|---------------------------|
| 1 | WebView URL 로드 | `capacitor.config.json` → `server.url` (동일) |
| 2 | 앱 아이콘 | `@capacitor/assets generate --ios` 또는 PHP GD → Assets.xcassets |
| 3 | 스플래시 | Assets.xcassets/Splash.imageset |
| 4 | 앱 이름 | Info.plist `CFBundleDisplayName` |
| 5 | Bundle ID | Xcode 프로젝트 설정, Info.plist |
| 6 | FCM 푸시 | APNs + Firebase, GoogleService-Info.plist |
| 7 | OAuth 인앱 | WKNavigationDelegate, `decidePolicyFor` |
| 8 | 다운로드 | WKDownloadDelegate 또는 JS→Swift 브릿지 |
| 9 | window.open | WKUIDelegate `createWebViewWith` |
| 10 | 뒤로가기 2회 종료 | JS 브릿지 또는 네이티브 제스처 |

### 5.2 Capacitor 표준 방식 우선

- **Phase 1**: Capacitor가 제공하는 기본 구조만 사용 (WebView URL 로드, 아이콘, 앱 이름, Bundle ID)
- **Phase 2**: FCM → Firebase iOS SDK, APNs 설정
- **Phase 3**: OAuth, 다운로드 등 커스텀 Swift 코드

---

## 6. 빌드 프로세스 (Android vs iOS)

### 6.1 Android (현재)

```
1. 템플릿 복사
2. injectConfig (capacitor.config, www/index.html, build.gradle, strings.xml)
3. injectAppDomainToOAuthWebViewClient
4. injectExtraPermissions
5. copyGoogleServicesJson (FCM 시)
6. injectFcmConfig
7. copyIcons (@capacitor/assets 또는 PHP GD)
8. generateKeystore
9. configureSigning
10. npm install
11. npx cap sync android
12. ./gradlew assembleRelease
13. APK, Keystore → storage/app/builds/{id}/
```

### 6.2 iOS (Phase 1 구현)

```
1. 템플릿 복사 (ios/ 포함, SPM 사용)
2. injectConfig (capacitor.config, www/index.html, ios/App/App/capacitor.config.json)
3. injectConfigIos (Info.plist CFBundleDisplayName, project.pbxproj PRODUCT_BUNDLE_IDENTIFIER 등)
4. copyIcons (Android + iOS, @capacitor/assets 또는 PHP GD)
5. npm install
6. npx cap sync ios
7. xcodebuild -sdk iphonesimulator build (시뮬레이터용, 서명 불필요)
8. App.app → ZIP → storage/app/builds/{id}/App-ios-simulator.zip
```

### 6.3 iOS 빌드 시 주의사항

| 항목 | 설명 |
|------|------|
| **서명** | IPA 빌드 시 Apple Developer 계정, 프로비저닝 프로파일 필요 |
| **무서명 빌드** | 시뮬레이터용은 서명 없이 가능. 실제 기기/배포는 서명 필수 |
| **xcodebuild** | `-allowProvisioningUpdates` 옵션으로 자동 서명 시도 가능 |

---

## 7. UI/UX 프로세스

### 7.1 현재 흐름 (Android만)

```
[1단계] 웹 URL, 앱 아이콘, 스플래시
    → [2단계] 앱 이름, 패키지 ID, FCM, 추가 권한
    → [3단계] 시뮬레이션, 요약, 빌드 시작
    → [결과] APK, Keystore 다운로드
```

### 7.2 iOS 추가 시 UI 변경

#### 1단계 (step1.blade.php)

| 추가 항목 | 타입 | 설명 |
|-----------|------|------|
| `platforms[]` | checkbox[] | `android`, `ios` — 빌드할 플랫폼 선택 |

**UI 예시**:
```
□ Android APK
□ iOS IPA
```
- 최소 1개 선택 필수
- 둘 다 선택 시 3단계에서 플랫폼별 빌드 진행

#### 2단계 (step2.blade.php)

| 플랫폼 | 추가/변경 필드 |
|--------|----------------|
| **Android** | 기존 유지 (패키지 ID, google-services.json) |
| **iOS** | `bundle_id` (기본값: package_id와 동일), `GoogleService-Info.plist` (FCM 시) |

**bundle_id 규칙**: `com.example.app` 형식. package_id와 동일하게 자동 생성 가능.

**UI 예시** (iOS 선택 시에만 표시):
```
Bundle ID (iOS)
[com.myplatform.app                    ]  자동 생성됨
```

#### 3단계 (step3.blade.php)

| 변경 | 내용 |
|------|------|
| 요약 | 선택된 플랫폼별로 APK/IPA 표시 |
| 빌드 버튼 | "Android 빌드", "iOS 빌드" 또는 "둘 다 빌드" |
| 결과 | artifacts.apk, artifacts.ipa 각각 다운로드 링크 |

### 7.3 세션/API 데이터 구조

**build_step1** (확장):
```php
[
    'web_url' => '...',
    'app_icon_path' => '...',
    'splash_image_path' => '...',
    'platforms' => ['android', 'ios'],  // 신규
]
```

**build_step2** (확장):
```php
[
    // 기존
    'app_name' => '...',
    'package_id' => '...',
    'fcm_enabled' => true,
    'google_services_path' => '...',
    // iOS 추가
    'bundle_id' => 'com.myplatform.app',  // package_id와 동일 또는 별도
    'google_service_info_path' => '...',   // FCM 시 (iOS용 plist)
]
```

---

## 8. 구현 단계 (Phase)

### Phase 1: 기본 iOS 빌드 (Capacitor 표준만)

| 순서 | 작업 | 파일/위치 |
|------|------|-----------|
| 1 | 템플릿에 ios/ 추가 | `npx cap add ios` 실행 후 템플릿에 반영 |
| 2 | package.json에 @capacitor/ios 추가 | build-templates/webview-app/package.json |
| 3 | CapacitorBuildService에 iOS 빌드 분기 | `build()` 메서드에서 platforms 확인 |
| 4 | injectConfig iOS 확장 | Info.plist, ios/App/ 프로젝트 설정 |
| 5 | copyIcons iOS 확장 | `@capacitor/assets generate --ios` 또는 수동 |
| 6 | runIosBuild 메서드 추가 | `npx cap sync ios` → `xcodebuild` |
| 7 | DB/API에 ipa_path 반영 | Build 모델, API 응답 |

**검증**: iOS 시뮬레이터에서 웹뷰 앱 실행 확인.

### Phase 2: FCM 푸시 (iOS)

| 순서 | 작업 | 파일/위치 |
|------|------|-----------|
| 1 | GoogleService-Info.plist 업로드 | 2단계 폼, config_json |
| 2 | copyGoogleServiceInfoPlist | CapacitorBuildService |
| 3 | Firebase iOS SDK, APNs 설정 | Podfile, Info.plist |
| 4 | AppDelegate에 FCM 초기화 | Swift 코드 주입 |
| 5 | 토큰 → 웹 전달 | `window.onFcmTokenReady` (Android와 동일) |

### Phase 3: OAuth, 다운로드 (iOS)

| 순서 | 작업 | 설명 |
|------|------|------|
| 1 | WKNavigationDelegate | OAuth URL을 WebView 내 로드 |
| 2 | WKUIDelegate | window.open → 부모 WebView |
| 3 | 다운로드 | WKDownloadDelegate 또는 JS 브릿지 |

### Phase 4: IPA 배포용 빌드

| 순서 | 작업 | 설명 |
|------|------|------|
| 1 | 프로비저닝 프로파일 | 사용자 업로드 또는 자동 서명 |
| 2 | xcodebuild archive | Release 구성 |
| 3 | xcrun altool / Transporter | IPA 업로드 (선택) |

---

## 9. 연동 포인트

### 9.1 CapacitorBuildService.php

| 메서드 | Android | iOS 추가 |
|--------|---------|----------|
| `build()` | 플랫폼 분기: `if (android) { ... } elseif (ios) { ... }` | |
| `injectConfig()` | capacitor.config, build.gradle, strings.xml | Info.plist, xcodeproj |
| `copyIcons()` | @capacitor/assets --android, PHP GD | @capacitor/assets --ios |
| `copyGoogleServicesJson()` | android/app/ | — |
| `copyGoogleServiceInfoPlist()` | — | ios/App/App/ |
| `runCapSync()` | `cap sync android` | `cap sync ios` |
| `runAndroidBuild()` | gradlew assembleRelease | — |
| `runIosBuild()` | — | xcodebuild archive |

### 9.2 BuildConfigService.php

| 메서드 | 변경 |
|--------|------|
| `generatePackageId()` | Android용 (유지) |
| `generateBundleId()` | 신규. 기본값: package_id와 동일. iOS용. |

### 9.3 Build 모델 / DB

| 컬럼 | 용도 |
|------|------|
| `apk_path` | Android APK |
| `ipa_path` | iOS IPA (신규 또는 이미 있음) |
| `keystore_path` | Android Keystore |

### 9.4 API

| 엔드포인트 | 변경 |
|------------|------|
| GET /api/build/{id} | `artifacts.ipa` 이미 지원 (ipa_path 있을 때) |
| GET /api/build/{id}/download/ipa | 이미 구현됨 (type=ipa) |

---

## 10. Capacitor iOS 설정 주입 예시

### 10.1 capacitor.config.json (공통)

```json
{
  "appId": "{{PACKAGE_ID}}",
  "appName": "{{APP_NAME}}",
  "webDir": "www",
  "server": {
    "url": "{{WEB_URL}}",
    "cleartext": true,
    "allowNavigation": [
      "kauth.kakao.com",
      "kapi.kakao.com",
      "accounts.kakao.com",
      "accounts.google.com",
      "www.google.com",
      "nid.naver.com",
      "naver.com",
      "www.facebook.com",
      "facebook.com",
      "appleid.apple.com",
      "apple.com",
      "{{APP_HOST}}"
    ]
  },
  "ios": {
    "contentInset": "never"
  }
}
```

- iOS에서도 동일. `appId`는 Bundle ID로 사용.
- **server.allowNavigation**: 카카오·구글·네이버 등 OAuth URL을 WebView 내에서 로드 (외부 Safari 이탈 방지). 빌드 시 앱 도메인(web_url host)이 `{{APP_HOST}}`에 주입됨.
- **ios.contentInset**: `"never"` — 웹 페이지가 CSS로 Safe Area를 처리하는 방식. 웹 개발자 요청사항(11절)을 적용한 경우 이 설정으로 헤더 겹침 문제 해결됨.

### 10.2 웹 개발자 필수 요청사항 (Safe Area)

iOS WebView 앱에서 상단 고정 헤더가 노치/상태바와 겹치는 문제를 방지하려면, **웹 페이지**에 아래 수정이 필요합니다. 웹 개발자에게 전달할 내용:

#### ① viewport 메타 태그

`<head>` 내 viewport 메타 태그에 **`viewport-fit=cover`** 포함:

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
```

> `viewport-fit=cover`가 없으면 `env(safe-area-inset-top)` 값이 0으로 반환됩니다.

#### ② 상단 고정 헤더 스타일

`position: fixed` 또는 `position: sticky`로 상단에 고정된 헤더에 적용:

```css
/* padding-top으로 노치/상태바 영역 확보 */
padding-top: env(safe-area-inset-top);

/* 헤더 전체 높이에 Safe Area 포함 (테두리·구분선이 내용 아래에 오도록) */
min-height: calc(기존헤더높이 + env(safe-area-inset-top));
```

예: 헤더 높이가 56px인 경우

```css
min-height: calc(56px + env(safe-area-inset-top));
```

#### ③ 요약

- 헤더 요소에 `padding-top: env(safe-area-inset-top)` 적용
- `min-height`에 `env(safe-area-inset-top)`를 더해 헤더 테두리/구분선이 내용 아래에 오도록 조정

#### ④ 테스트

iPhone Safari 또는 Chrome DevTools에서 노치 있는 기기(iPhone 14 Pro 등)로 확인.

---

**contentInset 옵션 참고**: `automatic` | `scrollableAxes` | `never` | `always`. 웹에서 Safe Area를 처리하면 `never` 사용. 앱에서 네이티브 inset을 적용하려면 `always`로 변경 가능.

### 10.3 OAuth/소셜 로그인 인앱 처리 (카카오 등)

**이슈**: 카카오·구글·네이버 등 OAuth 로그인 시 인증 및 로그인 처리가 외부 Safari에서 되어 앱 이탈.

**Android 해결 이력**: `OAuthWebViewClient`가 `shouldOverrideUrlLoading`에서 OAuth URL 감지 시 WebView 내 로드. `OnCreateWindowWebChromeClient`가 `window.open`/`target="_blank"` 시 부모 WebView에 로드.

**iOS 해결 방식**:

| 구분 | Android | iOS |
|------|---------|-----|
| 링크 클릭(리다이렉트) | OAuthWebViewClient.shouldOverrideUrlLoading | `server.allowNavigation` (Capacitor config) |
| window.open / target="_blank" | OnCreateWindowWebChromeClient → 부모 WebView | 기본: Safari로 열림. OAuth가 리다이렉트 방식이면 allowNavigation으로 충분 |

**allowNavigation 도메인** (빌드 템플릿에 포함):
- 카카오: kauth.kakao.com, kapi.kakao.com, accounts.kakao.com
- 구글: accounts.google.com, www.google.com
- 네이버: nid.naver.com, naver.com
- 페이스북: www.facebook.com, facebook.com
- 애플: appleid.apple.com, apple.com
- 앱 도메인: `{{APP_HOST}}` (빌드 시 web_url host 주입)

**참고**: OAuth 제공자가 `window.open`으로 팝업을 사용하는 경우, iOS는 기본적으로 Safari로 열 수 있음. 카카오 등 대부분은 리다이렉트 방식이라 allowNavigation으로 해결됨.

**검증 완료 (2026-03-03)**: iOS 빌드에서 카카오 로그인 인앱 처리 정상 동작 확인.

### 10.4 Info.plist

| 키 | 값 |
|----|-----|
| CFBundleDisplayName | {{APP_NAME}} |
| CFBundleIdentifier | {{BUNDLE_ID}} |

### 10.5 Xcode 프로젝트 (pbxproj)

- PRODUCT_BUNDLE_IDENTIFIER = {{BUNDLE_ID}}
- MARKETING_VERSION = {{VERSION_NAME}}
- CURRENT_PROJECT_VERSION = {{VERSION_CODE}}

---

## 11. 테스트 체크리스트

### Phase 1 완료 시

- [ ] 1단계에서 iOS 선택 가능
- [ ] 2단계에서 Bundle ID 표시·수정 가능
- [ ] 3단계에서 iOS 빌드 요청 가능
- [ ] `npx cap sync ios` 성공
- [ ] iOS 시뮬레이터에서 앱 실행, 웹 URL 로드 확인
- [ ] 앱 아이콘, 앱 이름 표시 확인

### Phase 2 완료 시

- [ ] FCM 토큰 발급
- [ ] `window.onFcmTokenReady(token)` 웹 전달
- [ ] 푸시 수신, 알림 탭 시 URL 로드

### Phase 3 완료 시

- [x] OAuth(카카오 등) 인앱 처리 (2026-03-03 검증 완료)
- [ ] PDF/이미지 다운로드
- [ ] window.open → WebView 내 로드

---

## 12. 참조 문서

| 문서 | 용도 |
|------|------|
| [Capacitor iOS](https://capacitorjs.com/docs/ios) | iOS 플랫폼 추가, 실행 |
| [Capacitor iOS Configuration](https://capacitorjs.com/docs/ios/configuration) | Info.plist, 권한 |
| [Capacitor CLI build](https://capacitorjs.com/docs/cli/commands/build) | `npx cap build ios` 옵션 |
| [Environment Setup](https://capacitorjs.com/docs/getting-started/environment-setup) | Xcode, CocoaPods |
| docs/BUILD_ENVIRONMENT.md | Android 빌드 환경 (iOS 섹션 추가 예정) |
| docs/CAPACITOR_LEARNING.md | iOS 확장 참고 |
| docs/FCM_WEB_DEVELOPER_GUIDE.md | FCM 연동 (Android 기준, iOS 확장) |

---

## 13. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-27 | 최초 작성 (AI 개발용 iOS 빌드 가이드) |
| 2026-03-03 | 10.1 capacitor.config에 ios.contentInset: "never" 추가. 10.2 웹 개발자 필수 요청사항(Safe Area) 섹션 추가. |
| 2026-03-03 | 10.3 OAuth/소셜 로그인 인앱 처리(카카오 등) 섹션 추가. server.allowNavigation으로 iOS Safari 이탈 방지. 카카오 로그인 검증 완료. |
