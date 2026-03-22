<!DOCTYPE html>
@php
    $appearance = $appearance ?? 'system';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ $appearance }}" class="{{ $appearance === 'dark' ? 'dark' : '' }}">
    <head>
        <script>
            if (document.documentElement.dataset.appearance === 'light') {
                document.documentElement.classList.remove('dark');
            }
        </script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $pwaBrand = request()->attributes->get('current_brand') ?? \App\Models\Brand::getDefault();
            $pwaTouchIcon = \App\Services\Pwa\BrandMediaUrl::appIconAbsolute($pwaBrand) ?? asset('favicon.svg');
            $pwaThemeColor = '#2563eb';
            if ($pwaBrand !== null && is_array($pwaBrand->theme_colors) && ! empty($pwaBrand->theme_colors['primary']) && is_string($pwaBrand->theme_colors['primary'])) {
                $pwaThemeColor = $pwaBrand->theme_colors['primary'];
            }
        @endphp
        <link rel="manifest" href="{{ url('/manifest.json') }}">
        <link rel="apple-touch-icon" href="{{ $pwaTouchIcon }}">
        <meta name="theme-color" content="{{ $pwaThemeColor }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit($pwaBrand?->name ?? config('app.name'), 64) }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            {{-- Force light mode when data-appearance="light" (e.g. site-render) - overrides .dark --}}
            html[data-appearance="light"],
            html[data-appearance="light"].dark {
                --background: hsl(0 0% 100%);
                --foreground: hsl(0 0% 3.9%);
                --card: hsl(0 0% 100%);
                --card-foreground: hsl(0 0% 3.9%);
                --popover: hsl(0 0% 100%);
                --popover-foreground: hsl(0 0% 3.9%);
                --secondary: hsl(0 0% 96.1%);
                --secondary-foreground: hsl(0 0% 9%);
                --muted: hsl(0 0% 96.1%);
                --muted-foreground: hsl(0 0% 45.1%);
                --border: hsl(0 0% 89.2%);
                --input: hsl(0 0% 89.2%);
                background-color: oklch(1 0 0) !important;
                color-scheme: light;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @php
            $brandFaviconUrl = data_get($page ?? [], 'props.brand.seo.favicon_url');
            $brandFaviconUrl = is_string($brandFaviconUrl) && $brandFaviconUrl !== '' ? $brandFaviconUrl : null;
            if ($brandFaviconUrl !== null && ! preg_match('#^https?://#i', $brandFaviconUrl)) {
                $brandFaviconUrl = url($brandFaviconUrl);
            }
            $brandFaviconPath = $brandFaviconUrl !== null ? (parse_url($brandFaviconUrl, PHP_URL_PATH) ?: '') : '';
            $brandFaviconType = match (true) {
                str_ends_with(strtolower($brandFaviconPath), '.svg') => 'image/svg+xml',
                str_ends_with(strtolower($brandFaviconPath), '.png') => 'image/png',
                str_ends_with(strtolower($brandFaviconPath), '.ico') => 'image/x-icon',
                str_ends_with(strtolower($brandFaviconPath), '.webp') => 'image/webp',
                default => null,
            };
        @endphp
        @if ($brandFaviconUrl)
            <link rel="icon" href="{{ $brandFaviconUrl }}" @if ($brandFaviconType !== null) type="{{ $brandFaviconType }}" @endif>
        @else
            <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <div id="toast-portal" aria-live="polite"></div>
    </body>
</html>
