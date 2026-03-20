<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @inertiaHead
</head>
<body class="antialiased bg-slate-50 text-slate-900">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @inertia
    @else
        <main style="max-width: 720px; margin: 60px auto; padding: 0 16px; font-family: Arial, sans-serif;">
            <h1 style="font-size: 24px; margin-bottom: 12px;">Frontend assets no disponibles</h1>
            <p style="margin-bottom: 8px;">Para cargar la UI de Inertia debes ejecutar uno de estos comandos:</p>
            <pre style="background: #f1f5f9; padding: 12px; border-radius: 8px;">npm run dev
npm run build</pre>
        </main>
    @endif
</body>
</html>
