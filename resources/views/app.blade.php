<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Eksplorasi bidang tanah secara interaktif: pencarian spasial, filter, dan statistik harga.">

        <title>{{ config('app.name') }}</title>

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/main.tsx'])
    </head>
    <body class="font-sans antialiased">
        <div
            id="app"
            data-map-url="{{ route('api.v1.map') }}"
            data-parcels-url="{{ route('api.v1.parcels.index') }}"
            data-statistics-url="{{ route('api.v1.parcels.statistics') }}"
        ></div>
    </body>
</html>
