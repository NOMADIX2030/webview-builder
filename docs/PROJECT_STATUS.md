# 프로젝트 현황

> **최종 업데이트**: 2026년 3월 2일 (FCM 푸시 알림 기능 전체 정상 동작 검증 완료)

---

## 1. 현재 상태

### 1.0 UI 마이그레이션 (Next.js → Laravel Blade)

| 구분 | 내용 |
|------|------|
| **프론트엔드** | Laravel Blade + Tailwind (서버사이드 렌더링) |
| **경로** | `/` → /build/step1, `/build/step2`, `/build/step3`, `/build/{id}` |
| **nginx** | `/` → Laravel `public`, `/api` → Laravel API |
| **세션** | `build_step1`, `build_step2` (폼 데이터) |

### 1.1 완료된 기능 (웹뷰 APK 빌드)

| 구분 | 내용 |
|------|------|
| **1단계** | 웹 URL, 앱 아이콘(512×512 권장), 스플래시 업로드 |
| **2단계** | 도메인 기반 자동 생성값(앱 이름, 패키지 ID 등) 확인·수정, FCM 푸시(google-services.json) 선택 |
| **3단계** | 모바일 시뮬레이션, APK 적용 아이콘 미리보기, 빌드 요청 |
| **빌드** | Capacitor 템플릿, @capacitor/assets 아이콘 생성(PHP GD 폴백), Android APK, Keystore 자동 생성 (동기 실행, 2~5분) |
| **다운로드** | APK, Keystore 다운로드 (X-Accel-Redirect → nginx 직접 서빙) |

### 1.2 적용된 설정·검증 완료 항목

| 항목 | 설정 | 비고 |
|------|------|------|
| **아이콘 미리보기** | `rounded-[22%]` (스쿼시클), `object-cover`, `bg-white` | 실제 APK와 90% 이상 일치 (검증 완료) |
| **뒤로가기** | `goBack()` → 서브 경로면 origin 이동 → 루트에서 뒤로가기 2회 연속 시 종료 (알림 없음, 2초 이내) | 앱 내 이전 페이지 이동 + 이중 클릭 종료 |
| **OAuth/소셜 로그인** | 카카오·구글·네이버 등 OAuth URL을 WebView 내에서 로드 (외부 브라우저 이탈 방지) | API 26+ 적용, 카카오 로그인 검증 완료 |
| **앱 도메인 URL** | OAuthWebViewClient에서 앱 서버 도메인(web_url) URL도 WebView 내 로드 | FCM 채팅 등 동일 도메인 링크가 브라우저로 열리지 않음 |
| **window.open 처리** | WebChromeClient.onCreateWindow로 target="_blank" 시 부모 WebView에 로드 | 외부 브라우저 이탈 방지 |
| **PDF/이미지 저장** | DownloadListener → PDF는 Downloads, 이미지는 Pictures(갤러리). blob URL: 훅+이중전략+클릭가로채기 (optiflow 등) | WRITE_EXTERNAL_STORAGE, READ_MEDIA_IMAGES (API 33+) |
| **아이콘 생성** | `@capacitor/assets` 1차 시도, 실패 시 PHP GD 폴백 | `npx @capacitor/assets generate --android` |
| **다운로드** | `<a download>` + 상대 경로 → Laravel X-Accel-Redirect → nginx internal location | PHP 버퍼 없음, 전체 파일 |
| **빌드 템플릿** | `node_modules` 복사 후 삭제 → `npm install`로 새로 생성 | 깨진 심볼릭 링크로 copy 실패 방지 |
| **FCM 푸시** | 2단계에서 google-services.json 업로드 → 앱에 FCM 포함. `window.onFcmTokenReady(token)` 웹 연동 | 웹에서 토큰 수신 후 서버 등록, FCM API로 발송 |
| **google-services.json** | 빌드 시 패키지명 자동 치환 (1단계 패키지 ID와 불일치해도 빌드 성공) | CapacitorBuildService |
| **알림 아이콘** | 앱 아이콘에서 흰색 실루엣 자동 생성 → 트레이에 앱 로고 표시 | FCM 사용 + 앱 아이콘 업로드 시 |
| **헤드업 알림** | IMPORTANCE_HIGH, PRIORITY_HIGH → 소리·진동·화면 상단 실시간 알림 카드 | Android 5.0+ |
| **알림 탭 시 URL** | 포그라운드: fcm_click_url / 백그라운드: data 키(action_url) → WebView.loadUrl | FCM notification+data 시 백그라운드 Intent 처리 |
| **Cold start 세션 복원** | 앱 전용 인증 토큰(app-token/app-login) → SharedPreferences 저장, FCM 클릭 시 세션 복원 | 앱 재시작 후에도 로그인 유지 |
| **redirect URL 인코딩** | FCM 클릭 시 action_url을 URL 인코딩하여 app-login/login에 전달 | `/chat?conversation=123` 등 쿼리 파라미터 정확 전달 |

### 1.3 FCM 푸시 검증 완료 (2026-03-02)

| 항목 | 상태 |
|------|------|
| 토큰 발급 → 웹 전달 | ✅ |
| 트레이 알림 아이콘 (앱 로고) | ✅ |
| 헤드업 알림 (소리·진동·화면 상단) | ✅ |
| 알림 탭 시 URL 로드 (포그라운드) | ✅ |
| 알림 탭 시 URL 로드 (백그라운드) | ✅ |
| Cold start 시 URL 로드 (앱 완전 종료 후) | ✅ |
| Cold start 세션 복원 (app-token/app-login) | ✅ |
| 채팅방 URL 정확 이동 (redirect 인코딩) | ✅ |

### 1.4 알려진 이슈

| 이슈 | 설명 | 우선순위 |
|------|------|----------|
| 3단계 아이콘 미리보기 | 기기별 마스크(원형/스쿼시클/둥근 사각형)로 100% 일치 불가 | 낮음 (90% 이상 동일로 검증 완료) |

---

## 2. 다음 단계 계획

| 순서 | 작업 | 비고 |
|------|------|------|
| 1 | 웹뷰 아이콘 및 추가 사항 테스트 | 3단계 미리보기 비율·여백 등 |
| 2 | 하이브리드 앱 빌드로 확장 | iOS IPA, 네이티브 기능 등 |

---

## 3. 참조 문서

| 문서 | 용도 |
|------|------|
| DEV_SPEC.md | 구현 명세, API, 빌드 절차 |
| BUILD_ENVIRONMENT.md | Java, Android SDK, Keystore 검증 |
| ENVIRONMENT_SETUP.md | nginx, 로컬 실행 방식 |
| DATABASE.md | 데이터베이스 테이블 |
| CAPACITOR_LEARNING.md | Capacitor 기능, 플러그인, iOS 확장 참고 |
| FCM_WEB_DEVELOPER_GUIDE.md | **고객용** FCM 푸시 연동 가이드 (웹 개발자 요구사항) |
