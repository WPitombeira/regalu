<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ config("app.name") ?? 'Page Title' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[#070715] flex flex-col min-h-full text-[#C3C3D1] flex min-h-full flex-col">
    <x-ui.navbar />
    <main class="grow mx-auto px-4 sm:px-6 lg:px-8 w-full py-[40px]">
        {{ $slot }}
    </main>
    <x-ui.footer />
</body>

</html>