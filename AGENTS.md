# webview-builder — AI 에이전트 지침

> Cursor 에이전트가 이 프로젝트에서 작업할 때 **항상** 참조하는 문서.

---

## 1. 새 대화/작업 시작 시 (필수)

**첫 응답 전에** 반드시 `read_file`로 아래 파일을 읽고 맥락을 파악한다:

1. `docs/PROJECT_STATUS.md` — 구현 현황, 알려진 이슈
2. `docs/DEV_SPEC.md` — 전체 명세 (필요 시)

(프로젝트 맥락은 본 문서 AGENTS.md 섹션 3에 포함됨)

**에러·문제 대응 시** 추가로 `docs/DEBUGGING.md`를 읽고 섹션 9 원칙을 준수한다.

→ 읽기 전에 추측·응답 금지.

---

## 2. 환경

| 항목 | 값 |
|------|-----|
| **워크스페이스** | `/Users/awekers/Sites/www` |
| **접근** | `http://localhost/` (nginx 문서 루트) |
| **API Base** | `http://localhost/api` |
| **로컬 실행** | nginx 통합: `/`→Laravel Blade, `/api`→Laravel |
| **프로젝트 nginx** | `www/nginx/*.conf` |
| **데이터베이스** | `webview_builder` (MariaDB 10.11, 127.0.0.1) |

---

## 3. 프로젝트 맥락

### GitHub

| 항목 | 값 |
|------|-----|
| **레포** | https://github.com/NOMADIX2030/webview-builder |
| **URL** | git@github-webview-builder:NOMADIX2030/webview-builder.git |
| **Deploy 키** | `~/.ssh/deploy_webview-builder` |
| **클론** | `git clone git@github-webview-builder:NOMADIX2030/webview-builder.git` |

### 완료 상태 (2026-03-02)

- 웹뷰 APK 빌드: 1~3단계 완성, 2~5분 동기 실행
- 앱 아이콘: @capacitor/assets 1차, PHP GD 폴백
- 아이콘 미리보기: 실제와 90% 이상 동일 (rounded-[22%], object-cover, bg-white) — 검증 완료
- 뒤로가기: OnBackPressedCallback → webView.canGoBack() 시 goBack() → 루트에서 2회 연속 시 종료 (알림 없음) — 검증 완료
- OAuth/소셜 로그인: Android OAuthWebViewClient, iOS server.allowNavigation으로 카카오·구글·네이버 등 OAuth URL을 WebView 내 로드 (외부 브라우저 이탈 방지) — 카카오 로그인 검증 완료
- **FCM 푸시**: 전체 정상 동작 — 토큰 전달, 앱 로고 알림 아이콘, 헤드업, 알림 탭 시 URL 로드 (포그라운드·백그라운드·cold start 모두)
- **Cold start 세션 복원**: 앱 전용 인증 토큰(app-token/app-login) 방식으로 앱 재시작 시에도 로그인 유지
- **앱 도메인 URL 처리**: OAuthWebViewClient에서 앱 서버 도메인 URL도 WebView 내 로드 (브라우저 이탈 방지)
- **window.open 처리**: WebChromeClient.onCreateWindow로 target="_blank" 시 부모 WebView에 로드

### 최근 작업 (2026-03-02)

- **FCM 알림 기능**: 전체 정상 동작 검증 완료 — cold start 세션 복원(app-token/app-login), redirect URL 인코딩, 채팅방 정확 이동
- **FCM 가이드**: FCM_WEB_DEVELOPER_GUIDE.md 웹 개발자용 상세 안내 (토큰 수신, SPA 예시, app-token/app-login 섹션 추가)
- OAuth/소셜 로그인: Android·iOS 카카오 로그인 인앱 처리 (검증 완료)
- Laravel Blade 마이그레이션, 뒤로가기, EMFILE 해결, 다운로드 X-Accel-Redirect
- sudo: AGENTS.md 섹션 7 참조

### 다음 단계

1. 웹뷰 아이콘·추가 사항 테스트
2. 하이브리드 앱 빌드 확장 (푸시, 네이티브 기능)

### 핵심 경로

- **UI**: `webview-builder/resources/views/` (step1, step2, step3, build/show)
- **템플릿**: `webview-builder/storage/app/build-templates/webview-app/`
- **빌드**: `webview-builder/app/Services/CapacitorBuildService.php`

---

## 4. 개발 규칙

### Phase

- **Phase 1~5 순서를 반드시 지킨다**
- 단계 건너뛰기 금지
- 각 Phase 완료 후 검증 후 다음 Phase 진행

### MVP 범위

| 포함 | 제외 |
|------|------|
| 일반 웹뷰만 | 하이브리드 앱 |
| Android APK만 | iOS IPA |
| 3단계 흐름 | 회원/인증 |
| Keystore 자동 생성 | 사용자 Keystore 업로드 |

### 디자인·코딩

| 규칙 | 내용 |
|------|------|
| **UI** | Laravel Blade + Tailwind (서버사이드 렌더링) |
| **디자인** | 모바일 우선 (375px~430px) |
| **품질** | 네이티브 앱 수준 (iOS HIG, Material Design) |

### 기술 스택

- 백엔드: Laravel 11.x | 프론트: Blade + Tailwind | DB: MariaDB 10.x
- 빌드: Capacitor 6.x

---

## 5. 협업·문서

- **문서 우선**: 새 기능·변경 시 문서 먼저 수정 → 구현
- **참조**: docs/PROJECT_OVERVIEW.md, docs/DEV_SPEC.md, docs/TECH_STACK.md, docs/AI_COLLABORATION_PROCESS.md
- **에러 대응 시 필수**: docs/DEBUGGING.md
- **기타**: docs/DATABASE.md, docs/ENVIRONMENT_SETUP.md, docs/BUILD_ENVIRONMENT.md

---

## 6. 서버 실행

사용자가 **"서버실행"** 또는 **"서버 실행"**이라고 하면:

- **Laravel Blade**: nginx + PHP-FPM만 필요. 별도 프론트엔드 서버 없음.
- **접근**: `http://localhost/` (nginx가 Laravel `webview-builder/public` 서빙)
- **Vite 개발 모드** (선택): `cd webview-builder && npm run dev` — Blade CSS/JS 핫 리로드

---

## 7. sudo

- **비밀번호**: `difm2020^`
- **실행**: `echo 'difm2020^' | sudo -S 명령` (비대화형 터미널 대응)

---

## 8. 에러·문제 대응

**문제 발생 시 반드시 준수**:

| 금지 | 필수 |
|------|------|
| 추측·가정 기반 대응 | 사용자와 의논 |
| 표면적 해결 | 근본원인(root cause) 파악 |
| 단독 판단 | 공식 문서·GitHub 조사 후 함께 논의 |

- 원인 파악이 어려울 때: 공식 문서, GitHub(이슈, PR, Discussions) 조사 → 사용자와 의논

---

## 9. 디버깅·문제해결 원칙 (필수 학습)

> **범용 방법론**: 어떤 문제든 이 원칙으로 접근한다. **반드시 준수.**

### 9.1 핵심 원칙

| 원칙 | 내용 |
|------|------|
| **원인 분리** | 증상만 보고 추측하지 않는다. **어디**에서 문제가 발생하는지 먼저 구분한다. (웹서버/DB/API/앱/빌드 등) |
| **최소 검증** | **가장 단순한 단계**부터 검증한다. 테스트용 입력·파일·요청으로 "이 단계만" 동작하는지 확인. |
| **단계별 추가** | 한 단계가 정상 확인된 후에만 다음 단계를 추가한다. |
| **한 번에 하나** | 여러 설정·코드를 동시에 수정하지 않는다. |
| **사용자 제안 우선** | 사용자가 제시한 접근법이 명확하면 **즉시 따른다.** |

### 9.2 일반 절차

1. **관련 요소 정리** — 프로세스 종료, 설정 비활성화 등으로 깨끗한 상태에서 시작
2. **최소 단계 검증** — 가장 기본적인 동작만 테스트
3. **단계별 추가** — 검증된 단계 위에 필요한 요소를 하나씩 추가하며 확인
4. **추가 설정·기능** — 핵심이 정상이면 나머지를 단계별로 활성화

### 9.3 금지 사항

- 원인 불명인데 여러 요소를 **동시에** 수정
- 검증 없이 **추측으로** 설정·코드 변경
- 사용자 제안을 **무시하고** 다른 시도 반복

→ 상세·예시: `docs/DEBUGGING.md`
