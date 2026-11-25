@php
    $asset = fn (string $file): string => file_exists(public_path('vendor/analytics/'.$file))
        ? asset('vendor/analytics/'.$file)
        : route('analytics.asset', ['file' => $file, 'v' => '1.0.0']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Analytics') · {{ $instanceName ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ $asset('dashboard.css') }}">
</head>
<body>
    <header class="pa-header">
        <div class="pa-wrap">
            <h1>Analytics</h1>
            <span class="pa-muted">{{ $instanceName ?? config('app.name') }}</span>
        </div>
    </header>
    <main class="pa-wrap">
        @yield('content')
    </main>
    @stack('scripts')
    <script src="{{ $asset('chart.umd.js') }}"></script>
    <script src="{{ $asset('dashboard.js') }}"></script>
</body>
</html>
