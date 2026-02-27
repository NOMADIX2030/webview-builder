# 프로젝트 현황

> **최종 업데이트**: 2026년 2월 27일 (안드로이드 테스트 완료)

---

## 1. 현재 상태

### 1.0 UI 마이그레이션 (Next.js → Laravel Blade)

| 구분 | 내용 |
|------|------|
| **프론트엔드** | Laravel Blade + Tailwind (서버사이드 렌더링) |
| **경로** | `/` → /build/step1, `/build/step2`, `/build/step3`, `/build/{id}` |
| **nginx** | `/` → Laravel `public`, `/api` → Laravel API |
| **세션** | `build_step1`, `build_step2` (폼 데이터) |

### 1.1 완료된 기능 (웹뷰 APK 빌드)

| 구분 | 내용 |
|------|------|
| **1단계** | 웹 URL, 앱 아이콘(512×512 권장), 스플래시 업로드 |
| **2단계** | 도메인 기반 자동 생성값(앱 이름, 패키지 ID 등) 확인·수정 |
| **3단계** | 모바일 시뮬레이션, APK 적용 아이콘 미리보기, 빌드 요청 |
| **빌드** | Capacitor 템플릿, @capacitor/assets 아이콘 생성(PHP GD 폴백), Android APK, Keystore 자동 생성 (동기 실행, 2~5분) |
| **다운로드** | APK, Keystore 다운로드 (X-Accel-Redirect → nginx 직접 서빙) |

### 1.2 적용된 설정·검증 완료 항목

| 항목 | 설정 | 비고 |
|------|------|------|
| **아이콘 미리보기** | `rounded-[22%]` (스쿼시클), `object-cover`, `bg-white` | 실제 APK와 90% 이상 일치 (검증 완료) |
| **뒤로가기** | `goBack()` → 서브 경로면 origin 이동 → 루트에서 뒤로가기 2회 연속 시 종료 (알림 없음, 2초 이내) | 앱 내 이전 페이지 이동 + 이중 클릭 종료 |
| **아이콘 생성** | `@capacitor/assets` 1차 시도, 실패 시 PHP GD 폴백 | `npx @capacitor/assets generate --android` |
| **다운로드** | `<a download>` + 상대 경로 → Laravel X-Accel-Redirect → nginx internal location | PHP 버퍼 없음, 전체 파일 |
| **빌드 템플릿** | `node_modules` 복사 후 삭제 → `npm install`로 새로 생성 | 깨진 심볼릭 링크로 copy 실패 방지 |

### 1.3 알려진 이슈

| 이슈 | 설명 | 우선순위 |
|------|------|----------|
| 3단계 아이콘 미리보기 | 기기별 마스크(원형/스쿼시클/둥근 사각형)로 100% 일치 불가 | 낮음 (90% 이상 동일로 검증 완료) |

---

## 2. 다음 단계 계획

| 순서 | 작업 | 비고 |
|------|------|------|
| 1 | 웹뷰 아이콘 및 추가 사항 테스트 | 3단계 미리보기 비율·여백 등 |
| 2 | 하이브리드 앱 빌드로 확장 | 푸시, 네이티브 기능 등 |

---

## 3. 참조 문서

| 문서 | 용도 |
|------|------|
| DEV_SPEC.md | 구현 명세, API, 빌드 절차 |
| BUILD_ENVIRONMENT.md | Java, Android SDK, Keystore 검증 |
| ENVIRONMENT_SETUP.md | nginx, 로컬 실행 방식 |
| DATABASE.md | 데이터베이스 테이블 |
