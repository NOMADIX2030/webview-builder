@extends('layouts.chat')

@section('title', 'AI 채팅')

@section('content')
<div class="chat-header">
    <a href="{{ route('landing.index') }}" class="text-decoration-none text-secondary d-flex align-items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:20px;height:20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        <span>뒤로</span>
    </a>
    <span class="chat-header-title">AI 채팅</span>
    <button type="button" class="btn btn-link text-secondary p-2" id="btnNewChat" title="새 대화">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    </button>
</div>

<div class="chat-main" id="chatScrollContainer">
    <div class="chat-messages" id="chatMessages" role="log" aria-live="polite" aria-label="대화 내용">
        {{-- 빈 상태 / 메시지 목록 --}}
    </div>
</div>

<div class="chat-input-wrap">
    <div class="chat-input-inner">
        <textarea class="chat-input" id="chatInput" rows="1" placeholder="메시지를 입력하세요..." autofocus aria-label="메시지 입력"></textarea>
        <button type="button" class="chat-send" id="btnSend" aria-label="전송">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
            </svg>
        </button>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const scrollEl = document.getElementById('chatScrollContainer');
    const messagesEl = document.getElementById('chatMessages');
    const inputEl = document.getElementById('chatInput');
    const btnSend = document.getElementById('btnSend');
    const btnNewChat = document.getElementById('btnNewChat');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let history = [];
    let isComposing = false;
    const SCROLL_THRESHOLD = 80;

    function isAtBottom() {
        if (!scrollEl) return true;
        return scrollEl.scrollTop + scrollEl.clientHeight >= scrollEl.scrollHeight - SCROLL_THRESHOLD;
    }

    function scrollToBottom(force) {
        if (!scrollEl) return;
        if (force || isAtBottom()) {
            scrollEl.scrollTop = scrollEl.scrollHeight;
        }
    }

    function updateScrollToBottomButton() {
        let btn = document.getElementById('btnScrollToBottom');
        if (history.length === 0) {
            if (btn) btn.remove();
            return;
        }
        const show = !isAtBottom();
        if (show) {
            if (!btn) {
                btn = document.createElement('button');
                btn.id = 'btnScrollToBottom';
                btn.type = 'button';
                btn.className = 'chat-scroll-to-bottom';
                btn.setAttribute('aria-label', '맨 아래로');
                btn.textContent = '새 메시지 보기';
                btn.onclick = () => { scrollToBottom(true); updateScrollToBottomButton(); };
                document.body.appendChild(btn);
            }
            btn.style.display = 'block';
        } else if (btn) {
            btn.style.display = 'none';
        }
    }

    function formatTime(d) {
        const h = d.getHours(), m = d.getMinutes();
        return (h < 12 ? '오전 ' : '오후 ') + (h % 12 || 12) + ':' + String(m).padStart(2, '0');
    }

    function renderMessages() {
        if (history.length === 0) {
            messagesEl.innerHTML = '<div class="chat-empty">무엇이든 물어보세요. AI가 도와드립니다.</div>';
            updateScrollToBottomButton();
            return;
        }
        messagesEl.innerHTML = history.map(m => {
            const isUser = m.role === 'user';
            const avatar = isUser ? '👤' : '🤖';
            const bubble = isUser ? escapeHtml(m.content) : linkifyAndEscape(m.content);
            const time = m.time ? formatTime(new Date(m.time)) : '';
            return `<div class="chat-message ${m.role}">
                <div class="chat-avatar">${avatar}</div>
                <div class="chat-bubble-wrap"><div class="chat-bubble">${bubble}</div>${time ? `<div class="chat-time">${time}</div>` : ''}</div>
            </div>`;
        }).join('');
        scrollToBottom(false);
        requestAnimationFrame(() => updateScrollToBottomButton());
    }

    function addTypingIndicator() {
        const wrap = document.createElement('div');
        wrap.className = 'chat-message assistant';
        wrap.id = 'typingIndicator';
        wrap.innerHTML = `
            <div class="chat-avatar">🤖</div>
            <div class="chat-bubble">
                <div class="chat-typing"><span></span><span></span><span></span></div>
            </div>
        `;
        messagesEl.appendChild(wrap);
        scrollToBottom(true);
    }

    function removeTypingIndicator() {
        document.getElementById('typingIndicator')?.remove();
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function linkifyAndEscape(str) {
        const urlRegex = /(https?:\/\/[^\s<>"']+)|(www\.[^\s<>"']+)/g;
        const parts = [];
        let lastIndex = 0;
        let m;
        while ((m = urlRegex.exec(str)) !== null) {
            parts.push({ t: 'text', v: str.slice(lastIndex, m.index) });
            const url = m[0];
            parts.push({ t: 'link', href: url.startsWith('www') ? 'https://' + url : url, text: url });
            lastIndex = m.index + url.length;
        }
        parts.push({ t: 'text', v: str.slice(lastIndex) });
        return parts.map(p => {
            if (p.t === 'text') return escapeHtml(p.v);
            const safe = p.href.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
            return `<a href="${safe}" target="_blank" rel="noopener noreferrer">${escapeHtml(p.text)}</a>`;
        }).join('').replace(/\n/g, '<br>');
    }

    function sendMessage() {
        const text = inputEl.value.trim();
        if (!text || isComposing) return;

        inputEl.value = '';
        inputEl.style.height = 'auto';
        inputEl.focus();

        history.push({ role: 'user', content: text, time: Date.now() });
        renderMessages();

        btnSend.disabled = true;
        addTypingIndicator();

        fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                message: text,
                history: history.slice(0, -1),
            }),
        })
        .then(r => r.json())
        .then(data => {
            removeTypingIndicator();
            if (data.error) {
                history.push({ role: 'assistant', content: '오류: ' + data.error, time: Date.now() });
            } else {
                history.push({ role: 'assistant', content: data.content || '(응답 없음)', time: Date.now() });
            }
            renderMessages();
        })
        .catch(() => {
            removeTypingIndicator();
            history.push({ role: 'assistant', content: '연결에 실패했습니다. 다시 시도해 주세요.', time: Date.now() });
            renderMessages();
        })
        .finally(() => { btnSend.disabled = false; inputEl.focus(); });
    }

    inputEl.addEventListener('compositionstart', () => { isComposing = true; });
    inputEl.addEventListener('compositionend', () => { isComposing = false; });

    btnSend.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !isComposing) {
            e.preventDefault();
            sendMessage();
        }
    });

    inputEl.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });

    btnNewChat.addEventListener('click', () => {
        history = [];
        renderMessages();
        document.getElementById('btnScrollToBottom')?.remove();
    });

    let scrollRaf = 0;
    scrollEl?.addEventListener('scroll', () => {
        if (scrollRaf) cancelAnimationFrame(scrollRaf);
        scrollRaf = requestAnimationFrame(() => { updateScrollToBottomButton(); scrollRaf = 0; });
    });

    renderMessages();
})();
</script>
@endpush
@endsection
