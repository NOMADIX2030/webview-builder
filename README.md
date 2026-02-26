# webview-builder

웹사이트를 Android APK로 변환하는 3단계 빌드 서비스. Laravel + Next.js + Capacitor.

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
cp docs/backend.env.example backend/.env
# DB_PASSWORD, APP_KEY 등 수정
cd backend && php artisan key:generate && php artisan migrate
```

> **nginx 경로**: 클론 위치가 다르면 `nginx/webview-builder.conf` 내 경로 수정 필요.

```bash
# 프론트엔드 (선택, 기본값 사용 시 생략 가능)
echo 'NEXT_PUBLIC_API_URL=http://localhost/api' > frontend/.env.local
```

### 3. 의존성 설치

```bash
# 백엔드
cd backend && composer install

# 프론트엔드
cd frontend && npm install
```

### 4. 실행

| 서비스 | 명령 | 비고 |
|--------|------|------|
| nginx | 기존 실행 | `/` → Next.js, `/api` → Laravel |
| PHP-FPM | 기존 실행 | Laravel |
| Next.js | `cd frontend && npm run dev` | 포트 3000 |

**접속**: http://localhost/

### 5. APK 빌드 시 추가 요구사항

Java 17, Android SDK 필요. → **docs/BUILD_ENVIRONMENT.md** 참조.

---

## 프로젝트 구조

```
├── frontend/          # Next.js (3단계 폼, 시뮬레이션)
├── backend/           # Laravel (API, 빌드)
├── nginx/             # nginx 설정
└── docs/              # 환경 설정, DB, 빌드 가이드
```

---

## 문서

| 문서 | 용도 |
|------|------|
| **docs/ENVIRONMENT_SETUP.md** | nginx, 로컬 실행 방식 |
| **docs/BUILD_ENVIRONMENT.md** | Java, Android SDK, APK 빌드 |
| **docs/DATABASE.md** | DB 스키마, 마이그레이션 |
| **docs/PROJECT_STATUS.md** | 구현 현황, 다음 단계 |
| **DEV_SPEC.md** | 기술 명세, API |
