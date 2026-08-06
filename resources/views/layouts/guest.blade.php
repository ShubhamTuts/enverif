<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Enverif')</title>
    @php
        $assetVersion = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev';
    @endphp
    <link rel="icon" href="{{ asset('assets/enverif-mark.svg') }}?v={{ rawurlencode($assetVersion) }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ rawurlencode($assetVersion) }}">
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('enverif-theme') ||
            (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    </script>
</head>
<body>
    @yield('content')
    <script src="{{ asset('assets/app.js') }}?v={{ rawurlencode($assetVersion) }}" defer></script>
</body>
</html>
