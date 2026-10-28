@props(['title'])
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} · {{ config('pekppp.name') }}</title>
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset(config('pekppp.brand.icon')) }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset(config('pekppp.brand.touch_icon')) }}">
<script>
    (function () {
        try {
            var root = document.documentElement;
            var get = function (key) { return localStorage.getItem(key); };
            var theme = get('theme');
            if (theme === 'light' || theme === 'dark') root.dataset.theme = theme;
            var accent = get('accent');
            if (accent && accent !== 'indigo') root.dataset.accent = accent;
            var radius = get('radius');
            if (radius) root.style.setProperty('--radius', radius + 'rem');
            if (get('sbmode') === 'mini') root.dataset.sbmode = 'mini';
        } catch (error) {}
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])

