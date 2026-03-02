# Capacitor 학습 문서

> **작성일**: 2026년 2월 27일  
> **목적**: 웹뷰 빌더 프로젝트의 iOS 확장 및 하이브리드 앱 개발 시 참고

---

## 1. Capacitor 개요

Capacitor는 Ionic 팀이 만든 **하이브리드 앱 프레임워크**로, 웹 앱을 네이티브 컨테이너로 감싸 iOS/Android 앱을 빌드한다. Cordova의 현대적 후속으로, **네이티브 프로젝트에 직접 접근**할 수 있어 플러그인 의존도를 줄인다.

| 구분 | Cordova | Capacitor |
|------|---------|-----------|
| 네이티브 접근 | 플러그인 위주 | 프로젝트 직접 수정 가능 |
| 유지보수 | 지원 약화 (App Center 2022 폐기) | Ionic 주도, 활발히 유지 |
| 플러그인 | 호환 플러그인 필요 (Swift, AndroidX) | Cordova 플러그인 호환 |

---

## 2. Capacitor 6 주요 변경 (2024)

### 2.1 플랫폼 지원

| 항목 | 내용 |
|------|------|
| **Android** | Android 14 지원, 기본 scheme `https` 전환 (Autofill 대응) |
| **iOS** | iOS 17 지원, Swift Package Manager(SPM) 실험 지원 |
| **Node.js** | 18+ (Node 16 EOL) |
| **Xcode** | 15.0+ |
| **Android Studio** | Hedgehog (2023.1.1) 이상, Gradle 8.2 |

### 2.2 iOS 플러그인 등록 변경

- 플러그인 클래스가 **자동 등록되지 않음**
- npm 플러그인: CLI가 등록할 클래스 목록 생성
- 로컬 커스텀 플러그인: 커스텀 뷰 컨트롤러에서 **수동 등록** 필요

### 2.3 마이그레이션

```bash
npx cap migrate
```

- VS Code 확장으로 마이그레이션 지원
- 5 → 6 전환 시 breaking change 최소화

---

## 3. 공식 플러그인

### 3.1 핵심 플러그인 목록

| 플러그인 | 용도 | 비고 |
|----------|------|------|
| @capacitor/camera | 카메라 접근 | 사진 촬영, 갤러리 선택 |
| @capacitor/filesystem | 파일 읽기/쓰기 | Node.js 스타일 API, downloadFile 7.1.0부터 deprecated → File Transfer 플러그인 |
| @capacitor/push-notifications | 푸시 알림 | Android: FCM, iOS: APNs |
| @capacitor/geolocation | 위치 | GPS |
| @capacitor/local-notifications | 로컬 알림 | 앱 내 스케줄 알림 |
| @capacitor/haptics | 햅틱 | 진동 피드백 |
| @capacitor/status-bar | 상태바 | 색상, 스타일 |
| @capacitor/splash-screen | 스플래시 | 표시/숨김 |
| @capacitor/app | 앱 정보 | 버전, 빌드 번호 등 |
| @capacitor/network | 네트워크 | 연결 상태 |
| @capacitor/keyboard | 키보드 | 표시/숨김, 이벤트 |
| @capacitor/preferences | 저장소 | Key-Value (SharedPreferences/UserDefaults) |

### 3.2 설치 예시

```bash
npm install @capacitor/filesystem
npm install @capacitor/push-notifications
npx cap sync
```

### 3.3 참고

- [Capacitor APIs 문서](https://capacitorjs.com/docs/apis)
- [capacitor-plugins GitHub](https://github.com/ionic-team/capacitor-plugins)

---

## 4. 커뮤니티 플러그인

### 4.1 @capacitor-community

- **58~73개** 플러그인
- Ionic 팀이 생태계를 관리하지만 개별 플러그인은 커뮤니티 유지
- [GitHub](https://github.com/capacitor-community)
- [npm scope](https://www.npmjs.com/org/capacitor-community)

### 4.2 Capawesome

- Ionic Developer Expert가 운영
- `@capawesome/capacitor-firebase`, `@capawesome/capacitor-mlkit` 등
- **Live Update** 플러그인: OTA 업데이트

### 4.3 주요 커뮤니티 플러그인

| 플러그인 | 용도 |
|----------|------|
| @capacitor-community/google-maps | Google Maps |
| @capacitor-community/bluetooth-le | 블루투스 LE |
| @capawesome/capacitor-live-update | OTA 실시간 업데이트 |
| @capacitor-community/firebase-analytics | Firebase 분석 |
| @capacitor-community/admob | AdMob 광고 |
| @capacitor-community/apple-sign-in | Apple 로그인 |

### 4.4 Cordova 플러그인 호환

- 대부분의 Cordova 플러그인 사용 가능
- `@awesome-cordova-plugins`로 래퍼 제공

---

## 5. 프로덕션 검증 사례

### 5.1 Bestinvest (투자 플랫폼)

- **기간**: 약 6개월
- **방식**: 기존 React 웹 앱을 Capacitor로 감싸 iOS/Android 앱화
- **선택 이유**: React Native 대비 주어진 시간 내 더 많은 기능 구현 가능
- **보안**: Identity Vault로 생체 인증
- [케이스 스터디](https://ionic.io/resources/case-studies/bestinvest)

### 5.2 Found (핀테크)

- **작업**: Cordova → Capacitor 마이그레이션
- **결과**: 다운타임 없이 전환, 네이티브 레이어 개선
- **해결한 문제**: 미지원 플러그인, 디버깅 어려움, 커스텀 플러그인 개발 부담
- [마이그레이션 포스트](https://found.com/engineering/migrating-from-cordova-to-capacitor)

### 5.3 BBC Children's Games

- **방식**: PWA를 Capacitor로 감싸 앱스토어 배포
- **효과**: 단일 코드베이스로 웹·iOS·Android 지원, 빌드/유지 비용 감소
- [케이스 스터디](https://ionic.io/resources/case-studies/bbc-games)

### 5.4 Eative (헬스 앱)

- **선택**: 네이티브·다른 프레임워크 검토 후 Capacitor 하이브리드 선택
- **기준**: 비용, 속도, 품질 균형

---

## 6. iOS blob/이미지 다운로드 이슈

### 6.1 WKWebView 한계

- `blob:` URL을 `download` 속성과 함께 사용하는 `<a>` 태그를 **제대로 지원하지 않음**
- **iOS 13~14**: "Failed to open URL" 에러
- **iOS 15+**: 조용히 실패
- [WebKit 버그](https://bugs.webkit.org/show_bug.cgi?id=216918)

### 6.2 Capacitor 앱에서의 영향

- blob 다운로드가 iOS·Android 모두에서 기본적으로 동작하지 않음
- [GitHub 이슈 #5478](https://github.com/ionic-team/capacitor/issues/5478)

### 6.3 iOS 대응 방안

| iOS 버전 | 방법 |
|----------|------|
| **14.5+** | `WKDownloadDelegate` 구현: `webView(_:navigationResponse:didBecome:)`, `webView(_:navigationAction:didBecome:)` |
| **이전** | JS에서 blob → base64 변환 후 `WKScriptMessageHandler`로 전달 |

### 6.4 파일 저장

- Capacitor Filesystem API 사용
- iOS는 **앱 샌드박스** 내부만 접근 가능
- 공용 사진 앱 폴더는 별도 권한/처리 필요

---

## 7. Live Update (OTA)

### 7.1 개념

- **웹 레이어**(HTML, CSS, JS)만 **앱스토어 재제출 없이** 업데이트
- 네이티브 코드 변경은 여전히 앱스토어 제출 필요

### 7.2 플러그인

| 플러그인 | 제공처 |
|----------|--------|
| @capacitor/live-updates | Ionic 공식 |
| @capawesome/capacitor-live-update | Capawesome |

### 7.3 주요 기능

- 번들 관리: 다운로드, 설정, 삭제
- 자동 업데이트: 최신 번들 자동 적용
- 델타 업데이트: 변경된 파일만 전송
- 코드 서명 및 검증
- 채널별 버전 관리
- 롤백 지원

### 7.4 Capawesome Cloud

- 번들 호스팅 서비스
- 약 60초 내 배포
- [cloud.capawesome.io](https://cloud.capawesome.io/live-updates)

---

## 8. WebView 설정 및 보안

### 8.1 capacitor.config 설정

```typescript
// capacitor.config.ts
export default {
  android: {
    overrideUserAgent: '...',
    appendUserAgent: '...',
  },
  ios: {
    backgroundColor: '#ffffff',
  },
  web: {
    zoomEnabled: false,
    initialFocus: true,
    loggingBehavior: 'production', // 'none' | 'debug' | 'production'
  },
};
```

### 8.2 보안 권장 사항

| 항목 | 권장 |
|------|------|
| API 키 | 앱 코드에 직접 포함 금지 |
| 파일 프로토콜 | `file://` 대신 `convertFileSrc()` 사용 |
| CORS | 외부 서비스가 CORS 정책 준수 |
| 토큰/키 | iOS Keychain, Android Keystore 사용 |

### 8.3 네이티브 WebView 설정

- Capacitor 3+: `CAPBridgeViewController` 서브클래스
- `webViewConfiguration(for:)` 오버라이드로 `WKWebViewConfiguration` 수정

---

## 9. Cordova → Capacitor 마이그레이션

### 9.1 공식 8단계

1. 코드 브랜치 생성
2. Capacitor CLI 및 코어 설치
3. 웹 앱 빌드
4. Android/iOS 플랫폼 추가
5. 스플래시·아이콘 재구성
6. Cordova 플러그인 감사 후 Capacitor 대체 플러그인으로 교체
7. 테스트 후 Cordova 제거
8. Capacitor 기반 개발 진행

### 9.2 마이그레이션 전략 문서

- [capacitorjs.com/docs/cordova/migration-strategy](https://capacitorjs.com/docs/cordova/migration-strategy)
- Ionic VS Code 확장으로 일부 자동화 가능

---

## 10. 웹뷰 빌더 프로젝트 적용 참고

### 10.1 현재 상태 (Android)

- blob 다운로드: URL.createObjectURL 훅 + 이중 전략 + 클릭 가로채기
- OAuth: WebView 내 로드 (OAuthWebViewClient)
- 뒤로가기: goBack + 이중 클릭 종료

### 10.2 iOS 확장 시

- 위 Android 로직을 **Swift로 동일 개념** 구현
- WKWebView: `WKUserScript`로 blob 훅 주입
- `WKScriptMessageHandler`로 JS ↔ 네이티브 통신
- iOS 14.5+에서는 `WKDownloadDelegate` 검토

### 10.3 웹 페이지 수정 옵션 (optiflow 등 자체 페이지)

**페이지 소유 시** WebView 전용 경로를 두는 것이 가장 확실함:

```javascript
// 이미지 저장 버튼 클릭 시
if (typeof AndroidBridge !== 'undefined' && AndroidBridge.saveDataUrl) {
  // WebView 앱: 직접 브릿지 호출
  AndroidBridge.saveDataUrl(dataUrl, '견적서.png');
} else {
  // 일반 브라우저: 기존 <a download> 방식
  const a = document.createElement('a');
  a.href = dataUrl;
  a.download = '견적서.png';
  a.click();
}
```

- **장점**: Chrome·WebView 모두 동일하게 동작
- **단점**: 페이지 수정 권한 필요

**앱 주입**: OAuthWebViewClient에서 `window.saveImageToDevice` → `AndroidBridge.saveDataUrl` 연결. iOS 확장 시 동일 인터페이스로 연결.

**타사 페이지**인 경우: 앱에서 blob 훅 + 클릭 가로채기로 대응.

### 10.4 참고 문서

- [Capacitor 공식 문서](https://capacitorjs.com/docs)
- [Ionic WebView 가이드](https://ionicframework.com/docs/v7/core-concepts/webview)
- [Capacitor 보안 가이드](https://capacitorjs.com/docs/guides/security)
