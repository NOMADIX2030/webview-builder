<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', '웹뷰 앱 빌드')</title>
    <meta name="description" content="웹사이트를 앱으로 쉽게 만들어보세요">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-screen bg-gray-50 text-gray-900 font-sans dark:bg-gray-900 dark:text-gray-100">
    <main class="flex min-h-screen flex-col items-center justify-center p-6">
        <div class="flex w-full max-w-md flex-col gap-6">
            @yield('content')
        </div>
    </main>
    @stack('scripts')
</body>
</html>
