<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Transkrip Nilai')</title>
    <link rel="stylesheet" href="{{ asset('css/transcript-print.css') }}?v={{ filemtime(public_path('css/transcript-print.css')) }}">
    <script defer src="{{ asset('js/transcript-print.js') }}?v={{ filemtime(public_path('js/transcript-print.js')) }}"></script>
</head>
<body>
    <div class="print-toolbar">
        <button type="button" data-print-transcript>Cetak / Simpan PDF</button>
        <p>Pilih printer untuk mencetak, atau pilih <strong>Simpan sebagai PDF / Save as PDF</strong>. Gunakan kertas A4, orientasi lanskap, dan nonaktifkan header/footer browser.</p>
    </div>
    <main class="print-document">
        @yield('content')
    </main>
</body>
</html>
