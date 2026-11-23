@props(["title", "subtitle" => null, "width" => "max-w-md"])
{{-- Shared frame for the register sign-in and setup screens (no Alpine: see auth/pin-login). --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
</head>
<body class="bg-gradient-to-br from-emerald-700 to-emerald-900 min-h-screen flex items-center justify-center p-5">
    <div class="w-full {{ $width }}">
        <div class="text-center mb-6">
            <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-7 w-auto mx-auto mb-5">
            <h1 class="text-2xl font-bold text-white">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-emerald-100 text-sm mt-2 leading-relaxed">{{ $subtitle }}</p>
            @endif
        </div>
        {{ $slot }}
    </div>
</body>
</html>
