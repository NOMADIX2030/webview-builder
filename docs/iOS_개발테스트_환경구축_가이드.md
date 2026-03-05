# iOS 개발 테스트 환경구축 가이드

> Apple 개발자 계정 가입부터 Xcode 시뮬레이터 및 실기기 테스트까지 전체 과정 정리.
>
> **환경**: Mac mini (Apple Silicon) + Xcode + Capacitor 6.x  
> **목표**: 웹뷰 iOS 앱 빌드 및 동작 테스트

---

## 1. Apple Developer 계정 가입

### 1.1 계정 종류 선택

| 종류 | 비용 | 특징 |
|---|---|---|
| **무료 계정** | 무료 | 시뮬레이터 테스트 가능, 실기기 설치 7일 제한, App Store 배포 불가 |
| **개인 유료 계정** | $99/년 | 실기기 무제한, App Store 배포 가능 |
| **기업 계정** | $299/년 | 팀 배포, 사내 배포 가능 |

> 웹뷰 앱 개발 테스트 목적이라면 **무료 계정으로 시작** 가능.
> App Store 배포가 필요하면 **개인 유료 계정($99/년)** 필요.

### 1.2 Apple ID 생성 (없는 경우)

1. [appleid.apple.com](https://appleid.apple.com) 접속
2. **Apple ID 만들기** 클릭
3. 이름, 이메일, 비밀번호 입력
4. 이메일 인증 완료

### 1.3 Apple Developer 프로그램 등록 (유료의 경우)

1. [developer.apple.com/programs](https://developer.apple.com/programs) 접속
2. **Enroll** 클릭
3. 개인(Individual) 또는 조직(Organization) 선택
4. 결제 정보 입력 ($99/년)
5. **처리 시간**: 즉시~최대 48시간 (신규 가입 시 검토 시간 소요)

> 무료 계정은 별도 등록 없이 Xcode 로그인만 하면 됩니다.

---

## 2. Mac 환경 구축

### 2.1 Xcode 설치

```bash
# App Store에서 설치 (권장)
# App Store → 검색 → "Xcode" → 설치
# 용량: 약 15GB, 설치 시간: 30분~1시간
```

또는 [developer.apple.com/xcode](https://developer.apple.com/xcode)에서 직접 다운로드.

설치 후 초기 설정:

```bash
# Xcode Command Line Tools 설치
xcode-select --install

# 설치 확인
xcode-select -p
# 출력: /Applications/Xcode.app/Contents/Developer
```

### 2.2 Xcode에 Apple 계정 연결

1. Xcode 실행
2. 상단 메뉴 **Xcode → Settings → Accounts**
3. 좌하단 **+** 클릭 → **Apple ID** 선택
4. Apple ID 로그인

### 2.3 Node.js 확인

```bash
node -v    # v18 이상 권장
npm -v
```

없으면 설치:

```bash
brew install node
```

### 2.4 CocoaPods 설치 (Capacitor iOS 필수)

```bash
sudo gem install cocoapods

# 설치 확인
pod --version
```

---

## 3. iOS 빌드 생성

### 3.1 웹뷰 빌더에서 iOS 빌드 시작

1. `http://localhost/build/step1` 접속
2. **플랫폼**: iOS 또는 Android + iOS 선택
3. 앱 아이콘, 앱 이름, Bundle ID 입력
   - Bundle ID 형식: `com.회사명.앱이름` (예: `com.optiflow.app`)
   - **한 번 정하면 변경 불가** — 신중히 결정
4. 빌드 시작

### 3.2 빌드 결과

빌드 완료 시 `.ipa` 파일 생성 (실기기 설치용).
시뮬레이터 테스트는 빌드 소스 디렉토리에서 직접 Xcode 실행.

---

## 4. Xcode 시뮬레이터 테스트

### 4.1 빌드 프로젝트 Xcode에서 열기

```bash
# 빌드된 프로젝트 경로 예시
open /path/to/build/project/ios/App/App.xcworkspace
```

> `.xcodeproj`가 아닌 **`.xcworkspace`** 파일을 열어야 합니다 (CocoaPods 의존성 포함).

### 4.2 시뮬레이터 선택

Xcode 상단 기기 선택 드롭다운에서 원하는 시뮬레이터 선택:

```
iPhone 16 Pro (추천)
iPhone 15
iPhone SE (3rd generation)  ← 소형 기기 테스트
iPad Pro 13-inch            ← 태블릿 테스트
```

### 4.3 빌드 및 실행

```
Xcode 상단 ▶ (Run) 버튼 클릭
단축키: Cmd + R
```

처음 실행 시 빌드 시간 약 2~5분 소요.

### 4.4 시뮬레이터에서 확인 가능한 항목

| 항목 | 확인 방법 |
|---|---|
| 웹뷰 렌더링 | 시뮬레이터에서 직접 확인 |
| 스플래시 화면 | 앱 시작 시 자동 표시 |
| 상태바 색상 | UI 직접 확인 |
| 카카오 로그인 | 시뮬레이터 내 Safari로 처리 |
| Universal Links | `xcrun simctl openurl` 명령 |
| 뒤로가기 제스처 | 스와이프 제스처 테스트 |

---

## 5. 실기기 테스트 (iPhone 있는 경우)

### 5.1 기기 등록 (유료 계정)

1. iPhone을 Mac에 USB 연결
2. Xcode → **Window → Devices and Simulators**
3. 연결된 기기 확인 → **Trust** 허용
4. [developer.apple.com/account](https://developer.apple.com/account) → **Certificates, IDs & Profiles** → Devices에 자동 등록

### 5.2 무료 계정 실기기 테스트

무료 계정도 실기기 테스트 가능 (7일 제한):

1. Xcode에서 기기 선택
2. **Signing & Capabilities** → Team을 본인 Apple ID 선택
3. `Cmd + R` 로 직접 기기에 설치
4. iPhone에서 **설정 → 일반 → VPN 및 기기 관리** → 개발자 앱 신뢰

### 5.3 유료 계정 실기기 테스트 (무제한)

```bash
# Xcode에서 바로 실기기에 빌드 & 설치
# 기기 선택 후 Cmd+R
```

### 5.4 TestFlight (외부 테스터 배포)

유료 계정 보유 시 TestFlight로 외부 테스터에게 배포:

1. Xcode → **Product → Archive**
2. **Distribute App → TestFlight**
3. App Store Connect에서 테스터 초대
4. 테스터 iPhone에 TestFlight 앱 설치 → 앱 수신

---

## 6. 실시간 로그 확인 (Android의 logcat 대응)

### 6.1 Xcode Console (기본)

Xcode 실행 중 하단 **Console** 패널에서 실시간 로그 확인.

### 6.2 idevicesyslog (터미널)

```bash
# libimobiledevice 설치
brew install libimobiledevice

# 실기기 연결 후 전체 시스템 로그
idevicesyslog

# 특정 앱 로그만 필터
idevicesyslog | grep -E "optiflow|WebView|kakao|Error"
```

### 6.3 시뮬레이터 로그

```bash
# 시뮬레이터 전체 로그
xcrun simctl spawn booted log stream

# 필터링
xcrun simctl spawn booted log stream | grep -E "optiflow|WebView|Error"
```

---

## 7. Universal Links 검증 (App Links iOS 버전)

### 7.1 apple-app-site-association 파일 배포

Android의 `assetlinks.json`에 해당하는 파일을 서버에 배포:

```
https://도메인/.well-known/apple-app-site-association
```

```json
{
  "applinks": {
    "apps": [],
    "details": [
      {
        "appID": "TEAM_ID.com.패키지명",
        "paths": ["*"]
      }
    ]
  }
}
```

> **TEAM_ID**: Apple Developer 계정의 Team ID (10자리 영문+숫자)
> [developer.apple.com/account](https://developer.apple.com/account) → Membership에서 확인

### 7.2 시뮬레이터에서 Universal Links 테스트

```bash
# 시뮬레이터에서 특정 URL로 앱 실행
xcrun simctl openurl booted https://optiflow.kr/auth/kakao/callback
```

### 7.3 실기기에서 Universal Links 테스트

Safari에서 해당 URL 접속 → 앱으로 이동되는지 확인.

---

## 8. 배터리/기기 정보 확인 (실기기 연결 시)

```bash
# 배터리 잔량
ideviceinfo -k BatteryCurrentCapacity

# 배터리 최대 용량 (iOS 수명)
ideviceinfo -k BatteryMaximumCapacity

# 기기 정보 전체
ideviceinfo
```

---

## 9. App Store 배포 (유료 계정)

### 9.1 배포 준비

1. **앱 아이콘**: 1024x1024px PNG (투명 배경 불가)
2. **스크린샷**: 기기별 필수 (iPhone 6.5인치, 5.5인치)
3. **개인정보처리방침 URL**: 필수
4. **앱 설명**: 한글/영문

### 9.2 Archive 및 업로드

```
Xcode → Product → Archive
→ Distribute App
→ App Store Connect
→ Upload
```

### 9.3 App Store Connect에서 심사 제출

1. [appstoreconnect.apple.com](https://appstoreconnect.apple.com) 접속
2. 앱 정보 입력 → 심사 제출
3. 심사 기간: 보통 24~48시간 (거절 시 수정 후 재제출)

---

## 10. Android와 iOS 비교 요약

| 항목 | Android | iOS |
|---|---|---|
| **개발 환경** | 모든 OS | Mac 필수 |
| **빌드 도구** | Gradle | Xcode |
| **테스트 도구** | ADB | Xcode / libimobiledevice |
| **실시간 로그** | `adb logcat` | `idevicesyslog` / Xcode Console |
| **앱 설치** | APK 직접 설치 | Xcode 또는 TestFlight |
| **App Links** | assetlinks.json | apple-app-site-association |
| **개발자 계정** | 무료 | 무료(제한) / $99/년 |
| **심사** | 없음 (직접 배포) | 필수 (1~2일) |
| **FCM 테스트** | 실기기 필수 | 실기기 필수 |

---

## 11. 자주 발생하는 문제

| 증상 | 원인 | 해결 |
|---|---|---|
| `No signing certificate` | Apple 계정 미연결 | Xcode → Settings → Accounts에서 로그인 |
| `Untrusted Developer` | 실기기 신뢰 미설정 | 설정 → 일반 → VPN 및 기기 관리 → 신뢰 |
| `pod install` 실패 | CocoaPods 미설치 | `sudo gem install cocoapods` |
| `.xcworkspace` 없음 | pod install 미실행 | 프로젝트 ios/ 폴더에서 `pod install` |
| 시뮬레이터 흰 화면 | 웹뷰 URL 미설정 | capacitor.config.json의 server.url 확인 |
| Universal Links 미동작 | AASA 파일 미배포 | `.well-known/apple-app-site-association` 서버 배포 확인 |
