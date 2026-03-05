# FCM 푸시 알림 배지 실시간 갱신 — 적용 완료

> **적용일**: 2026년 3월 6일  
> **요청**: 웹뷰 알림 배지(헤더 종 아이콘 숫자)가 FCM 수신 시 폴링 없이 실시간 갱신되도록 네이티브 연동

---

## 1. 개요

### 1.1 배경

- 기존: 웹에서 배지 갱신을 위해 주기적 서버 조회(폴링) 사용
- 개선: FCM data 수신 시 앱이 WebView에 JavaScript를 주입하여 배지 즉시 갱신

### 1.2 적용 내용

| 구분 | 내용 |
|------|------|
| **MainActivity** | `sInstance`로 포그라운드 시점 추적, `notifyWebViewOfPushReceived(data)` 정적 메서드 추가 |
| **AppFirebaseMessagingService** | `onMessageReceived`에서 data 수신 시 `MainActivity.notifyWebViewOfPushReceived(data)` 호출 |
| **웹 콜백** | `window.__onPushReceived(data)` — 정의 시에만 호출 (Opt-in, 다양한 웹사이트 대응) |

---

## 2. 동작 흐름

```
[서버] FCM data 발송 (unread_count 등 포함)
    ↓
[앱] AppFirebaseMessagingService.onMessageReceived
    ↓
[앱] MainActivity.notifyWebViewOfPushReceived(data)
    ↓ (포그라운드 + WebView 로드됨)
[앱] webView.evaluateJavascript("if(window.__onPushReceived){...}")
    ↓
[웹] window.__onPushReceived({ unread_count, title, body, type })
    ↓
[웹] 헤더 배지 숫자 등 UI 갱신
```

---

## 3. 서버 FCM data 구조 (권장)

```json
{
  "data": {
    "notification_id": "123",
    "type": "system",
    "action_url": "https://example.com/?open_notification=123",
    "title": "새 알림",
    "body": "알림 내용입니다.",
    "unread_count": "5"
  }
}
```

- `unread_count`: 해당 사용자의 현재 미읽음 알림 총 개수 (문자열)

---

## 4. 웹 개발자 가이드

- **상세**: `docs/FCM_WEB_DEVELOPER_GUIDE.md` 섹션 3.8
- **요약**: `window.__onPushReceived = function(data) { ... }` 정의 시 FCM 수신마다 호출됨

---

## 5. 주의 사항

| 항목 | 설명 |
|------|------|
| **포그라운드만** | 앱이 화면에 보일 때만 주입. 백그라운드 수신 시 호출 안 함 |
| **Opt-in** | `window.__onPushReceived` 없으면 호출하지 않음 (에러 없음) |
| **기존 동작 유지** | 네이티브 알림 표시·소리·진동은 그대로 유지 |
