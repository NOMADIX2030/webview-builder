# webview-builder

웹사이트를 Android APK로 변환하는 3단계 빌드 서비스. **기존 Laravel 프로젝트에 하나의 기능 페이지로 추가**하여 웹뷰 APK 빌드 서비스를 제공할 수 있습니다.

---

## 대상

- **기존 Laravel 기반 웹사이트**에 웹뷰 APK 빌드 기능을 추가하려는 경우
- Cursor AI 등이 다른 Laravel 프로젝트에서 이 문서를 참조해 **단일 기능 모듈**로 통합할 때

---

## 통합 방식 개요

| 구분 | 내용 |
|------|------|
| **Web 경로** | `/build` (step1, step2, step3, {id}) |
| **API 경로** | `/api/upload`, `/api/build`, `/api/build/{id}/download/{type}` |
| **페이지 수** | 4개 (1단계, 2단계, 3단계, 빌드 결과) |
| **DB** | `builds` 테이블 1개 추가 |

---

## 기존 Laravel 프로젝트에 통합하는 방법

### 1. 복사할 파일 목록

아래 파일·폴더를 **본 레포의 webview-builder/** 에서 **대상 Laravel 프로젝트**로 복사한다.

| 소스 (webview-builder/) | 대상 (프로젝트 루트/) |
|-------------------------|------------------------|
| `app/Http/Controllers/Web/BuildController.php` | `app/Http/Controllers/Web/BuildController.php` |
| `app/Http/Controllers/Api/BuildController.php` | `app/Http/Controllers/Api/BuildController.php` |
| `app/Http/Controllers/Api/UploadController.php` | `app/Http/Controllers/Api/UploadController.php` |
| `app/Services/CapacitorBuildService.php` | `app/Services/CapacitorBuildService.php` |
| `app/Services/BuildConfigService.php` | `app/Services/BuildConfigService.php` |
| `app/Jobs/ProcessBuildJob.php` | `app/Jobs/ProcessBuildJob.php` |
| `app/Models/Build.php` | `app/Models/Build.php` |
| `resources/views/step1.blade.php` | `resources/views/step1.blade.php` |
| `resources/views/step2.blade.php` | `resources/views/step2.blade.php` |
| `resources/views/step3.blade.php` | `resources/views/step3.blade.php` |
| `resources/views/build/show.blade.php` | `resources/views/build/show.blade.php` |
| `resources/views/layouts/app.blade.php` | 참조용 (기존 레이아웃에 맞게 수정) |
| `database/migrations/2026_02_26_172120_create_builds_table.php` | `database/migrations/` |
| `storage/app/build-templates/webview-app/` | `storage/app/build-templates/webview-app/` |
| `config/build.php` | `config/build.php` |

### 2. 라우트 추가

**Web 라우트** (`routes/web.php`):

```php
// webview-builder: 3단계 빌드 UI
// prefix를 /build로 하면 컨트롤러·뷰의 route('build.xxx') 그대로 사용 가능
Route::prefix('build')->name('build.')->group(function () {
    Route::get('/', fn () => redirect()->route('build.step1'));
    Route::get('/step1', [App\Http\Controllers\Web\BuildController::class, 'step1'])->name('step1');
    Route::post('/step1', [App\Http\Controllers\Web\BuildController::class, 'step1Store'])->name('step1.store');
    Route::get('/step2', [App\Http\Controllers\Web\BuildController::class, 'step2'])->name('step2');
    Route::post('/step2', [App\Http\Controllers\Web\BuildController::class, 'step2Store'])->name('step2.store');
    Route::get('/step3', [App\Http\Controllers\Web\BuildController::class, 'step3'])->name('step3');
    Route::post('/step3', [App\Http\Controllers\Web\BuildController::class, 'step3Store'])->name('step3.store');
    Route::get('/{id}', [App\Http\Controllers\Web\BuildController::class, 'show'])->name('show');
});
```

**API 라우트** (`routes/api.php`):

```php
// webview-builder API (기존 /api prefix 아래에 추가)
Route::post('/upload', [App\Http\Controllers\Api\UploadController::class, 'store']);
Route::get('/upload/preview', [App\Http\Controllers\Api\UploadController::class, 'preview']);
Route::post('/build/generate-step2', [App\Http\Controllers\Api\BuildController::class, 'generateStep2']);
Route::post('/build', [App\Http\Controllers\Api\BuildController::class, 'store']);
Route::get('/build/{buildId}', [App\Http\Controllers\Api\BuildController::class, 'show']);
Route::get('/build/{buildId}/download/{type}', [App\Http\Controllers\Api\BuildController::class, 'download'])
    ->where('type', 'apk|ipa|keystore');
```

> **주의**: 
> - Web prefix를 `/webview` 등으로 바꾸면 컨트롤러·뷰 내 `route('build.xxx')`를 `route('webview.xxx')`로 일괄 수정 필요
> - API 경로가 기존 `/api/build`와 충돌하면 prefix 조정 (예: `/api/webview-build`)

### 3. 환경 변수 (.env)

```env
# webview-builder
BUILD_TEMPLATE_PATH=storage/app/build-templates/webview-app
BUILD_OUTPUT_PATH=storage/app/builds
```

### 4. 마이그레이션 및 스토리지

```bash
php artisan migrate
php artisan storage:link
```

### 5. 뷰·폼 수정

복사한 Blade 뷰에서 다음을 프로젝트에 맞게 수정:

- `@extends` 레이아웃 (기존 프로젝트 레이아웃 사용)
- API 호출 URL: 컨트롤러·JS에서 `/api/build`, `/api/upload` 등 호출 시, 대상 프로젝트의 API prefix와 일치하는지 확인

### 6. APK 빌드 환경 (선택)

APK를 실제로 생성하려면 **Java 17**, **Android SDK** 필요. → `docs/BUILD_ENVIRONMENT.md` 참조.

---

## Cursor AI 통합 시 체크리스트

다른 Laravel 프로젝트의 Cursor AI가 이 기능을 추가할 때:

1. [ ] 위 파일 복사 및 네임스페이스(`App\Http\Controllers\Web`, `Api`) 조정
2. [ ] Web 라우트 추가 (`/build` prefix 권장 — `route('build.xxx')` 그대로 사용)
3. [ ] API 라우트 추가 (`/api/upload`, `/api/build` 등 — 기존 경로와 충돌 시 prefix 조정)
4. [ ] `builds` 마이그레이션 실행
5. [ ] Blade 뷰의 `@extends`를 프로젝트 레이아웃으로 변경
6. [ ] nginx 등에서 `X-Accel-Redirect` 지원 시 APK 다운로드 설정 (선택)
7. [ ] `docs/DEV_SPEC.md` 참조해 API·빌드 흐름 확인

---

## 프로젝트 구조 (본 레포)

```
├── webview-builder/           # Laravel 앱 (통합 시 복사 소스)
│   ├── app/
│   │   ├── Http/Controllers/Web/
│   │   ├── Http/Controllers/Api/
│   │   ├── Jobs/
│   │   ├── Models/
│   │   └── Services/
│   ├── resources/views/
│   ├── storage/app/build-templates/
│   └── config/build.php
├── nginx/                     # nginx 설정 (참조용)
└── docs/                      # 상세 문서
```

---

## 문서

| 문서 | 용도 |
|------|------|
| **docs/DEV_SPEC.md** | API 명세, 빌드 절차, 데이터 스키마 |
| **docs/PROJECT_OVERVIEW.md** | 서비스 개요, 요구사항 |
| **docs/BUILD_ENVIRONMENT.md** | Java, Android SDK, APK 빌드 환경 |
| **docs/ENVIRONMENT_SETUP.md** | nginx, X-Accel-Redirect, 로컬 실행 |
| **docs/DATABASE.md** | builds 테이블 스키마 |
| **docs/PROJECT_STATUS.md** | 구현 현황 |

---

## 독립 실행 (참조·테스트용)

이 레포를 그대로 클론해 단독 실행할 수도 있다.

```bash
git clone git@github-webview-builder:NOMADIX2030/webview-builder.git
cd webview-builder
cp webview-builder/.env.example webview-builder/.env
# .env에 DB_DATABASE=webview_builder, DB 비밀번호 등 설정
cd webview-builder && composer install && npm install && npm run build
php artisan key:generate && php artisan migrate
```

> Deploy key: `github-webview-builder` 호스트. 일반 SSH: `git@github.com:NOMADIX2030/webview-builder.git`
