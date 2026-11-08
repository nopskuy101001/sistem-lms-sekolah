<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Transkrip Nilai')</title>
    <style>{!! file_get_contents(public_path('css/transcript-print.css')) !!}</style>
</head>
<body class="{{ ($isPdf ?? false) ? 'pdf-output' : 'preview-output' }}">
    @unless($isPdf ?? false)
        <header class="preview-toolbar">
            <div>
                <strong>Pratinjau transkrip</strong>
                <p>Periksa isi transkrip, lalu unduh berkas PDF yang siap dicetak.</p>
            </div>
            <a href="{{ $pdfUrl }}" class="download-pdf-button">
                <svg aria-hidden="true" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M12 12v6m0 0-2.5-2.5M12 18l2.5-2.5"/></svg>
                Unduh PDF
            </a>
        </header>
    @endunless
    <main class="print-document">
        @yield('content')
    </main>
</body>
</html>
