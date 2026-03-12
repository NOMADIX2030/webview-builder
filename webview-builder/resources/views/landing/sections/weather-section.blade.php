{{-- 오늘의 날씨 (위치 기반, 현재 시각부터 12시간) --}}
<section class="mb-5" id="weather-section">
    <div id="weather-section-body">
        @include('landing.sections.weather-section-body', ['weatherData' => $weatherData ?? null])
    </div>
</section>

@push('scripts')
<script>
(function() {
    var body = document.getElementById('weather-section-body');
    if (!body) return;
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            var lat = pos.coords.latitude;
            var lon = pos.coords.longitude;
            fetch('/api/landing/weather?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon), {
                headers: { 'Accept': 'text/html' }
            }).then(function(r) {
                if (!r.ok) return;
                return r.text();
            }).then(function(html) {
                if (html) body.innerHTML = html;
            }).catch(function() {});
        },
        function() {},
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
    );
})();
</script>
@endpush
