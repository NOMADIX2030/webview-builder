# 스플래시 화면 분석

> **작성일**: 2026년 3월 6일

---

## 질문1. 현재 스플래시가 제대로 동작하는지?

### 1.1 앱 첫 로딩 시 출력 여부

| 항목 | 상태 |
|------|------|
| **표시 시점** | ✅ 앱 첫 실행 시(Launch)에만 표시됨 |
| **구현** | MainActivity가 `AppTheme.NoActionBarLaunch` 사용 → `Theme.SplashScreen` + `@drawable/splash` |
| **라이브러리** | `androidx.core:core-splashscreen:1.0.1` |

- `Launch` 테마는 앱 시작 시 한 번만 적용되므로, **첫 로딩 시에만** 스플래시가 표시됨.
- 이후 WebView 로드 완료 시 일반 테마로 전환되는 구조.

### 1.2 사용자 업로드 스플래시 적용 (2026-03-06)

| 항목 | 상태 |
|------|------|
| **1단계 입력** | ✅ 스플래시 이미지 업로드 가능 (선택) |
| **DB 저장** | ✅ `splash_image_path` 저장됨 |
| **빌드 시 적용** | ✅ **injectSplashConfig(), copySplash()** 적용됨 |

- **업로드 있음**: `AppTheme.NoActionBarLaunch` + `copySplash()` → drawable에 복사
- **업로드 없음**: `AppTheme.NoActionBar` → 즉시 앱 실행 (스플래시 없음)

### 1.3 결론

- 스플래시는 **앱 첫 로딩 시에만** 표시됨. ✅
- 사용자가 업로드한 스플래시는 **빌드 시 반영됨**. ✅

---

## 질문2. 최적 사이즈

### 2.1 Capacitor / @capacitor/assets 공식

| 플랫폼 | 최소 사이즈 | 비고 |
|--------|-------------|------|
| **Android** | **2732 × 2732 px** | @capacitor/assets generate 시 |
| **iOS** | **2732 × 2732 px** | @capacitor/assets generate 시 |
| **공통** | **2732 × 2732 px** | Capacitor 문서 권장 |

출처: [Capacitor Splash Screens and Icons](https://capacitorjs.com/docs/guides/splash-screens-and-icons)

### 2.2 Android 상세

| Android 버전 | 방식 | 권장 사이즈 |
|--------------|------|-------------|
| **Android 12+** | 아이콘 + 배경색 (풀스크린 이미지 불가) | 앱 아이콘 사용, 배경색만 설정 |
| **Android 11 이하** | 풀스크린 drawable | density별 또는 **2732×2732** 원본 |

**Density별 (레거시)**:
| Density | px |
|---------|-----|
| mdpi | 320 × 480 |
| hdpi | 480 × 800 |
| xhdpi | 720 × 1280 |
| xxhdpi | 960 × 1600 |
| xxxhdpi | 1280 × 1920 |

- `@capacitor/assets` 사용 시 **2732×2732** 원본 하나로 모든 density 생성.

### 2.3 iOS 상세

| 항목 | 값 |
|------|-----|
| **최소** | 2732 × 2732 px |
| **제한** | iOS 14+ Launch Image 25MB 제한 (2732×2732×4 ≈ 30MB 주의) |
| **권장** | 2732×2732 PNG, 압축 품질 조절로 25MB 이하 유지 |

### 2.4 입력 인터페이스 권장 문구

```
Android: 2732×2732 px 이상 권장 (정사각형)
iOS: 2732×2732 px 이상 권장 (정사각형)
공통: 2732×2732 px (Capacitor @capacitor/assets 기준)
```

---

## 4. @capacitor/splash-screen 플러그인 적용 (2026-03-06)

### 4.1 적용 내용

| 항목 | 설정 |
|------|------|
| **플러그인** | @capacitor/splash-screen ^6.0.1 |
| **launchAutoHide** | false (웹 로딩 완료 시 수동 hide) |
| **launchFadeOutDuration** | 300 ms (자연스러운 페이드아웃) |
| **backgroundColor** | #ffffff |
| **androidScaleType** | CENTER_CROP |

### 4.2 권장값 (자연스럽고 부드러운 UX)

| 옵션 | 권장값 | 설명 |
|------|--------|------|
| **launchAutoHide** | false | 실제 로딩 완료 시점에 hide |
| **launchFadeOutDuration** | 200~400 ms | 300 ms: 눈에 거슬리지 않는 페이드 |
| **backgroundColor** | #ffffff | 스플래시 이미지 배경과 일치 |
| **androidScaleType** | CENTER_CROP | 화면 비율에 맞게 꽉 채움 |

### 4.3 동작 방식

- **Android**: OAuthWebViewClient `onPageFinished`에서 첫 페이지 로드 시 `SplashScreen.hide({fadeOutDuration:300})` 주입
- **iOS**: launchAutoHide: false 시 웹에서 `Capacitor.Plugins.SplashScreen.hide()` 호출 필요 (또는 launchAutoHide: true + launchShowDuration으로 폴백)

### 4.4 조건부 동작 (2026-03-06)

| 조건 | 동작 |
|------|------|
| **스플래시 업로드 있음** | Launch 테마 사용 → 스플래시 표시 → 웹 로드 완료 시 hide |
| **스플래시 업로드 없음** | 일반 테마 사용 → 즉시 앱 실행 (스플래시 없음) |

- **Android**: `injectSplashConfig()`에서 `splash_image_path` 유무에 따라 `AppTheme.NoActionBarLaunch` / `AppTheme.NoActionBar` 선택
- **iOS**: 업로드 있으면 Splash.imageset에 복사, 없으면 흰색 빈 이미지로 즉시 전환
- **OAuthWebViewClient**: `onPageFinished`에서 `SplashScreen.hide()` 호출 (스플래시 업로드 시에만 Launch 테마 사용 → hide 의미 있음)

### 4.5 웹 개발자용 (선택)

웹사이트에서 스플래시를 직접 제어하려면:

```javascript
if (window.Capacitor?.Plugins?.SplashScreen) {
  window.Capacitor.Plugins.SplashScreen.hide({ fadeOutDuration: 300 });
}
```

> 스플래시를 업로드한 빌드에서만 위 코드가 의미 있음. 미업로드 시 스플래시가 없으므로 호출해도 무방.

---

## 5. 스플래시가 너무 빨리 사라질 때 — 대안 (2026-03-06)

### 5.1 문제

웹 페이지가 빨리 로드되면 스플래시가 거의 보이지 않고 바로 웹뷰로 전환됨.

### 5.2 업계에서 쓰는 패턴

| 패턴 | 설명 | 출처 |
|------|------|------|
| **최소 표시 시간** | 2~5초 권장 (애니메이션 시 1초 이하) | Android 공식, WebViewGold, Medium |
| **max(최소시간, 페이지로드)** | 페이지 로드 완료와 최소 시간 중 **더 긴 쪽**에 hide | Stack Overflow, Expo |
| **launchShowDuration** | Capacitor: 고정 시간 후 자동 hide (launchAutoHide: true) | Capacitor 공식 문서 |

### 5.3 대안 1: Capacitor `launchShowDuration` (가장 단순)

`capacitor.config.json`:

```json
"SplashScreen": {
  "launchAutoHide": true,
  "launchShowDuration": 2500,
  "launchFadeOutDuration": 300
}
```

- **장점**: 설정만으로 2.5초 고정 표시
- **단점**: 페이지가 5초 걸려도 2.5초에 hide → 웹뷰가 준비되기 전에 사라질 수 있음

### 5.4 대안 2: 최소 표시 시간 + 페이지 로드 (권장)

**로직**: `hide 시점 = max(최소 2초, 페이지 로드 완료 시점)`

- 앱 시작 시 `launchTime = Date.now()` 기록
- `onPageFinished`에서: `elapsed = Date.now() - launchTime`
- `remaining = Math.max(0, 2000 - elapsed)` → `setTimeout(hide, remaining)`

**구현 위치**: OAuthWebViewClient에서 주입하는 JS를 수정하거나, 네이티브에서 `postDelayed`로 최소 시간 보장.

**참고**:
- [Stack Overflow: Show splash when page loads, but at least for x seconds](https://stackoverflow.com/questions/36561936/show-splash-screen-when-page-loads-but-at-least-for-x-seconds)
- [Stack Overflow: Minimum time for loading screen](https://stackoverflow.com/questions/67605126/minimum-time-for-loading-screen)
- [Expo SplashScreen](https://docs.expo.dev/versions/latest/sdk/splash-screen/): `preventAutoHideAsync()` + 수동 hide

### 5.5 대안 3: Spinner 표시

`showSpinner: true`로 로딩 인디케이터를 보여 사용자에게 로딩 중임을 전달.

```json
"SplashScreen": {
  "showSpinner": true,
  "spinnerColor": "#999999"
}
```

### 5.6 권장 조합

| 항목 | 권장값 |
|------|--------|
| 최소 표시 시간 | 2~3초 |
| 페이드아웃 | 200~400ms |
| 패턴 | max(최소 2초, 페이지 로드 완료) |

### 5.7 적용 완료 (2026-03-06)

| 파일 | 변경 |
|------|------|
| **MainActivity.java** | `splashLaunchTime` 추가 (onCreate 시점 기록) |
| **OAuthWebViewClient.java** | `postDelayed`로 max(2초, 페이지로드) 적용, `SplashScreen.hide()` 호출 |

### 5.8 Android 12+ 제한 및 대안 (2026-03-06)

- **Android 12+**: OS가 앱 아이콘 스플래시 0.2~0.5초 강제 표시. 커스텀 풀스크린 제어 불가.
- **대안**: 네이티브 ImageView 오버레이 (앱 첫 구동 시에만) — 미구현. 현재는 스플래시 미사용 시 즉시 실행.

---

## 6. 참고

| 문서 | URL |
|------|-----|
| Capacitor Splash Screen API | https://capacitorjs.com/docs/apis/splash-screen |
| Capacitor Splash Screens | https://capacitorjs.com/docs/guides/splash-screens-and-icons |
| Android SplashScreen API | https://developer.android.com/develop/ui/views/launch/splash-screen |
| @capacitor/assets | https://github.com/ionic-team/capacitor-assets |
| Stack Overflow: min duration + page load | https://stackoverflow.com/questions/36561936/show-splash-screen-when-page-loads-but-at-least-for-x-seconds |
