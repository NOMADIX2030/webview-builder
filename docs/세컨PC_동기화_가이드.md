# 세컨 PC 동기화 가이드

> **대상**: 주개발을 다른 PC에서 하고, 이 PC(세컨)에서 GitHub를 통해 최신 코드를 받아 사용하는 경우  
> **최종 업데이트**: 2026년 3월 9일

---

## 1. 개요

주개발 PC에서 기능 개발·디버깅 후 GitHub에 push하면, 세컨 PC에서는 아래 절차만 수행하면 **추가 코드 수정 없이** 최신 상태로 동기화할 수 있다.

---

## 2. 동기화 절차

```bash
# 1. 프로젝트 루트로 이동
cd /Users/awekers/Sites/www

# 2. GitHub에서 최신 코드 받기
git pull

# 3. PHP 의존성 설치 (vendor/는 Git 미포함)
cd webview-builder && composer install

# 4. Vite 빌드 (public/build/ 생성, Git 미포함)
npm run build

# 5. (선택) 새 마이그레이션이 있으면 실행
php artisan migrate --force
```

---

## 3. 최초 설정 (한 번만)

이 PC를 처음 사용하거나 새로 클론한 경우, 아래를 한 번만 진행한다.

### 3.1 nginx user 설정 (macOS)

`/usr/local/etc/nginx/nginx.conf` 상단:

```nginx
user  awekers staff;   # nobody 대신
worker_processes  1;
```

이후 `nginx -s reload` 또는 nginx 재시작.

### 3.2 환경 파일

```bash
cd webview-builder
cp .env.example .env
# .env 내 DB 비밀번호, APP_KEY 등 수정
php artisan key:generate
```

### 3.3 데이터베이스

```bash
php artisan migrate --force
```

### 3.4 Storage 링크

```bash
php artisan storage:link
```

---

## 4. 주의 사항

| 항목 | 설명 |
|------|------|
| **코드 수정 금지** | 세컨 PC에서 코드 수정 시 주개발 PC와 충돌. 로컬 수정 필요 시 브랜치 사용 |
| **git pull 전 확인** | `git status`로 로컬 변경 여부 확인. 변경 있으면 `git stash` 또는 버림 |
| **vendor, public/build** | Git 미포함. `composer install`, `npm run build` 필수 |
| **.env** | Git 미포함. 로컬에서 별도 유지 |

---

## 5. 참조

| 문서 | 용도 |
|------|------|
| PROJECT_STATUS.md | 구현 현황 |
| ENVIRONMENT_SETUP.md | nginx, PHP-FPM 환경 |
| AGENTS.md §2 | 워크스페이스, 접근 경로 |
