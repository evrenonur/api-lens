<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('api-lens.title', 'API Lens') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔍</text></svg>">
    @php
        $lensUrl = url(config('api-lens.url', 'api-lens'));
    @endphp
    <script>
        window.__API_LENS_CONFIG__ = {
            apiUrl: "{{ $lensUrl }}/api",
            configUrl: "{{ $lensUrl }}/config",
            baseUrl: "{{ url('/') }}",
            appName: "{{ config('app.name', 'Laravel') }}",
        };
    </script>
    <link rel="stylesheet" href="{{ $lensUrl }}/assets/main.css">
    <script type="module" src="{{ $lensUrl }}/assets/index.js"></script>
</head>
<body>
    <div id="api-lens-app"></div>
</body>
</html>
