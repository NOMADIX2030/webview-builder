@foreach($news as $item)
    <div class="col-6 col-md-4 col-xl-3">
        @include('landing.sections.news-card', ['item' => $item])
    </div>
@endforeach
