# AI 채팅 UX 가이드

> **참조**: GetStream Chat UX, MITRE Chatbot Accessibility Playbook, Vercel AI Chatbot, CopilotKit

---

## 1. 레이아웃: 대화·마지막 글이 입력창 위

**기본 원칙** (GetStream, ChatGPT 패턴):
- 입력창은 항상 화면 하단 고정
- 대화 내용은 입력창 **위** 스크롤 영역에 표시
- 최신 메시지가 입력창 바로 위에 위치 (위→아래: 과거→최신)

**구현**:
- `body`: flex column, `min-height: 0`으로 스크롤 영역 고정
- `chat-main`: flex 1, min-height 0, overflow-y auto (스크롤 컨테이너)
- `chat-messages`: 내용물, 자연스럽게 아래로 늘어남

---

## 2. 스마트 스크롤
- **자동 스크롤**: 사용자가 이미 하단에 있을 때만 새 메시지 시 자동 스크롤
- **맨 아래로 버튼**: 위로 스크롤 시 "새 메시지 보기" 표시, 클릭 시 하단 이동
- 과거 메시지 읽기 중 새 응답이 와도 강제로 내려가지 않음

---

## 3. IME (한글·일본어·중국어) 조합 대응

### 문제
한글 IME 사용 시 **Enter로 글자 확정**을 하는데, 기존에는 이 Enter가 **메시지 전송**으로 처리되어 "줘", "녕" 같은 조각 문자가 별도 메시지로 전송되는 버그 발생.

### 해결
- `keydown` 시 `e.isComposing` 확인
- `compositionstart` / `compositionend`로 수동 `isComposing` 상태 관리
- **조합 중에는 Enter로 전송하지 않음**

```javascript
inputEl.addEventListener('compositionstart', () => { isComposing = true; });
inputEl.addEventListener('compositionend', () => { isComposing = false; });
inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !isComposing) {
        e.preventDefault();
        sendMessage();
    }
});
```

---

## 4. 메시지 타임스탬프
- 각 메시지에 오전/오후 시각 표시
- 대화 맥락 파악에 도움

---

## 5. 접근성 (WCAG)

| 항목 | 적용 |
|------|------|
| 메시지 영역 | `role="log"`, `aria-live="polite"` — 스크린 리더에 새 메시지 알림 |
| 입력창 | `aria-label="메시지 입력"` |
| 전송 버튼 | `aria-label="전송"` |
| 애니메이션 감소 | `prefers-reduced-motion` 시 메시지 페이드 제거 |

---

## 6. 포커스 관리
- 전송 후 입력창에 포커스 유지
- 응답 수신 후에도 입력창으로 포커스 복귀

---

## 7. URL 링크 (새 탭)
- AI 응답 내 `http://`, `https://`, `www.` URL을 감지해 클릭 가능한 링크로 변환
- `target="_blank"`, `rel="noopener noreferrer"` 적용으로 새 탭에서 안전하게 열림

---

## 8. 기타 UX
- 전송 중 버튼 비활성화로 중복 전송 방지
- 빈 문자열·공백만 있는 메시지 전송 방지 (`trim()`)
- Shift+Enter로 줄바꿈, Enter로 전송
