<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <script>
        (() => {
            const storageKey = 'marketplace-dark-mode'
            const stored = localStorage.getItem(storageKey)
            const enabled = stored === null
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                : stored === 'true'

            document.documentElement.classList.toggle('app-dark', enabled)
            document.documentElement.style.colorScheme = enabled ? 'dark' : 'light'
        })()
    </script>
    <link href="https://fonts.cdnfonts.com/css/lato" rel="stylesheet">

    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
