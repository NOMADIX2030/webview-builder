# Laravel Blade 기술 스택 아키텍처 문서

> **작성일**: 2026년 2월 26일  
> **최종 수정**: 2026년 2월 28일  
> **대상**: webview-builder (웹뷰 APK 빌드 서비스) — 추후 블로그, 채팅, 회원 확장 시 참조

---

## 1. 개요

본 문서는 **webview-builder** 프로젝트의 기술 스택을 정의합니다. **Laravel Blade** 단일 앱으로 3단계 폼·빌드·다운로드를 제공하며, **Capacitor**로 Android APK를 생성합니다.

### 1.1 현재 스택 요약

| 구분 | 기술 | 핵심 역할 |
|------|------|-----------|
| **프론트엔드** | Laravel Blade + Tailwind | 3단계 폼 UI, 서버사이드 렌더링 |
| **백엔드** | Laravel (PHP) | API, 빌드 큐, 파일 저장 |
| **DB** | MariaDB 10.x | 빌드 이력, 메타데이터 |
| **빌드 엔진** | Capacitor | 웹뷰 → Android APK |
| **웹 서버** | nginx | Laravel Blade, API 프록시 |

---

## 2. 전체 아키텍처

### 2.1 핵심 아키텍처

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      Laravel Blade (프론트엔드)                           │
├─────────────────────────────────────────────────────────────────────────┤
│  • 1단계: 웹 URL, 앱 아이콘, 스플래시 업로드                              │
│  • 2단계: 자동 생성값 확인·수정                                          │
│  • 3단계: 시뮬레이션, 최종 확인, 빌드 요청                               │
│  • 빌드 상태: 진행률, APK 다운로드                                       │
└───────────────────────────┬─────────────────────────────────────────────┘
                            │ 폼 POST / API
┌───────────────────────────▼─────────────────────────────────────────────┐
│                      Laravel (백엔드)                                     │
├─────────────────────────────────────────────────────────────────────────┤
│  • Web BuildController (Blade 라우트, 세션)                              │
│  • Api BuildController (빌드 API, 다운로드)                               │
│  • ProcessBuildJob (Capacitor 빌드 실행)                                 │
│  • BuildConfigService, CapacitorBuildService                             │
└───────────────────────────┬─────────────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────────────┐
│                MariaDB 10.x (builds 테이블)                               │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 웹 → APK 빌드 구조

```
[사용자] → [Blade 1~3단계] → [Laravel API] → [ProcessBuildJob]
                                                    │
                                                    ▼
                                    [Capacitor 템플릿] → [Android APK]
                                                    │
                                                    ▼
                                    [X-Accel-Redirect] → [nginx] → [다운로드]
```

### 2.3 컴포넌트 관계

```
┌──────────────┐     폼/세션       ┌──────────────┐     Eloquent      ┌──────────────┐
│   Blade      │ ◄────────────────► │   Laravel    │ ◄────────────────► │   MariaDB    │
│   (뷰)       │                    │   (Web/Api)  │                    │   (builds)   │
└──────────────┘                    └──────┬───────┘                    └──────────────┘
                                            │
                                            │ Capacitor 빌드
                                            ▼
                                    ┌──────────────┐
                                    │   APK 출력   │
                                    │   (storage)  │
                                    └──────────────┘
```

---

## 3. Laravel 백엔드 역할

### 3.1 라우트

| 구분 | 경로 | 역할 |
|------|------|------|
| **Web** | `/`, `/build/step1`~`step3`, `/build/{id}` | Blade 폼, 빌드 상태 |
| **API** | `/api/build`, `/api/build/{id}`, `/api/build/{id}/download/{type}` | 빌드 생성, 상태 조회, 다운로드 |

### 3.2 데이터베이스 (MariaDB 10.x)

| 역할 | 설명 |
|------|------|
| **ORM** | Eloquent (Build 모델) |
| **마이그레이션** | builds 테이블 |
| **Laravel 연결** | `mysql` 드라이버로 MariaDB 호환 |

| 테이블 | 용도 |
|--------|------|
| **builds** | 빌드 이력, 상태, APK 경로, 메타데이터 |

### 3.3 빌드 파이프라인

| 구성요소 | 역할 |
|----------|------|
| **ProcessBuildJob** | 동기 실행 (dispatchSync), 2~5분 |
| **CapacitorBuildService** | 템플릿 복사, 설정 주입, npm install, cap build |
| **BuildConfigService** | 도메인 → 앱 이름, 패키지 ID 등 자동 생성 |

### 3.4 기타

| 역할 | 설명 |
|------|------|
| **파일 저장** | 업로드(아이콘, 스플래시), APK, Keystore |
| **세션** | build_step1, build_step2 (폼 데이터) |
| **다운로드** | X-Accel-Redirect → nginx internal location |

---

## 4. Blade 프론트엔드 역할

### 4.1 페이지

| 경로 | 역할 |
|------|------|
| `/` | step1으로 리다이렉트 |
| `/build/step1` | 웹 URL, 앱 아이콘, 스플래시 업로드 |
| `/build/step2` | 자동 생성값 확인·수정 |
| `/build/step3` | 시뮬레이션(iframe), 최종 확인, 빌드 시작 |
| `/build/{id}` | 빌드 상태, 폴링, APK 다운로드 |

### 4.2 스타일

| 기술 | 용도 |
|------|------|
| **Tailwind CSS** | 유틸리티 클래스 |
| **Vite** | CSS/JS 번들 (npm run build) |

### 4.3 Capacitor (앱 빌드)

| 역할 | 설명 |
|------|------|
| **웹뷰 래핑** | 사용자 입력 URL을 WebView에 로드 |
| **APK 출력** | Android APK, Keystore 자동 생성 |
| **템플릿** | `webview-builder/storage/app/build-templates/webview-app/` |

---

## 5. nginx 역할

| 경로 | 처리 |
|------|------|
| `/` | Laravel Blade (try_files → index.php) |
| `/api` | Laravel API |
| `/storage` | 업로드 파일 (storage/app/public) |
| `/internal-download/` | X-Accel-Redirect용 (APK 직접 서빙) |

---

## 6. 기술 스택 요약

| 계층 | 기술 | 버전 |
|------|------|------|
| **프론트엔드** | Laravel Blade + Tailwind | - |
| **백엔드** | Laravel | 11.x |
| **DB** | MariaDB | 10.x |
| **빌드 엔진** | Capacitor | 6.x |
| **웹 서버** | nginx | - |

---

## 7. 추후 확장 시 참조 (블로그, 채팅, 회원)

> **참고**: 아래는 webview-builder에 블로그·채팅·회원 기능을 추가할 때 참조할 수 있는 아키텍처입니다. 현재 MVP에는 미적용.

| 기능 | 추천 기술 |
|------|-----------|
| **블로그** | Blade 또는 별도 Next.js 도입 |
| **실시간 채팅** | Laravel Reverb + Laravel Echo |
| **회원/인증** | Laravel Sanctum |
| **SPA 전환** | 필요 시 Next.js 또는 Inertia.js 재도입 |

---

## 8. AI 코딩 (Cursor) 관점

| 기술 | AI 코딩 적합도 | 이유 |
|------|----------------|------|
| **Laravel** | ⭐⭐⭐⭐⭐ | 관례가 명확, 문서·예제 풍부 |
| **Blade** | ⭐⭐⭐⭐⭐ | 템플릿 문법 단순 |
| **MariaDB** | ⭐⭐⭐⭐⭐ | MySQL 호환, Eloquent 예제와 동일 |
| **Capacitor** | ⭐⭐⭐⭐ | 문서·예제 충분 |

---

## 9. 변경 이력

| 일자 | 내용 |
|------|------|
| 2026-02-28 | Laravel Blade 마이그레이션 반영 (Next.js 제거) |
| 2026-02-26 | 최초 작성 |
| 2026-02-26 | DB를 MariaDB 10.x로 확정, Capacitor 추가 |
