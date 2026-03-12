<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AI 채팅')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @stack('styles')
    <style>
        :root {
            --chat-bg: #ffffff;
            --chat-user-bubble: #0ea5e9;
            --chat-user-text: #fff;
            --chat-ai-bubble: #f1f5f9;
            --chat-ai-text: #0f172a;
            --chat-input-bg: #f8fafc;
            --chat-input-border: #e2e8f0;
            --chat-sidebar-bg: #f8fafc;
        }
        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background: var(--chat-bg);
            color: var(--chat-ai-text);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            flex-shrink: 0;
            height: 56px;
            border-bottom: 1px solid var(--chat-input-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            background: var(--chat-bg);
        }
        .chat-header-title { font-weight: 600; font-size: 1rem; }
        .chat-main {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .chat-messages {
            flex: 1;
            min-height: min-content;
            padding: 1rem;
            max-width: 768px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        .chat-message {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            animation: msgFade 0.25s ease;
        }
        @media (prefers-reduced-motion: reduce) {
            .chat-message { animation: none; }
        }
        @keyframes msgFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .chat-message.user { flex-direction: row-reverse; }
        .chat-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        .chat-message.user .chat-avatar { background: var(--chat-user-bubble); color: #fff; }
        .chat-message.assistant .chat-avatar { background: #94a3b8; color: #fff; }
        .chat-bubble-wrap {
            max-width: 85%;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }
        .chat-message.assistant .chat-bubble-wrap { align-items: flex-start; }
        .chat-time {
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .chat-bubble {
            padding: 0.75rem 1rem;
            border-radius: 18px;
            font-size: 0.9rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .chat-message.user .chat-bubble {
            background: var(--chat-user-bubble);
            color: var(--chat-user-text);
            border-bottom-right-radius: 4px;
        }
        .chat-message.assistant .chat-bubble {
            background: var(--chat-ai-bubble);
            color: var(--chat-ai-text);
            border: 1px solid var(--chat-input-border);
            border-bottom-left-radius: 4px;
        }
        .chat-typing {
            display: inline-flex;
            gap: 4px;
            padding: 0.5rem 0;
        }
        .chat-typing span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #94a3b8;
            animation: typing 1.4s ease-in-out infinite both;
        }
        .chat-typing span:nth-child(1) { animation-delay: 0s; }
        .chat-typing span:nth-child(2) { animation-delay: 0.2s; }
        .chat-typing span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
        .chat-input-wrap {
            flex-shrink: 0;
            padding: 1rem;
            background: var(--chat-bg);
            border-top: 1px solid var(--chat-input-border);
        }
        .chat-input-inner {
            max-width: 768px;
            margin: 0 auto;
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            background: var(--chat-input-bg);
            border: 1px solid var(--chat-input-border);
            border-radius: 24px;
            padding: 0.5rem 0.5rem 0.5rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .chat-input-inner:focus-within {
            border-color: #94a3b8;
            box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.2);
        }
        .chat-input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            resize: none;
            min-height: 24px;
            max-height: 160px;
        }
        .chat-input:focus { outline: none; }
        .chat-input::placeholder { color: #94a3b8; }
        .chat-send {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--chat-user-bubble);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .chat-send:hover { background: #0284c7; }
        .chat-send:disabled { background: #cbd5e1; cursor: not-allowed; }
        .chat-empty {
            flex: 1;
            min-height: 12rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }
        .chat-scroll-to-bottom {
            position: fixed;
            bottom: 6.5rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            border: 1px solid var(--chat-input-border);
            background: var(--chat-bg);
            color: #64748b;
            font-size: 0.8rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 10;
        }
        .chat-scroll-to-bottom:hover {
            background: var(--chat-input-bg);
            color: var(--chat-ai-text);
        }
    </style>
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
