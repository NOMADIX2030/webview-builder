@php
    $title = $feature['title'] ?? '';
    $url   = $feature['url'] ?? '#';
@endphp
<div class="col">
    <a href="{{ $url }}"
        class="feature-card d-flex flex-column align-items-center justify-content-center gap-2 rounded-3 bg-white p-3 h-100 shadow-sm">
        <span class="icon-wrap d-flex align-items-center justify-content-center rounded-3 bg-primary bg-opacity-10 text-primary"
            style="width:44px;height:44px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        </span>
        <span class="fw-semibold text-center text-dark" style="font-size:0.75rem; line-height:1.3;">{{ $title }}</span>
    </a>
</div>
