# webview-builder

웹사이트를 Android APK로 변환하는 3단계 빌드 서비스. Laravel Blade + Capacitor.

---

## 맥북에서 설치·실행 (클론 후 최소 설정)

### 1. 클론

```bash
git clone git@github-webview-builder:NOMADIX2030/webview-builder.git
cd webview-builder
```

> Deploy key 사용 시 `github-webview-builder` 호스트 사용. 일반 SSH 키 사용 시 `git@github.com:NOMADIX2030/webview-builder.git`

### 2. 설정 파일 생성 (필수)

```bash
# 백엔드 .env (MariaDB, webview_builder DB 설정 포함)
cp docs/webview-builder.env.example webview-builder/.env
# DB_PASSWORD, APP_KEY 등 수정
cd webview-builder && php artisan key:generate && php artisan migrate
```

> **nginx 경로**: 클론 위치가 다르면 `nginx/webview-builder.conf` 내 경로 수정 필요.

### 3. 의존성 설치

```bash
# 백엔드
cd webview-builder && composer install

# Laravel (Vite/Tailwind 빌드용)
cd webview-builder && npm install && npm run build
```

### 4. 실행

| 서비스 | 명령 | 비고 |
|--------|------|------|
| nginx | 기존 실행 | `/` → Laravel Blade, `/api` → Laravel |
| PHP-FPM | 기존 실행 | Laravel |

**접속**: http://localhost/

### 5. APK 빌드 시 추가 요구사항

Java 17, Android SDK 필요. → **docs/BUILD_ENVIRONMENT.md** 참조.

### 6. 적용된 주요 설정 (2026-02-28)

| 항목 | 설정 |
|------|------|
| **UI** | Laravel Blade + Tailwind (서버사이드 렌더링) |
| 아이콘 미리보기 | `rounded-[22%]`, `object-cover`, `bg-white` (실제 APK와 90% 이상 동일) |
| 앱 뒤로가기 | `OnBackPressedCallback` → webView.canGoBack() 시 goBack() |
| 아이콘 생성 | @capacitor/assets 1차, PHP GD 폴백 |
| APK 다운로드 | X-Accel-Redirect → nginx 직접 파일 서빙 |

---

## 프로젝트 구조

```
├── webview-builder/           # Laravel (Blade UI, API, 빌드)
├── nginx/             # nginx 설정
└── docs/              # 프로젝트 문서 전체
```

---

## 문서

| 문서 | 용도 |
|------|------|
| **docs/PROJECT_OVERVIEW.md** | 서비스 개요, 요구사항 |
| **docs/DEV_SPEC.md** | 기술 명세, API |
| **docs/TECH_STACK.md** | 아키텍처 |
| **docs/AI_COLLABORATION_PROCESS.md** | AI 협업 프로세스 |
| **docs/PROJECT_STATUS.md** | 구현 현황, 다음 단계 |
| **docs/ENVIRONMENT_SETUP.md** | nginx, 로컬 실행 방식 |
| **docs/BUILD_ENVIRONMENT.md** | Java, Android SDK, APK 빌드 |
| **docs/DATABASE.md** | DB 스키마, 마이그레이션 |
