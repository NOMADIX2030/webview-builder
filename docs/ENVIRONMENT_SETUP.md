# 웹뷰 앱 빌드 서비스 — 환경 설정 방안

> **목적**: 로컬 개발 환경 설정 (localhost 기준)  
> **상태**: 확정 — localhost(nginx) 통합 방식 적용

---

## 1. nginx 설정

### 1.1 방안 요약

`www/nginx/` 폴더에 프로젝트 전용 설정을 두고, 메인 `www.conf`의 `include`로 불러온다. (sudo 없이 수정 가능)

### 1.2 제안 구조

| 경로 | 역할 |
|------|------|
| `/` | Next.js 프론트엔드 (프록시 또는 정적 파일) |
| `/api` | Laravel API (backend/public) |
| `/storage` | Laravel storage 링크 (업로드 파일) |

### 1.3 구체 설정 (www/nginx/webview-builder.conf)

> 메인 www.conf의 `include www/nginx/*.conf`로 로드. `location ^~ /`는 메인 설정의 `location /`보다 우선.

```nginx
# /api → Laravel (backend/public/index.php)
location ^~ /api {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME /Users/awekers/Sites/www/backend/public/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    include fastcgi_params;
}

# /storage → Laravel storage (업로드 파일)
location ^~ /storage {
    alias /Users/awekers/Sites/www/backend/storage/app/public;
}

# / → Next.js (포트 3000 프록시, ^~ 로 메인 location / 대체)
location ^~ / {
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

> Laravel `routes/api.php`가 `/api` 접두사로 라우팅. REQUEST_URI는 fastcgi_params에 포함됨.

---

## 2. 로컬 개발 실행 방식

### 2.1 방안 A: nginx 통합 (권장)

| 서비스 | 실행 | 접근 |
|--------|------|------|
| **Next.js** | `cd frontend && npm run dev` (포트 3000) | nginx가 `/`를 3000으로 프록시 |
| **Laravel** | nginx + PHP-FPM (기존) | `/api` → backend/public |
**접근**: `http://localhost/` → Next.js, `http://localhost/api` → Laravel  
**CORS**: 동일 origin (localhost:80)이므로 CORS 불필요.

**시작 순서**:
1. nginx 실행 (기존)
2. PHP-FPM 실행 (기존)
3. `npm run dev` (frontend)

**빌드**: "빌드 시작" 클릭 시 동기 실행 (2~5분 소요). 별도 Queue 워커 불필요.  
**APK 빌드 시**: Java JDK 17, Android SDK 필요. → **docs/BUILD_ENVIRONMENT.md** 참조.  
**nginx**: `fastcgi_read_timeout 600` 설정됨 (빌드 요청용). 변경 후 `nginx -s reload`.

### 2.2 방안 B: 분리 실행 (개발 편의)

| 서비스 | 실행 | 접근 |
|--------|------|------|
| **Next.js** | `npm run dev` | `http://localhost:3000` |
| **Laravel** | `php artisan serve --port=8000` | `http://localhost:8000` |

**환경 변수**: `NEXT_PUBLIC_API_URL=http://localhost:8000/api`  
**CORS**: Laravel `config/cors.php`에서 `localhost:3000` 허용 필요.

**장점**: nginx 설정 없이 빠른 개발. **단점**: CORS 설정, 포트 분리.

### 2.3 확정: 방안 A (localhost nginx 통합)

- **로컬 실행**: `http://localhost/`로 통일
- **nginx**가 `/` → Next.js(3000), `/api` → Laravel, `/storage` → 업로드 파일 처리
- **CORS 불필요** (동일 origin)

---

## 3. 문서 위치 정리

### 3.1 방안 A: docs/로 통합

| 현재 | 제안 |
|------|------|
| 루트: PROJECT_OVERVIEW, DEV_SPEC, TECH_STACK, AI_COLLABORATION | `docs/`로 이동 |
| docs/: backend.env.example | 유지 |

**결과**: `docs/`에 모든 문서 집중.

### 3.2 방안 B: 루트 유지 (현재)

| 현재 | 제안 |
|------|------|
| 루트: 주요 문서 | 유지 |
| docs/: 환경·DB 등 부가 문서 | 유지 |

**결과**: DEV_SPEC 3.1만 수정 — "문서는 루트와 docs/에 분산"으로 명시.

### 3.3 확정: 방안 B

**루트 유지** — 핵심 문서는 루트, 부가 문서는 docs/. DEV_SPEC 3.1에 반영.

---

## 4. .gitignore (GitHub 개설 시 적용)

> **시점**: 초기 개발 완료 후 GitHub 개설 시 .gitignore 추가 및 git init.

### 4.1 제안 내용

```gitignore
# 환경
.env
.env.local
.env.*.local

# 의존성
node_modules/
vendor/

# 빌드
frontend/.next/
frontend/out/
backend/storage/app/builds/*
!backend/storage/app/builds/.gitkeep

# Laravel
backend/storage/*.key
backend/bootstrap/cache/*.php

# IDE
.idea/
.vscode/
*.swp

# OS
.DS_Store

# Cursor (비밀번호 등)
.cursor/rules/sudo-password.mdc
```

---

## 5. storage/public 접근

### 5.1 절차

1. `cd backend && php artisan storage:link`
2. `public/storage` → `storage/app/public` 심볼릭 링크 생성
3. nginx `location /storage`에서 `backend/storage/app/public` 또는 `backend/public/storage` 서빙

### 5.2 Laravel 기본

- 업로드: `Storage::put('uploads/xxx', $file)`
- URL: `asset('storage/uploads/xxx')` → `/storage/uploads/xxx`

---

## 6. 적용 순서

| 단계 | 항목 | 시점 |
|------|------|------|
| 1 | 문서 위치 (DEV_SPEC 3.1 수정) | Phase 1 전 |
| 2 | nginx 설정 (www/nginx/) | Phase 1 완료 후 (backend, frontend 생성 직후) |
| 3 | 로컬 개발 (방안 A: localhost) | Phase 1~5 전체 |
| 4 | storage 링크 | Phase 3 (Upload API 구현 시) |
| 5 | **APK 빌드 환경** (Java, Android SDK) | Phase 5 APK 빌드 시 → **docs/BUILD_ENVIRONMENT.md** |
| 6 | .gitignore, git init | 초기 개발 완료 후 GitHub 개설 시 |

---

## 7. 관련 문서

| 문서 | 용도 |
|------|------|
| **docs/BUILD_ENVIRONMENT.md** | APK 빌드용 Java, Android SDK 설치 |
| docs/DATABASE.md | DB 스키마, 마이그레이션 |
| docs/backend.env.example | Laravel 환경 변수 |

---

## 8. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-27 | APK 빌드 환경 문서 링크 추가 |
| 2026-02-27 | 최초 작성 (우선순위 항목별 방안) |
