# 웹뷰 앱 빌드 — Android APK 빌드 환경

> **목적**: Phase 5 Capacitor Android 빌드에 필요한 도구 설치 및 환경 설정  
> **참조**: DEV_SPEC.md 7. 빌드 프로세스

---

## 1. 필요 사항 요약

| 도구 | 용도 | 필수 |
|------|------|------|
| **Java JDK 17** | Gradle, keytool | ✅ |
| **Android SDK** | Android APK 빌드 | ✅ |
| **keytool** | Keystore 생성 (JDK 포함) | ✅ |
| **Node.js** | npm, Capacitor CLI | ✅ (프론트엔드에 이미 설치) |

---

## 2. macOS 설치 (Homebrew)

### 2.1 사전 요구사항

- **Homebrew** 설치: https://brew.sh
- **Xcode Command Line Tools**: `xcode-select --install`

### 2.2 Java JDK 17 설치

```bash
# OpenJDK 17 설치
brew install openjdk@17

# PATH 설정 (쉘 설정 파일에 추가)
# Apple Silicon (M1/M2/M3):
echo 'export PATH="/opt/homebrew/opt/openjdk@17/bin:$PATH"' >> ~/.zshrc
# Intel Mac:
echo 'export PATH="/usr/local/opt/openjdk@17/bin:$PATH"' >> ~/.zshrc

source ~/.zshrc
```

**검증**:
```bash
java -version
# openjdk version "17.x.x" ...

keytool -version
# keytool (OpenJDK) ...
```

### 2.3 Android SDK 설치

**방법 A: Android Studio (권장, 가장 간단)**

1. [Android Studio 다운로드](https://developer.android.com/studio)
2. 설치 후 실행 → SDK Manager에서 **Android SDK** 설치
3. 기본 경로: `~/Library/Android/sdk`

**방법 B: 커맨드라인 도구만**

```bash
# Android command-line tools 설치
brew install --cask android-commandlinetools

# SDK 경로
# Apple Silicon: /opt/homebrew/share/android-commandlinetools
# Intel Mac: /usr/local/share/android-commandlinetools

# 필수 패키지가 자동 설치되지 않았다면 sdkmanager로 설치
# (대부분 brew cask가 platforms, build-tools를 포함)
```

### 2.4 환경 변수 설정

`~/.zshrc` 또는 `~/.bash_profile`에 추가:

```bash
# Java
export JAVA_HOME=$(/usr/libexec/java_home -v 17 2>/dev/null || echo "/opt/homebrew/opt/openjdk@17")
export PATH="$JAVA_HOME/bin:$PATH"

# Android SDK (Android Studio 설치 시)
export ANDROID_HOME=$HOME/Library/Android/sdk
export PATH=$PATH:$ANDROID_HOME/platform-tools
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin
```

**Android Studio 미설치 시** (commandlinetools만 사용):
```bash
# Apple Silicon
export ANDROID_HOME=/opt/homebrew/share/android-commandlinetools
# Intel Mac
export ANDROID_HOME=/usr/local/share/android-commandlinetools
```

> **참고**: `backend/queue-with-env.sh`를 사용하면 위 환경 변수를 자동으로 로드합니다.

적용: `source ~/.zshrc`

### 2.5 빌드 실행 방식

"빌드 시작" 클릭 시 **동기 실행** — 별도 Queue 워커 불필요.

---

## 3. 설치 검증

```bash
# Java
java -version
keytool -version

# Android SDK
echo $ANDROID_HOME
ls $ANDROID_HOME/platforms
ls $ANDROID_HOME/build-tools

# Node (프로젝트에 이미 있음)
node -v
npm -v
```

---

## 4. 빌드 테스트

### 4.1 테스트 절차

1. **1단계** (`/`): 웹 URL, 앱 아이콘 입력 → "다음 (2단계)"
2. **2단계** (`/step2`): 자동 생성값 확인·수정 → "다음 (3단계)"
3. **3단계** (`/step3`): 시뮬레이션 확인 → **"빌드 시작"** 클릭
4. **2~5분 대기** — "빌드 중... (2~5분 소요)" 표시됨
5. 완료 시 결과 페이지로 이동 → APK 다운로드 버튼 표시

### 4.2 재테스트 방법

- **이전 빌드가 대기 중이었던 경우**: "새 빌드 시작" 버튼 클릭 → 1단계로 이동 → 위 절차 1~5 반복
- **처음부터 다시**: `http://localhost/` 접속 → 1단계부터 진행

**실패 시**: 빌드 결과 페이지에 에러 메시지 표시.

### 4.3 앱 아이콘 규격 (Android 공식)

1단계에서 업로드하는 앱 아이콘은 빌드 시 **자동 리사이징**됩니다.

| 용도 | mdpi | hdpi | xhdpi | xxhdpi | xxxhdpi |
|------|------|------|-------|--------|---------|
| Legacy (ic_launcher) | 48px | 72px | 96px | 144px | 192px |
| Adaptive foreground (API 26+) | 108px | 162px | 216px | 324px | 432px |

**권장 업로드**: 512×512 px 이상 (1024×1024 권장), PNG/JPEG/WebP, 정사각형

> 참조: [Android Adaptive Icons](https://developer.android.com/develop/ui/views/launch/icon_design_adaptive), [Create app icons](https://developer.android.com/studio/write/create-app-icons)

---

## 5. 문제 해결

| 증상 | 원인 | 해결 |
|------|------|------|
| `java: command not found` | Java 미설치 또는 PATH 미설정 | 2.2 절차 수행 |
| `keytool: command not found` | JDK 미설치 (JRE만 설치됨) | `brew install openjdk@17` |
| `ANDROID_HOME not set` | 환경 변수 미설정 | 2.4 절차 수행 |
| `SDK location not found` | Android SDK 미설치 | 2.3 절차 수행 |
| `Failed to find target with hash string 'android-34'` | SDK 플랫폼 미설치 | SDK Manager에서 Android 14 (API 34) 설치 |
| `Execution failed for task ':app:mergeReleaseResources'` | 리소스 충돌 | 템플릿 아이콘 형식 확인 (PNG) |

---

## 6. Keystore 검증

빌드 완료 시 `release.keystore`가 생성되며, 결과 페이지에서 다운로드할 수 있습니다.

### 6.1 Keystore 정보 확인

```bash
# 다운로드한 keystore 파일이 있는 경로에서 실행
keytool -list -v -keystore release.keystore -storepass webview123
```

**출력 예시**:
```
Alias name: webview-build
Creation date: ...
Entry type: PrivateKeyEntry
Certificate chain length: 1
Certificate[1]:
Owner: CN=Webview, OU=Build, O=Webview, L=Local, ST=Local, C=US
Issuer: CN=Webview, OU=Build, O=Webview, L=Local, ST=Local, C=US
Serial number: ...
Valid from: ... until: ...
```

### 6.2 간단 확인 (alias 목록만)

```bash
keytool -list -keystore release.keystore -storepass webview123
```

### 6.3 APK 서명 검증

APK가 해당 Keystore로 서명되었는지 확인:

```bash
# APK 서명 정보 확인
apksigner verify --print-certs app-release.apk
# 또는 (구버전)
jarsigner -verify -verbose -certs app-release.apk
```

### 6.4 Keystore 기본값 (자동 생성 시)

| 항목 | 값 |
|------|-----|
| storePassword | webview123 |
| keyPassword | webview123 |
| keyAlias | webview-build |

> **주의**: 프로덕션 배포 시 별도 Keystore를 생성하고 비밀번호를 안전하게 관리하세요.

---

## 7. 참조 문서

| 문서 | 용도 |
|------|------|
| ENVIRONMENT_SETUP.md | nginx, 로컬 실행 방식 |
| DEV_SPEC.md 7장 | 빌드 프로세스 상세 |
| [Capacitor Android](https://capacitorjs.com/docs/android) | Capacitor 공식 문서 |
| [Android 개발자](https://developer.android.com/studio/command-line) | SDK command-line 도구 |

---

## 8. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-27 | 최초 작성 (Java 17, Android SDK, keytool) |
| 2026-02-27 | Keystore 검증 방법 추가, 앱 아이콘 ic_launcher_foreground 적용 |
| 2026-02-27 | 앱 아이콘 규격 섹션 추가 (Android 공식), PHP GD 리사이징, 업로드 경로(public/private) 해석, 3단계 아이콘 미리보기 |
