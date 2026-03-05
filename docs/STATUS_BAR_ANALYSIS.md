# Android 상태바(상단 트레이) 분석 — 사실 파악

> **작성일**: 2026년 3월 6일  
> **목적**: 현재 "페이크 숨김" 상태의 원인 파악 및 완전 숨김 가능 여부 조사  
> **적용일**: 2026년 3월 6일 — 정상 복원 후 완전 숨김 적용 완료

---

## 1. 현재 상황 정리

### 1.1 사용자 관찰

- **"화이트로 안보이게 만 처리한거같아"**: 상태바를 숨긴 게 아니라, 배경을 흰색/투명으로 해서 안 보이게 한 것처럼 보임
- **"검은색 알림아이콘은 보이거는"**: 배터리·시간·알림 아이콘이 여전히 보임
- **"페이크 숨기는 척만한 상태"**: 실제로는 숨기지 않고, 시각적으로만 숨긴 것처럼 보이게 한 상태

### 1.2 결론: `hide()`가 제대로 동작하지 않는 것으로 추정

상태바가 **진짜로 숨겨졌다면** 아이콘(배터리, 시간, 알림 등)도 함께 사라져야 한다.  
아이콘이 보인다면 **상태바는 그대로 있고**, 배경만 투명/흰색으로 처리된 상태다.

---

## 2. 현재 코드 분석

### 2.1 MainActivity.java (39~46행)

```java
// Edge-to-edge: 헤더가 상단 엣지까지 붙음. 상태바(시간·배터리) 숨김.
WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
WindowInsetsControllerCompat compat = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
if (compat != null) {
    compat.hide(WindowInsetsCompat.Type.statusBars());
}
```

- `hide(WindowInsetsCompat.Type.statusBars())` 호출은 있음
- Android 공식 문서 기준으로는 이 호출만으로 상태바가 숨겨져야 함

### 2.2 styles.xml

```xml
<item name="android:statusBarColor">@android:color/transparent</item>
<item name="android:navigationBarColor">@android:color/transparent</item>
<item name="android:windowDrawsSystemBarBackgrounds">true</item>
```

- 상태바 배경을 **투명**으로 설정
- 웹 콘텐츠가 흰색이면 상태바 영역도 흰색처럼 보임
- 아이콘은 시스템 기본 색(밝은 배경에서는 검정)이라 그대로 보임

### 2.3 동작 추정

| 항목 | 상태 |
|------|------|
| `hide()` | 호출은 되나, 실제로는 상태바가 숨겨지지 않음(또는 곧 다시 나타남) |
| `statusBarColor` | 투명 → 웹 배경(흰색)이 비침 |
| 아이콘 | 검정색으로 보임 → “페이크 숨김”처럼 느껴짐 |

---

## 3. Android 공식 문서 요약

### 3.1 상태바 완전 숨김 — 권장 방식

출처: [Hide system bars for immersive mode | Android Developers](https://developer.android.com/develop/ui/views/layout/immersive)

```java
WindowInsetsControllerCompat windowInsetsController =
    WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());

// 1) 숨김 동작 설정 (필수)
windowInsetsController.setSystemBarsBehavior(
    WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);

// 2) 상태바 숨김
windowInsetsController.hide(WindowInsetsCompat.Type.statusBars());
```

- `setSystemBarsBehavior()`를 먼저 호출해야 숨김이 안정적으로 동작한다고 명시됨
- 현재 코드에는 `setSystemBarsBehavior()` 호출이 없음

### 3.2 `setSystemBarsBehavior` 역할

| 값 | 의미 |
|----|------|
| `BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE` | 스와이프 시 일시적으로만 표시, 곧 다시 숨김 |
| `BEHAVIOR_SHOW_BARS_BY_SWIPE` | 스와이프 시 표시 후 유지 |
| `BEHAVIOR_SHOW_BARS_BY_TOUCH` | 터치 시 표시 |

- `BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE`가 immersive 모드에 적합
- 이 값을 설정하지 않으면 기본 동작에 따라 숨김이 유지되지 않을 수 있음

### 3.3 호출 시점

출처: [Hide the status bar | Android Developers](https://developer.android.com/training/system-ui/status)

- `onCreate()`에서만 숨기면, 홈으로 갔다가 돌아올 때 상태바가 다시 나타날 수 있음
- **`onResume()` 또는 `onWindowFocusChanged()`에서 다시 숨기는 것이 권장됨**

현재 코드는 `onCreate()`에서만 호출하고, `onResume()`에서는 재호출하지 않음.

---

## 4. Capacitor Status Bar 플러그인

### 4.1 구현 (소스코드)

출처: [capacitor-plugins/StatusBar.java](https://github.com/ionic-team/capacitor-plugins/blob/main/status-bar/android/src/main/java/com/capacitorjs/plugins/statusbar/StatusBar.java)

```java
public void hide() {
    View decorView = activity.getWindow().getDecorView();
    WindowInsetsControllerCompat windowInsetsControllerCompat = 
        WindowCompat.getInsetsController(activity.getWindow(), decorView);
    windowInsetsControllerCompat.hide(WindowInsetsCompat.Type.statusBars());
    // ...
}
```

- `hide()`만 호출하고 `setSystemBarsBehavior()`는 호출하지 않음
- Capacitor 플러그인도 Android 문서의 “권장 패턴”과 완전히 일치하지는 않음

### 4.2 Capacitor 문서

- `StatusBar.hide()`는 Android 16+에서도 동작한다고 명시
- `backgroundColor`, `overlaysWebView`는 Android 15+에서 제한됨

---

## 5. 원인 정리

### 5.1 가능한 원인

| 원인 | 설명 |
|------|------|
| `setSystemBarsBehavior` 미설정 | 기본 동작으로 인해 숨김이 유지되지 않을 수 있음 |
| `onResume`에서 재호출 없음 | 홈/다른 앱 전환 후 돌아오면 상태바가 다시 나타날 수 있음 |
| BridgeActivity/Capacitor 간섭 | `super.onCreate()` 이후 WebView/레이아웃 설정이 상태바를 다시 보이게 할 수 있음 |
| 호출 순서 | `setDecorFitsSystemWindows(false)`와 `hide()` 순서·시점이 부적절할 수 있음 |

### 5.2 “페이크 숨김”이 되는 이유

- `statusBarColor = transparent`로 배경만 투명
- 웹 배경이 흰색이면 상태바 영역도 흰색처럼 보임
- 아이콘은 시스템 색(검정)이라 그대로 보임
- 결과적으로 “숨긴 것처럼 보이지만, 실제로는 상태바가 남아 있는” 상태

---

## 6. 완전 숨김 가능 여부

### 6.1 결론: **가능함**

- Android 공식 문서: `WindowInsetsControllerCompat.hide()`로 상태바를 숨길 수 있음
- Capacitor 문서: `StatusBar.hide()`가 Android 16+에서도 동작
- 문제는 **호출 방식·시점**일 가능성이 큼

### 6.2 권장 수정 사항

1. **`setSystemBarsBehavior()` 추가**
   ```java
   compat.setSystemBarsBehavior(
       WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
   compat.hide(WindowInsetsCompat.Type.statusBars());
   ```

2. **`onResume()`에서 재호출**
   ```java
   @Override
   public void onResume() {
       super.onResume();
       reapplyStatusBarHidden();
       // ...
   }
   ```

3. **`onWindowFocusChanged()`에서 재호출** (선택)
   - 포커스 복귀 시에도 숨김을 유지하려면 여기서도 호출

4. **API 30 미만 대응**
   - `View.SYSTEM_UI_FLAG_FULLSCREEN` 등 레거시 플래그로 폴백

---

## 7. 적용 이력

### 7.1 2026-03-06: 기본 상태바 복원

| 파일 | 변경 |
|------|------|
| **MainActivity.java** | `applyStatusBarHidden()` 제거, `setDecorFitsSystemWindows(true)`로 기본 상태바 표시 |
| **styles.xml** | `statusBarColor`/`navigationBarColor`: `@android:color/black` 유지 (시스템 기본 스타일) |

### 7.2 현재 동작

- **상태바(배터리·시간·알림 아이콘) 정상 표시** — Android 시스템 기본 상단 트레이
- `WindowCompat.setDecorFitsSystemWindows(getWindow(), true)`: 콘텐츠가 상태바 아래에 배치

---

## 8. 참고 문서

| 문서 | URL |
|------|-----|
| Hide the status bar | https://developer.android.com/training/system-ui/status |
| Hide system bars for immersive mode | https://developer.android.com/develop/ui/views/layout/immersive |
| Capacitor Status Bar API | https://capacitorjs.com/docs/apis/status-bar |
| Capacitor StatusBar Android 소스 | https://github.com/ionic-team/capacitor-plugins/blob/main/status-bar/android/ |
