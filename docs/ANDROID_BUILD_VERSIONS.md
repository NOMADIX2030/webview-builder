# Android 빌드 버전 (compileSdk, AGP) — 조사 결과

> **작성일**: 2026년 3월 6일

---

## 1. 용어 정리

| 용어 | 의미 | 현재 프로젝트 |
|------|------|---------------|
| **compileSdk** | 컴파일 시 참조하는 Android API 버전. 앱이 사용할 수 있는 API 범위 결정. | 34 (Android 14) |
| **targetSdk** | 앱이 타깃하는 Android 버전. 런타임 동작·권한·제한 적용. | 34 |
| **minSdk** | 앱이 설치 가능한 최소 Android 버전. | 22 (Android 5.1+) |
| **AGP (Android Gradle Plugin)** | Gradle에서 Android 앱을 빌드하는 플러그인. `com.android.tools.build:gradle` | 8.2.1 |

---

## 2. 각 항목 설명

### 2.1 compileSdk 34

- **의미**: 빌드 시 Android 14 (API 34) API를 기준으로 컴파일
- **역할**: 새 API 사용 가능 여부 결정. `targetSdk`와는 별개.
- **참고**: compileSdk를 올려도 targetSdk를 그대로 두면, 새 API는 사용할 수 있지만 런타임 동작은 기존대로 유지 가능.

### 2.2 AGP 8.2.1 (Android Gradle Plugin)

- **의미**: Gradle 빌드 시스템에서 Android 앱을 빌드하는 플러그인
- **역할**: `./gradlew assembleRelease` 실행 시 사용
- **호환**: AGP 8.2.1은 compileSdk 34를 **권장 최대**로 지원

---

## 3. 왜 최신 버전을 사용하지 않았는가?

### 3.1 Capacitor 6 공식 요구사항

**Capacitor 6** 업데이트 가이드([Updating to 6.0](https://capacitorjs.com/docs/updating/6-0))에서 아래를 명시:

| 항목 | Capacitor 6 요구값 |
|------|-------------------|
| compileSdkVersion | 34 |
| targetSdkVersion | 34 |
| Android Gradle Plugin | **8.2.1** |
| Gradle Wrapper | 8.2.1 |
| coreSplashScreenVersion | **1.0.1** |
| Android Studio | Hedgehog (2023.1.1) 이상 |

### 3.2 프로젝트가 이 버전을 쓰는 이유

1. **Capacitor 6와의 호환**: 프로젝트는 Capacitor 6.x를 사용하며, 공식 가이드가 위 버전을 지정함.
2. **안정성**: Capacitor 6가 검증한 조합이라 플러그인·의존성과의 충돌 가능성이 낮음.
3. **의존성 제약**: `core-splashscreen:1.2.0`은 compileSdk 35, AGP 8.6.0+를 요구하지만, Capacitor 6는 `coreSplashScreenVersion = '1.0.1'`을 권장함.

### 3.3 최신 버전으로 올리면?

| 변경 | 필요 작업 | 리스크 |
|------|-----------|--------|
| compileSdk 35 | variables.gradle 수정 | 일부 의존성·플러그인 호환성 검증 필요 |
| AGP 8.6.0+ | build.gradle, gradle-wrapper.properties 수정 | Capacitor 6 공식 지원 범위 밖, 빌드·플러그인 이슈 가능 |
| core-splashscreen 1.2.0 | 위 두 가지 선행 필요 | 현재는 1.0.1 사용으로 해결 |

---

## 4. core-splashscreen 버전 이슈 (2026-03-06)

### 4.1 발생한 에러

```
Dependency 'androidx.core:core-splashscreen:1.2.0' requires:
  - compileSdk 35 or later (현재: 34)
  - Android Gradle plugin 8.6.0 or higher (현재: 8.2.1)
```

### 4.2 적용한 조치

- `coreSplashScreenVersion`을 **1.2.0 → 1.0.1**로 변경
- Capacitor 6 공식 가이드와 동일한 버전으로 맞춤

### 4.3 1.0.1 vs 1.2.0

| 항목 | 1.0.1 | 1.2.0 |
|------|-------|-------|
| compileSdk | 34 호환 | 35 필요 |
| AGP | 8.2.1 호환 | 8.6.0+ 필요 |
| Theme.SplashScreen | 지원 | 지원 |
| Capacitor 6 권장 | 예 | 아니오 |

---

## 5. 참조 문서

| 문서 | URL |
|------|-----|
| Capacitor 6 업데이트 가이드 | https://capacitorjs.com/docs/updating/6-0 |
| Capacitor 6 환경 설정 | https://capacitorjs.com/docs/v6/getting-started/environment-setup |
| Android compileSdk vs targetSdk | https://developer.android.com/build |
| AGP 릴리스 노트 | https://developer.android.com/studio/releases/gradle-plugin |
