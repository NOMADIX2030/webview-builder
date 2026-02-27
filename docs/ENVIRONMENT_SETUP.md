# 웹뷰 앱 빌드 서비스 — 환경 설정 방안

> **목적**: 로컬 개발 환경 설정 (localhost 기준)  
> **상태**: 확정 — Laravel Blade 단일 앱 (nginx 통합)

---

## 1. nginx 설정

### 1.1 방안 요약

`www/nginx/` 폴더에 프로젝트 전용 설정을 두고, 메인 `www.conf`의 `include`로 불러온다. (sudo 없이 수정 가능)

### 1.2 구조

| 경로 | 역할 |
|------|------|
| `/` | Laravel Blade (3단계 폼, 빌드 상태) |
| `/api` | Laravel API (webview-builder/public) |
| `/storage` | Laravel storage 링크 (업로드 파일) |

### 1.3 구체 설정 (www/nginx/webview-builder.conf)

> 메인 www.conf의 `include www/nginx/*.conf`로 로드.

```nginx
# /api → Laravel API
location ^~ /api {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME /Users/.../webview-builder/public/index.php;
    ...
}

# /storage → Laravel storage (업로드 파일)
location ^~ /storage {
    alias /Users/.../webview-builder/storage/app/public;
}

# / → Laravel Blade (try_files → index.php)
location ~ ^/(?!api|storage|internal-download) {
    root /Users/.../webview-builder/public;
    try_files $uri $uri/ /index.php$uri$is_args$args;
}
location ^~ /index.php {
    fastcgi_pass 127.0.0.1:9000;
    ...
}
```

> Laravel `routes/api.php`가 `/api` 접두사로 라우팅. REQUEST_URI는 fastcgi_params에 포함됨.

---

## 2. 로컬 개발 실행 방식

### 2.1 nginx 통합 (권장)

| 서비스 | 실행 | 접근 |
|--------|------|------|
| **Laravel** | nginx + PHP-FPM | `/` → Blade, `/api` → API |

**접근**: `http://localhost/` → Laravel Blade 단일 앱  
**CORS**: 동일 origin (localhost:80)이므로 CORS 불필요.

**시작 순서**:
1. nginx 실행 (기존)
2. PHP-FPM 실행 (기존)

**빌드**: "빌드 시작" 클릭 시 동기 실행 (2~5분 소요). 별도 Queue 워커 불필요.  
**APK 빌드 시**: Java JDK 17, Android SDK 필요. → **docs/BUILD_ENVIRONMENT.md** 참조.  
**nginx**: `fastcgi_read_timeout 600` 설정됨 (빌드 요청용). 변경 후 `nginx -s reload`.  
**APK 다운로드**: X-Accel-Redirect → nginx가 직접 파일 서빙. `fastcgi_buffering off` 설정됨.  
**문제 시**:
- `fastcgi_temp` 권한 오류: `sudo chmod -R 1777 /usr/local/var/run/nginx/fastcgi_temp` 후 nginx 재시작.

### 2.1.1 PHP-FPM (macOS /Users 경로)

로컬 개발 시 PHP-FPM이 `/Users/...`에 접근하려면 `www.conf`의 `user`를 본인 사용자로 설정:

```bash
# /usr/local/etc/php/8.2/php-fpm.d/www.conf
user = awekers
group = staff
```

### 2.2 Vite 개발 모드 (선택)

Blade CSS/JS 핫 리로드가 필요할 때:

```bash
cd webview-builder && npm run dev
```

- Vite가 `http://localhost:5173`에서 HMR 서빙
- `@vite` 지시문이 개발 시 자동으로 Vite 서버 연결

### 2.3 확정: Laravel Blade 단일 앱

- **로컬 실행**: `http://localhost/`로 통일
- **nginx**가 `/` → Laravel Blade, `/api` → Laravel API, `/storage` → 업로드 파일 처리
- **CORS 불필요** (동일 origin)

---

## 3. 문서 위치 정리

### 3.1 확정: docs/ 통합

**모든 프로젝트 문서**는 **docs/**에 위치. 루트에는 README.md, AGENTS.md만 유지.

---

## 4. .gitignore (GitHub 개설 시 적용)

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
webview-builder/public/build/
webview-builder/storage/app/builds/*
!webview-builder/storage/app/builds/.gitkeep

# Laravel
webview-builder/storage/*.key
webview-builder/bootstrap/cache/*.php

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

1. `cd webview-builder && php artisan storage:link`
2. `public/storage` → `storage/app/public` 심볼릭 링크 생성
3. nginx `location /storage`에서 `webview-builder/storage/app/public` 서빙

### 5.2 Laravel 기본

- 업로드: `Storage::put('uploads/xxx', $file)`
- URL: `asset('storage/uploads/xxx')` → `/storage/uploads/xxx`

---

## 6. 적용 순서

| 단계 | 항목 | 시점 |
|------|------|------|
| 1 | 문서 위치 (docs/ 통합 완료) | - |
| 2 | nginx 설정 (www/nginx/) | Phase 1 완료 후 |
| 3 | 로컬 개발 (localhost) | Phase 1~5 전체 |
| 4 | storage 링크 | Phase 3 (Upload API 구현 시) |
| 5 | **APK 빌드 환경** (Java, Android SDK) | Phase 5 APK 빌드 시 → **BUILD_ENVIRONMENT.md** |
| 6 | .gitignore, git init | 초기 개발 완료 후 GitHub 개설 시 |

---

## 7. 관련 문서

| 문서 | 용도 |
|------|------|
| **BUILD_ENVIRONMENT.md** | APK 빌드용 Java, Android SDK 설치 |
| DATABASE.md | DB 스키마, 마이그레이션 |
| webview-builder.env.example | Laravel 환경 변수 |

---

## 8. Laravel 12 nginx 설정 (www.conf)

**참조**: [Laravel 12.x Deployment - Nginx](https://laravel.com/docs/12.x/deployment#nginx)

| 항목 | Laravel 12 공식 |
|------|-----------------|
| root | `프로젝트/public` (webview-builder/public) |
| try_files | `$uri $uri/ /index.php?$query_string` |
| error_page | `404 /index.php` |
| PHP location | `location ~ ^/index\.php(/|$)` (index.php만) |
| SCRIPT_FILENAME | `$realpath_root$fastcgi_script_name` 또는 명시 경로 |

**폴더명 변경 시**: `backend` → `webview-builder`로 바뀌면 `www.conf`의 `root`와 `SCRIPT_FILENAME` 경로를 업데이트해야 함.

---

## 9. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-28 | Laravel 12 공식 nginx 설정 적용, PHP-FPM /Users 권한 안내 |
| 2026-02-28 | Laravel Blade 마이그레이션 반영 (Next.js 제거) |
| 2026-02-27 | APK 빌드 환경 문서 링크 추가 |
| 2026-02-27 | 최초 작성 (우선순위 항목별 방안) |
