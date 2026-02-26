# 웹뷰 앱 빌드 서비스 — 개발 초안 명세서

> **목적**: Cursor AI가 이 문서를 기반으로 정확한 개발을 수행할 수 있도록 작성된 기술 명세서  
> **작성일**: 2026년 2월 27일  
> **참조**: PROJECT_OVERVIEW.md

---

## 1. 문서 개요

### 1.1 이 문서의 역할

| 대상 | 용도 |
|------|------|
| **Cursor AI** | 개발 시 참조할 핵심 명세. 이 문서의 구조, API, 규칙을 정확히 따를 것 |
| **개발자** | 구현 가이드, 아키텍처 이해 |

### 1.2 프로젝트 목적

웹사이트 소유자가 **도메인 주소와 최소 정보**만 입력하면, 해당 웹을 **웹뷰 앱** 또는 **하이브리드 앱**으로 패키징하여 **APK/IPA 빌드 파일**을 생성하는 서비스. 사용자는 생성된 파일을 직접 앱스토어에 출시한다.

---

## 2. 기술 스택

### 2.1 전체 스택

| 계층 | 기술 | 버전 | 역할 |
|------|------|------|------|
| **프론트엔드** | Next.js (React) | 14.x | 3단계 폼 UI, 시뮬레이션 |
| **UI/디자인** | shadcn/ui + Tailwind | - | 컴포넌트, 모바일 우선 디자인 |
| **백엔드** | Laravel | 11.x | API, 빌드 큐, 파일 저장 |
| **DB** | MariaDB | 10.x | 빌드 이력, 메타데이터 |
| **캐시/큐** | Redis | 7.x | Laravel Queue |
| **빌드 엔진** | Capacitor | 6.x | 웹뷰/하이브리드 앱 생성 |
| **런타임** | Node.js | 20.x | Capacitor 빌드 실행 |
| **웹 서버** | nginx | - | 정적 파일, API 프록시 |

### 2.2 기술 선택 이유

| 기술 | 이유 |
|------|------|
| **Laravel** | 기존 웹앱 개발 스택, Queue·파일·DB 처리 용이 |
| **Next.js** | 기존 스택, 3단계 폼·SSR 적합 |
| **Capacitor** | 웹 기반 앱 빌드, 템플릿 주입 용이 |

### 2.3 디자인 표준 (필수 준수)

> **AI 개발 시**: 이 프로젝트의 모든 UI/UX는 아래 디자인 표준을 **반드시** 따를 것.

| 항목 | 규정 |
|------|------|
| **UI 라이브러리** | **shadcn/ui만** 사용. 다른 UI 라이브러리(Flowbite, MUI, Chakra 등) 사용 금지 |
| **디자인 원칙** | **모바일 우선(Mobile-First)** |
| **품질 기준** | **네이티브 앱 디자인 수준** (iOS HIG, Material Design 가이드라인 참고) |

#### 2.3.1 shadcn/ui 사용 규칙

| 규칙 | 내용 |
|------|------|
| **컴포넌트** | shadcn/ui 공식 컴포넌트만 사용 |
| **추가 컴포넌트** | 필요 시 shadcn/ui 패턴으로 확장 (Radix UI + Tailwind) |
| **스타일** | Tailwind CSS. shadcn/ui 테마 변수 활용 |
| **폼** | React Hook Form + Zod + shadcn Form 컴포넌트 |

#### 2.3.2 모바일 우선 디자인

| 항목 | 기준 |
|------|------|
| **뷰포트** | 375px (iPhone SE) ~ 430px 기준으로 우선 설계 |
| **터치 영역** | 최소 44x44px (Apple HIG) |
| **여백** | 모바일 safe area (상단 노치, 하단 홈 인디케이터) 고려 |
| **반응형** | 모바일 → 태블릿 → 데스크톱 순으로 확장 |

#### 2.3.3 네이티브 앱 수준 디자인

| 항목 | 기준 |
|------|------|
| **iOS HIG** | Human Interface Guidelines (탭, 네비게이션, 제스처) 참고 |
| **Material Design** | 터치 피드백, 엘리베이션, 모션 원칙 참고 |
| **접근성** | WCAG 2.1 AA, 키보드/스크린 리더 지원 |

#### 2.3.4 AI 학습 참조 소스

| 소스 | 용도 |
|------|------|
| **shadcn/ui 공식** | https://ui.shadcn.com — 컴포넌트 API, 사용법 |
| **shadcn/ui GitHub** | https://github.com/shadcn-ui/ui — 소스 코드, 이슈 |
| **shadcn 블록** | https://ui.shadcn.com/blocks — 페이지 템플릿 |
| **awesome-shadcn-ui** | https://github.com/shadcn-ui/awesome-shadcn-ui — 트렌디한 예제, 확장 |
| **공식 예제** | 최신 트렌드, 커뮤니티 패턴 학습 후 적용 |

**AI 개발 시**: shadcn/ui 공식 문서, GitHub, awesome-shadcn-ui 등에서 **최신 트렌드와 예제를 학습**하여 구현할 것. 구식 패턴 사용 금지.

---

## 3. 프로젝트 구조

### 3.1 디렉터리 구조

**워크스페이스 = 로컬 웹서버 기본 폴더** (`/Users/awekers/Sites/www`)

```
/Users/awekers/Sites/www/             # 프로젝트 루트 (nginx 문서 루트)
├── frontend/                         # Next.js 앱
│   ├── app/
│   │   ├── page.tsx                  # 1단계
│   │   ├── step2/
│   │   │   └── page.tsx              # 2단계
│   │   ├── step3/
│   │   │   └── page.tsx              # 3단계
│   │   ├── build/
│   │   │   └── [id]/
│   │   │       └── page.tsx          # 빌드 상태/다운로드
│   │   └── layout.tsx
│   ├── components/
│   │   ├── ui/                      # shadcn/ui 컴포넌트 (components.json)
│   │   │   ├── button.tsx
│   │   │   ├── input.tsx
│   │   │   ├── card.tsx
│   │   │   └── ...
│   │   ├── Step1Form.tsx
│   │   ├── Step2Form.tsx
│   │   ├── Step3Preview.tsx
│   │   └── MobileSimulator.tsx
│   ├── lib/
│   │   └── api.ts                    # Laravel API 호출
│   └── package.json
│
├── backend/                          # Laravel 앱
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   └── BuildController.php
│   │   ├── Jobs/
│   │   │   └── ProcessBuildJob.php
│   │   └── Services/
│   │       ├── BuildConfigService.php   # 자동 생성 로직
│   │       └── CapacitorBuildService.php
│   ├── routes/
│   │   └── api.php
│   ├── database/migrations/
│   │   └── xxxx_create_builds_table.php
│   └── storage/
│       └── app/
│           ├── builds/               # APK, IPA 저장
│           ├── uploads/              # 로고, 스플래시 업로드
│           └── build-templates/       # Capacitor 템플릿
│               └── webview-app/       # 기본 템플릿
│
├── nginx/                            # nginx 설정 (www.conf include)
│   └── webview-builder.conf
└── docs/                             # 부가 문서
    ├── DATABASE.md                   # 데이터베이스 테이블 구축
    ├── ENVIRONMENT_SETUP.md          # 환경 설정 (nginx, localhost)
    └── backend.env.example           # Laravel .env 템플릿
```

**문서 위치**: 핵심 문서(PROJECT_OVERVIEW, DEV_SPEC, TECH_STACK, AI_COLLABORATION_PROCESS)는 **루트**에 위치. 부가 문서는 **docs/**.

### 3.2 워크스페이스 경로

| 항목 | 경로 |
|------|------|
| **프로젝트 루트** | `/Users/awekers/Sites/www` |
| **설명** | 로컬 웹서버(nginx) 기본 폴더. **http://localhost/** 로 접근 |

### 3.3 로컬 실행 방식 (localhost)

| 경로 | 처리 | 접근 |
|------|------|------|
| `/` | Next.js (nginx → 127.0.0.1:3000 프록시) | http://localhost/ |
| `/api` | Laravel (backend/public) | http://localhost/api |
| `/storage` | 업로드 파일 | http://localhost/storage |

**시작 순서**: nginx, PHP-FPM → `npm run dev` (frontend) → `php artisan queue:work` (빌드 시)

---

## 4. 사용자 흐름 (3단계)

### 4.1 1단계 — 최소 정보 수집

**경로**: `/` 또는 `/step1`

| 입력 항목 | 필수 | 타입 | 설명 |
|-----------|------|------|------|
| `webUrl` | ✅ | string (URL) | 로드할 도메인 (https://example.com) |
| `appIcon` | ✅ | File | 앱 아이콘 (1024x1024 권장) |
| `splashImage` | △ | File | 스플래시 이미지 (선택) |
| `appType` | ✅ | enum | `webview` \| `hybrid` |
| (hybrid 시) `pushConfig` | ✅ | object | FCM/APNs 설정 |
| (hybrid 시) `nativeFeatures` | △ | array | `['location','camera','file']` 등 |

**유효성 검사**:
- `webUrl`: 유효한 URL 형식, https 권장
- `appIcon`: 이미지 파일 (png, jpg), 최소 512x512

### 4.2 2단계 — 자동 생성 + 수정

**경로**: `/step2`

**1단계 데이터를 기반으로 자동 생성하는 값** (모두 수정 가능):

| 필드 | 자동 생성 규칙 | 예시 |
|------|----------------|------|
| `appName` | 도메인에서 추출, 첫 글자 대문자 | myplatform.com → Myplatform |
| `packageId` | 도메인 역순 + .app | com.myplatform.app |
| `privacyPolicyUrl` | `{webUrl}/privacy` | https://myplatform.com/privacy |
| `supportUrl` | `{webUrl}/contact` 또는 `{webUrl}` | https://myplatform.com/contact |
| `versionName` | `1.0.0` | 1.0.0 |
| `versionCode` | `1` | 1 |

**표시 형식**: 각 필드에 입력란 + "자동 생성됨" 표시, 사용자가 수정 가능

### 4.3 3단계 — 시뮬레이션 + 최종 확인

**경로**: `/step3`

| 표시 | 설명 |
|------|------|
| **모바일 시뮬레이터** | iPhone/Android 프레임 내부에 스플래시 → 웹 로드 화면 순서로 미리보기 |
| **요약 정보** | 앱 이름, 패키지 ID, URL 등 최종 확인 |
| **확인 버튼** | "빌드 시작" 클릭 시 빌드 요청 |

---

## 5. API 명세

### 5.1 기본 정보

| 항목 | 값 |
|------|-----|
| **Base URL** | `http://localhost/api` (로컬) |
| **Content-Type** | `application/json` |
| **인증** | MVP에서는 생략 (추후 Sanctum 적용 가능) |

### 5.2 엔드포인트

#### POST /api/build

빌드 요청 생성.

**Request Body**:
```json
{
  "step1": {
    "webUrl": "https://myplatform.com",
    "appType": "webview",
    "appIconPath": "uploads/xxx/icon.png",
    "splashImagePath": "uploads/xxx/splash.png"
  },
  "step2": {
    "appName": "Myplatform",
    "packageId": "com.myplatform.app",
    "privacyPolicyUrl": "https://myplatform.com/privacy",
    "supportUrl": "https://myplatform.com/contact",
    "versionName": "1.0.0",
    "versionCode": 1
  }
}
```

**Response** (201):
```json
{
  "buildId": "uuid-xxx",
  "status": "queued",
  "message": "빌드가 큐에 등록되었습니다."
}
```

#### GET /api/build/{buildId}

빌드 상태 조회.

**Response** (200):
```json
{
  "buildId": "uuid-xxx",
  "status": "queued|building|completed|failed",
  "progress": 50,
  "message": "Android 빌드 중...",
  "artifacts": {
    "apk": "/api/build/xxx/download/apk",
    "ipa": "/api/build/xxx/download/ipa",
    "keystore": "/api/build/xxx/download/keystore"
  },
  "createdAt": "2026-02-27T10:00:00Z",
  "completedAt": null
}
```

#### GET /api/build/{buildId}/download/{type}

빌드 결과물 다운로드. `type`: `apk` | `ipa` | `keystore`

**Response**: 파일 스트림 (Content-Disposition: attachment)

#### POST /api/upload

파일 업로드 (로고, 스플래시). `public` 디스크 사용 (storage/app/public).

**Request**: `multipart/form-data`, 필드명: `file`

**Response** (200):
```json
{
  "path": "uploads/xxx/filename.png",
  "url": "/storage/uploads/xxx/filename.png"
}
```

#### GET /api/upload/preview?path=uploads/xxx/file.png

업로드 파일 미리보기 (private/public 둘 다 지원). 3단계 아이콘 미리보기용.

**Response**: 이미지 스트림 (Content-Type: image/png 등)

#### POST /api/build/generate-step2

1단계 데이터로 2단계 추천값 생성 (프론트엔드에서 호출).

**Request Body**:
```json
{
  "webUrl": "https://myplatform.com"
}
```

**Response** (200):
```json
{
  "appName": "Myplatform",
  "packageId": "com.myplatform.app",
  "privacyPolicyUrl": "https://myplatform.com/privacy",
  "supportUrl": "https://myplatform.com/contact",
  "versionName": "1.0.0",
  "versionCode": 1
}
```

---

## 6. 데이터베이스 스키마

> **상세**: 테이블 구축 방법, 마이그레이션 절차는 **docs/DATABASE.md** 참조.

### 6.1 builds 테이블

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | uuid (PK) | 빌드 고유 ID |
| `status` | enum | queued, building, completed, failed |
| `app_type` | enum | webview, hybrid |
| `web_url` | string | 로드할 URL |
| `app_name` | string | 앱 이름 |
| `package_id` | string | 패키지 ID |
| `version_name` | string | 1.0.0 |
| `version_code` | int | 1 |
| `privacy_policy_url` | string | |
| `support_url` | string | |
| `app_icon_path` | string | |
| `splash_image_path` | string | nullable |
| `config_json` | json | 기타 설정 (화면 방향, 푸시 설정 등) |
| `apk_path` | string | nullable, 완료 시 |
| `ipa_path` | string | nullable, 완료 시 |
| `keystore_path` | string | nullable, Android용 |
| `error_message` | text | nullable, 실패 시 |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `completed_at` | timestamp | nullable |

### 6.2 데이터베이스 환경 (로컬)

| 항목 | 값 |
|------|-----|
| **DB 이름** | `webview_builder` |
| **DB 엔진** | MariaDB 10.11.x |
| **문자셋** | utf8mb4 |
| **콜레이션** | utf8mb4_unicode_ci |

**접속 정보** (iMac 로컬):

| 항목 | 값 |
|------|-----|
| DB_CONNECTION | mariadb |
| DB_HOST | 127.0.0.1 |
| DB_PORT | 3306 |
| DB_DATABASE | webview_builder |
| DB_USERNAME | awekers |
| DB_PASSWORD | (기존 계정 비밀번호 사용) |

> **참고**: 소켓 접속이 실패할 경우 `DB_HOST=127.0.0.1`로 TCP 접속 사용.

---

## 7. 빌드 프로세스

### 7.1 Capacitor 템플릿 구조

```
build-templates/webview-app/
├── www/
│   └── index.html          # window.location.href = "{WEB_URL}"
├── android/
├── ios/
├── capacitor.config.json   # appId, appName 등 주입
└── package.json
```

### 7.2 빌드 단계 (동기 실행, Bus::dispatchSync)

1. **템플릿 복사**: `webview-app` → `builds/{buildId}/project/`
2. **설정 주입**: `capacitor.config.json`, `www/index.html`, build.gradle, strings.xml 등
3. **아이콘/스플래시**: 업로드 이미지를 Android 공식 규격으로 리사이즈 후 mipmap 복사 (PHP GD)
4. **Keystore 생성**: `keytool -genkey` (Android용, release.keystore)
5. **npm install**: `npm install` (타임아웃 180초)
6. **Capacitor 동기화**: `npx cap sync android` (타임아웃 120초)
7. **Android 빌드**: `./gradlew assembleRelease` (타임아웃 300초)
8. **결과물 이동**: APK, Keystore → `storage/app/builds/{buildId}/`
9. **DB 업데이트**: status=completed, apk_path, keystore_path 저장

> **참고**: `npx cap build android` 대신 `./gradlew assembleRelease` 사용. `local.properties`에 sdk.dir 자동 설정.

### 7.3 설정 주입 예시

**capacitor.config.json**:
```json
{
  "appId": "{{PACKAGE_ID}}",
  "appName": "{{APP_NAME}}",
  "webDir": "www",
  "server": {
    "url": "{{WEB_URL}}",
    "cleartext": true
  }
}
```

**www/index.html** (서버 URL 사용 시):
```html
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body>
  <script>
    window.location.href = "{{WEB_URL}}";
  </script>
</body>
</html>
```

---

## 8. 자동 생성 로직 (BuildConfigService)

### 8.1 도메인 → 앱 이름

```
입력: https://myplatform.com
처리: 호스트에서 "myplatform" 추출 → "Myplatform" (첫 글자 대문자)
출력: Myplatform
```

### 8.2 도메인 → 패키지 ID

```
입력: https://myplatform.com
처리: 호스트에서 "myplatform" 추출 → "com.myplatform.app"
규칙: com.{도메인}.app (소문자, 특수문자 제거)
출력: com.myplatform.app
```

### 8.3 도메인 → URL

```
privacyPolicyUrl: {origin}/privacy
supportUrl: {origin}/contact 또는 {origin}
```

---

## 9. 프론트엔드 구현 가이드

### 9.1 상태 관리

- **1→2→3 단계 데이터**: React state 또는 URL search params로 전달
- **빌드 ID**: 3단계 확인 후 API 응답으로 받아 `/build/[id]`로 이동

### 9.2 단계별 라우팅

| 단계 | 경로 | 이전 | 다음 |
|------|------|------|------|
| 1 | `/` | - | `/step2` |
| 2 | `/step2` | `/` | `/step3` |
| 3 | `/step3` | `/step2` | `/build/[id]` |
| 결과 | `/build/[id]` | `/step3` | - |

### 9.3 모바일 시뮬레이터 (3단계)

- **shadcn/ui** Card, 기타 컴포넌트로 모바일 프레임 구현
- iPhone/Android 프레임: CSS 또는 shadcn 스타일로 구현
- 내부에 iframe 또는 div로 `webUrl` 로드 (CORS 주의)
- 모바일 우선: 시뮬레이터가 메인 뷰, 데스크톱에서는 중앙 정렬

---

## 10. 필수 업무 진행 방법 (AI 준수)

> **AI 개발자**: 이 섹션의 순서와 절차를 **반드시** 따를 것. 단계를 건너뛰거나 순서를 바꾸지 말 것.

---

### 10.1 Phase 1: 프로젝트 초기화

| 순서 | 작업 | 명령/방법 | 완료 조건 |
|------|------|-----------|-----------|
| 1-1 | 프로젝트 루트 확인 | 워크스페이스: `/Users/awekers/Sites/www` | `frontend/`, `backend/`, `docs/` 폴더 구조 준비 |
| 1-2 | Laravel 프로젝트 생성 | `backend/` 폴더에 `composer create-project laravel/laravel .` | Laravel 앱 동작 확인 |
| 1-2a | Laravel DB 설정 | `backend/.env`에 10.10 환경 변수 반영. `docs/backend.env.example` 참조. DB_DATABASE=webview_builder | `php artisan migrate` 성공 |
| 1-3 | Next.js 프로젝트 생성 | `frontend/` 폴더에 `npx create-next-app@latest .` (App Router, TypeScript, Tailwind) | Next.js 앱 동작 확인 |

---

### 10.2 Phase 2: shadcn/ui 설치 (필수, 초기에 완료)

| 순서 | 작업 | 명령 | 완료 조건 |
|------|------|------|-----------|
| 2-1 | shadcn/ui 초기화 | `cd frontend && npx shadcn@latest init` | `components.json` 생성 |
| 2-2 | **필수 컴포넌트 일괄 추가** | 아래 명령 실행 | `components/ui/` 하위에 컴포넌트 파일 생성됨 |

**실행할 명령 (한 번에)**:
```bash
cd frontend
npx shadcn@latest add button input card label form dialog alert progress separator tabs badge avatar skeleton sonner
```

| 컴포넌트 | 용도 |
|----------|------|
| button, input, label, form | 폼 입력 |
| card | 레이아웃, 카드 UI |
| dialog | 모달, 확인 창 |
| alert | 에러/안내 메시지 |
| progress | 빌드 진행률 |
| separator | 구분선 |
| tabs | 탭 전환 (필요 시) |
| badge | 상태 표시 |
| avatar | 프로필/아이콘 미리보기 |
| skeleton | 로딩 스켈레톤 |
| sonner | 토스트 알림 |

**규칙**: 개발 중 추가 컴포넌트가 필요하면 `npx shadcn@latest add [컴포넌트명]` 실행. **shadcn/ui 외 다른 UI 라이브러리 사용 금지.**

---

### 10.3 Phase 3: 백엔드 구축

| 순서 | 작업 | 내용 | 완료 조건 |
|------|------|------|-----------|
| 3-1 | 마이그레이션 생성 | `builds` 테이블. **docs/DATABASE.md** 3.4 예시 참조 | `php artisan migrate` 성공 |
| 3-2 | Build 모델 생성 | Eloquent Model | `app/Models/Build.php` |
| 3-3 | API 라우트 등록 | 5. API 명세 참조 | `routes/api.php` |
| 3-4 | BuildController | POST /api/build, GET /api/build/{id}, GET /api/build/{id}/download/{type} | API 응답 확인 |
| 3-5 | UploadController | POST /api/upload | 파일 업로드 동작 |
| 3-6 | BuildConfigService | 도메인 → 앱 이름, 패키지 ID 등 자동 생성 (8. 자동 생성 로직 참조) | 2단계 API 응답 확인 |
| 3-7 | ProcessBuildJob | Queue Job, Capacitor 빌드 실행 | Job 디스패치 동작 |
| 3-8 | Queue 설정 | Redis 또는 database driver | `php artisan queue:work` 실행 가능 |

---

### 10.4 Phase 4: 프론트엔드 구축 (shadcn/ui만 사용)

| 순서 | 작업 | 경로 | 완료 조건 |
|------|------|------|-----------|
| 4-1 | 1단계 페이지 | `app/page.tsx` | 도메인, 로고 입력, 2단계 이동 |
| 4-2 | 2단계 페이지 | `app/step2/page.tsx` | 자동 생성값 표시·수정, 3단계 이동 |
| 4-3 | 3단계 페이지 | `app/step3/page.tsx` | 시뮬레이션, 최종 확인, 빌드 요청 |
| 4-4 | 빌드 결과 페이지 | `app/build/[id]/page.tsx` | 상태 폴링, 다운로드 링크 |
| 4-5 | API 연동 | `lib/api.ts` | Laravel API 호출 |
| 4-6 | 모바일 우선 스타일 | 모든 페이지 | 375px~430px 기준 레이아웃 |

**UI 규칙**: `components/ui/`의 shadcn 컴포넌트만 사용. 커스텀 컴포넌트도 shadcn 패턴(Tailwind, Radix) 따를 것.

---

### 10.5 Phase 5: Capacitor 템플릿 및 빌드

> **빌드 환경**: APK 빌드 시 Java JDK 17, Android SDK 필요. → **docs/BUILD_ENVIRONMENT.md** 참조.

| 순서 | 작업 | 내용 | 완료 조건 |
|------|------|------|-----------|
| 5-1 | Capacitor 템플릿 생성 | `backend/storage/app/build-templates/webview-app/` | `www/`, `android/`, `capacitor.config.json` |
| 5-2 | ProcessBuildJob 구현 | 템플릿 복사 → 설정 주입 → npm install → cap sync → cap build | APK 파일 생성 |
| 5-3 | Keystore 자동 생성 | keytool로 Android 서명용 | 서명된 APK 출력 |

---

### 10.6 진행 순서 요약 (AI 체크리스트)

```
[ ] Phase 1: Laravel + Next.js 초기화
[ ] Phase 2: shadcn/ui init + 필수 컴포넌트 일괄 추가
[ ] Phase 3: Laravel API, Job, Service 구현
[ ] Phase 4: Next.js 페이지 (1→2→3→build) 구현, shadcn/ui만 사용
[ ] Phase 5: Capacitor 템플릿, ProcessBuildJob 빌드 로직
```

**위 순서를 지키고, 각 Phase 완료 후 다음 Phase로 진행할 것.**

---

### 10.7 디자인 표준 (최우선 준수)

| 규칙 | 내용 |
|------|------|
| **UI 라이브러리** | **shadcn/ui만** 사용. Flowbite, MUI, Chakra 등 사용 금지 |
| **디자인 원칙** | **모바일 우선**. 375px~430px 기준 우선 설계 |
| **품질** | **네이티브 앱 수준** (iOS HIG, Material Design 참고) |
| **학습 소스** | shadcn/ui 공식 문서, GitHub, awesome-shadcn-ui에서 **최신 트렌드** 학습 후 구현 |

**참조 URL**: https://ui.shadcn.com | https://github.com/shadcn-ui/ui | https://github.com/shadcn-ui/awesome-shadcn-ui

### 10.8 네이밍 규칙

| 구분 | 규칙 | 예시 |
|------|------|------|
| **컴포넌트** | PascalCase | Step1Form, MobileSimulator |
| **API 라우트** | kebab-case | /api/build, /api/upload |
| **DB 테이블** | snake_case 복수형 | builds |
| **Laravel 모델** | PascalCase 단수형 | Build |

### 10.9 에러 처리

- API: HTTP 상태 코드 + `{ "error": "메시지" }` 형식
- 프론트: try-catch, 사용자에게 명확한 메시지 표시

### 10.10 환경 변수

**Laravel (.env)**:
```
APP_URL=http://localhost
APP_ENV=local

# 데이터베이스 (webview_builder)
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webview_builder
DB_USERNAME=awekers
DB_PASSWORD=

# 빌드
BUILD_TEMPLATE_PATH=storage/app/build-templates/webview-app
BUILD_OUTPUT_PATH=storage/app/builds

# Redis (Queue/Cache - 선택)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=database
CACHE_STORE=database
```

> **비밀번호**: 기존 iMac MariaDB 계정(awekers) 비밀번호 사용. DB_PASSWORD는 보안상 .env에만 입력.

**Next.js (.env.local)**:
```
NEXT_PUBLIC_API_URL=http://localhost/api
```

---

## 11. MVP 범위 (1차 구현)

| 포함 | 제외 |
|------|------|
| 일반 웹뷰만 | 하이브리드 앱 |
| Android APK만 | iOS IPA |
| 3단계 흐름 | 회원/인증 |
| Keystore 자동 생성 | 사용자 Keystore 업로드 |
| 로컬 실행 | 배포 설정 |

---

## 11.1 구현 현황 (웹뷰 APK 빌드)

| 구분 | 상태 | 비고 |
|------|------|------|
| **1단계** | ✅ 완료 | 웹 URL, 앱 아이콘, 스플래시 업로드 |
| **2단계** | ✅ 완료 | 자동 생성값 확인·수정 |
| **3단계** | ✅ 완료 | 시뮬레이션, APK 적용 아이콘 미리보기, 빌드 요청 |
| **빌드** | ✅ 완료 | Capacitor 템플릿, Android APK, Keystore 자동 생성 |
| **다운로드** | ✅ 완료 | APK, Keystore 다운로드 |

**알려진 이슈**:
- 3단계 아이콘 미리보기 비율/여백이 실제 APK와 다름 (추후 개선 예정)

**다음 단계**:
- 웹뷰 아이콘 및 추가 사항 테스트
- 하이브리드 앱 빌드로 확장

---

## 12. 검증 체크리스트

개발 완료 후 확인할 항목:

- [x] 1단계: 도메인, 로고 입력 후 2단계 이동
- [x] 2단계: 자동 생성값 표시, 수정 가능, 3단계 이동
- [x] 3단계: 시뮬레이션 표시, APK 적용 아이콘 미리보기, 확인 후 빌드 요청
- [x] API: POST /api/build → buildId 반환 (동기 실행)
- [x] API: GET /api/build/{id} → status, artifacts
- [x] 2~5분 소요 후 APK 파일 생성
- [x] 다운로드: APK, Keystore 다운로드 가능

---

## 13. 참조 문서

| 문서 | 경로 |
|------|------|
| 프로젝트 개요 | PROJECT_OVERVIEW.md |
| **프로젝트 현황** | docs/PROJECT_STATUS.md (완료 기능, 다음 단계) |
| 기술 스택 아키텍처 | TECH_STACK.md |
| AI 협업 프로세스 | AI_COLLABORATION_PROCESS.md |
| **데이터베이스** | docs/DATABASE.md (테이블 구축, 마이그레이션) |
| **환경 설정 방안** | docs/ENVIRONMENT_SETUP.md (nginx, 개발 실행 방식) |
| **APK 빌드 환경** | docs/BUILD_ENVIRONMENT.md (Java, Android SDK 설치) |
| 백엔드 환경 변수 템플릿 | docs/backend.env.example |
| **shadcn/ui** | https://ui.shadcn.com |
| **shadcn/ui GitHub** | https://github.com/shadcn-ui/ui |
| **awesome-shadcn-ui** | https://github.com/shadcn-ui/awesome-shadcn-ui |
| Capacitor 문서 | https://capacitorjs.com/docs |
| Laravel Queue | https://laravel.com/docs/queues |

---

## 14. 변경 이력

| 날짜 | 내용 |
|------|------|
| 2026-02-27 | 데이터베이스 환경 추가 (webview_builder, 6.2, 10.10) |
| 2026-02-27 | 최초 작성 (AI 개발용 명세서) |
| 2026-02-27 | 2.3 디자인 표준 추가 (shadcn/ui 전용, 모바일 우선, 네이티브 앱 수준, AI 학습 참조 소스) |
| 2026-02-27 | 10. 필수 업무 진행 방법 추가 (Phase 1~5, shadcn/ui 초기 컴포넌트 일괄 추가, AI 체크리스트) |
| 2026-02-27 | 11.1 구현 현황 추가 (웹뷰 APK 빌드 완성), 12. 검증 체크리스트 완료 표시, 7.2 빌드 단계 실제 구현 반영 |
