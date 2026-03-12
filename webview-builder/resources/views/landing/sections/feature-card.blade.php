@php
    $title = $feature['title'] ?? '';
    $url   = $feature['url'] ?? '#';
    $icon  = $feature['icon'] ?? 'device-phone-mobile';
@endphp
<div class="col">
    <a href="{{ $url }}"
        class="feature-card d-flex flex-column align-items-center justify-content-center gap-2 rounded-3 bg-white p-3 h-100 shadow-sm">
        <span class="icon-wrap d-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary"
            style="width:44px;height:44px;">
            @if($icon === 'chat-bubble-left-right')
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.08-.902-.455-1.226a5.969 5.969 0 0 1-.41-3.658 9.764 9.764 0 0 1 2.555-.337C9.97 3.75 17.03 3.75 21 8.25v3.75Z" />
            </svg>
            @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            @endif
        </span>
        <span class="fw-semibold text-center text-dark" style="font-size:0.75rem; line-height:1.3;">{{ $title }}</span>
    </a>
</div>
