# 웹뷰 앱 빌드 서비스 — 데이터베이스 문서

> **목적**: 데이터베이스 스키마, 테이블 구축 방법, 마이그레이션 절차 정의  
> **참조**: DEV_SPEC.md (API, 빌드 프로세스)

---

## 1. 개요

### 1.1 데이터베이스 정보

| 항목 | 값 |
|------|-----|
| **DB 이름** | `webview_builder` |
| **엔진** | MariaDB 10.11.x |
| **문자셋** | utf8mb4 |
| **콜레이션** | utf8mb4_unicode_ci |

### 1.2 접속 정보 (로컬)

| 항목 | 값 |
|------|-----|
| DB_CONNECTION | mariadb |
| DB_HOST | 127.0.0.1 |
| DB_PORT | 3306 |
| DB_DATABASE | webview_builder |
| DB_USERNAME | awekers |
| DB_PASSWORD | (기존 계정 비밀번호) |

> 소켓 접속 실패 시 `DB_HOST=127.0.0.1`로 TCP 접속 사용.

---

## 2. 테이블 스키마

### 2.1 builds 테이블

빌드 요청 및 결과 저장.

| 컬럼 | 타입 | Null | 기본값 | 설명 |
|------|------|------|--------|------|
| `id` | char(36) | NO | - | UUID (PK) |
| `status` | varchar(20) | NO | queued | queued, building, completed, failed |
| `app_type` | varchar(20) | NO | webview | webview, hybrid |
| `web_url` | varchar(500) | NO | - | 로드할 URL |
| `app_name` | varchar(255) | NO | - | 앱 이름 |
| `package_id` | varchar(255) | NO | - | 패키지 ID |
| `version_name` | varchar(50) | NO | 1.0.0 | 버전 표시명 |
| `version_code` | int unsigned | NO | 1 | 버전 코드 (정수) |
| `privacy_policy_url` | varchar(500) | NO | - | 개인정보처리방침 URL |
| `support_url` | varchar(500) | NO | - | 문의/지원 URL |
| `app_icon_path` | varchar(500) | NO | - | 앱 아이콘 저장 경로 |
| `splash_image_path` | varchar(500) | YES | NULL | 스플래시 이미지 경로 |
| `config_json` | json | YES | NULL | 기타 설정 (화면 방향, 푸시 등) |
| `apk_path` | varchar(500) | YES | NULL | APK 파일 경로 (완료 시) |
| `ipa_path` | varchar(500) | YES | NULL | IPA 파일 경로 (완료 시) |
| `keystore_path` | varchar(500) | YES | NULL | Keystore 경로 (Android) |
| `error_message` | text | YES | NULL | 실패 시 에러 메시지 |
| `created_at` | timestamp | YES | NULL | 생성 시각 |
| `updated_at` | timestamp | YES | NULL | 수정 시각 |
| `completed_at` | timestamp | YES | NULL | 완료 시각 |

**인덱스**:
- PRIMARY KEY (`id`)
- INDEX `builds_status_index` (`status`)
- INDEX `builds_created_at_index` (`created_at`)

---

## 3. 테이블 구축 방법

### 3.1 원칙

| 원칙 | 내용 |
|------|------|
| **Laravel 마이그레이션** | 모든 테이블·컬럼 변경은 마이그레이션으로 관리 |
| **직접 SQL 금지** | `CREATE TABLE` 등 직접 실행하지 않음 (초기 DB 생성 제외) |
| **롤백 가능** | `php artisan migrate:rollback`로 되돌릴 수 있어야 함 |
| **버전 관리** | `database/migrations/` 파일을 git으로 관리 |

### 3.2 구축 절차

```
1. 마이그레이션 파일 생성
   → php artisan make:migration create_builds_table

2. 마이그레이션 파일 편집
   → database/migrations/xxxx_create_builds_table.php
   → up(): 테이블 생성
   → down(): 테이블 삭제

3. 마이그레이션 실행
   → php artisan migrate

4. 검증
   → php artisan migrate:status
   → DB에서 테이블 구조 확인
```

### 3.3 마이그레이션 파일 작성 규칙

| 규칙 | 내용 |
|------|------|
| **파일명** | `YYYY_MM_DD_HHMMSS_create_builds_table.php` |
| **클래스명** | `CreateBuildsTable` (PascalCase, 테이블명 복수형) |
| **up()** | `Schema::create('builds', function (Blueprint $table) { ... })` |
| **down()** | `Schema::dropIfExists('builds')` |
| **타입** | Laravel Blueprint 메서드 사용 (`uuid`, `string`, `text`, `json`, `timestamp` 등) |

### 3.4 builds 테이블 마이그레이션 예시

```php
// database/migrations/xxxx_create_builds_table.php
public function up(): void
{
    Schema::create('builds', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('status', 20)->default('queued');
        $table->string('app_type', 20)->default('webview');
        $table->string('web_url', 500);
        $table->string('app_name');
        $table->string('package_id');
        $table->string('version_name', 50)->default('1.0.0');
        $table->unsignedInteger('version_code')->default(1);
        $table->string('privacy_policy_url', 500);
        $table->string('support_url', 500);
        $table->string('app_icon_path', 500);
        $table->string('splash_image_path', 500)->nullable();
        $table->json('config_json')->nullable();
        $table->string('apk_path', 500)->nullable();
        $table->string('ipa_path', 500)->nullable();
        $table->string('keystore_path', 500)->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
        $table->timestamp('completed_at')->nullable();

        $table->index('status');
        $table->index('created_at');
    });
}
```

### 3.5 UUID 사용

- `id`는 UUID v4 사용
- Laravel: `Str::uuid()` 또는 `$model->id = (string) Str::uuid()`
- 마이그레이션: `$table->uuid('id')->primary()` (Laravel 9+)

---

## 4. 스키마 변경 절차

새 테이블 추가 또는 기존 테이블 수정 시:

| 순서 | 작업 |
|------|------|
| 1 | 이 문서(DATABASE.md)에 변경 내용 반영 |
| 2 | `php artisan make:migration add_xxx_to_builds_table` 또는 `create_xxx_table` |
| 3 | 마이그레이션 파일 작성 |
| 4 | `php artisan migrate` 실행 |
| 5 | Eloquent 모델 수정 (필요 시) |
| 6 | DEV_SPEC.md API·스키마 섹션 동기화 |

---

## 5. 참조

| 문서 | 용도 |
|------|------|
| DEV_SPEC.md 6. 데이터베이스 스키마 | API 연동, 스키마 요약 |
| webview-builder.env.example | DB 접속 환경 변수 |
| Laravel Migrations | https://laravel.com/docs/migrations |

---

## 6. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-27 | 최초 작성 (builds 테이블, 구축 절차) |
