@extends('transcripts.print-layout')
@section('title', 'Transkrip Nilai - '.$student->name)
@php
    $fmt = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
@section('content')
<section class="print-section">
    <header class="document-heading">
        <p class="school-name">SMK NEGERI 1 CERME</p>
        <h1>TRANSKRIP NILAI SISWA</h1>
        <p>Rekap nilai seluruh kelas</p>
    </header>
    <div class="document-meta">
        <div><strong>Nama siswa:</strong> {{ $student->name }}<br><strong>NIS:</strong> {{ $student->nomor_induk ?: '—' }}</div>
        <div><strong>Kelas tervalidasi:</strong> {{ $validatedCount }} dari {{ $classrooms->count() }}<br><strong>Dibuat:</strong> {{ now()->translatedFormat('d M Y H:i') }}</div>
    </div>
    <table>
        <caption>Rekap nilai seluruh kelas — {{ $student->name }}</caption>
        <colgroup><col style="width:6%"><col style="width:25%"><col style="width:22%"><col style="width:17%"><col style="width:15%"><col style="width:15%"></colgroup>
        <thead><tr><th scope="col">No.</th><th scope="col">Kelas</th><th scope="col">Guru</th><th scope="col">Status</th><th scope="col">Rata-rata akhir</th><th scope="col">Tanggal validasi</th></tr></thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $entry['classroom']->name }}</td>
                    <td>{{ $entry['classroom']->teacher->name }}</td>
                    <td>{{ $entry['status'] }}</td>
                    <td class="number"><strong>{{ $entry['average'] !== null ? $fmt($entry['average']).' / 100' : '—' }}</strong></td>
                    <td>{{ $entry['finalized_at']?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada kelas yang diikuti.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="summary"><strong>Rata-rata seluruh kelas tervalidasi: {{ $fmt($average) }} / 100</strong> · {{ $scoredClassCount }} kelas memiliki nilai akhir.</p>
    <p class="document-note">Setiap kelas menampilkan satu nilai rata-rata akhir. Kelas yang belum divalidasi tetap tercantum tanpa nilai, dan tanda — berbeda dari nilai 0.</p>
</section>
@endsection
