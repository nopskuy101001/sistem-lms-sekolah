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
        <colgroup><col style="width:4%"><col style="width:18%"><col style="width:24%"><col style="width:10%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:20%"></colgroup>
        <thead><tr><th scope="col">No.</th><th scope="col">Kelas / Guru</th><th scope="col">Aktivitas</th><th scope="col">Jenis</th><th scope="col">Nilai</th><th scope="col">Maks.</th><th scope="col">Persentase</th><th scope="col">Status</th></tr></thead>
        <tbody>
            @forelse($entries as $entry)
                @php $score = $entry['score']; @endphp
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $entry['classroom']->name }}<small>{{ $entry['classroom']->teacher->name }}</small></td>
                    <td>{{ $entry['activity']['title'] ?? '—' }}</td>
                    <td>{{ $entry['activity']['type'] ?? '—' }}</td>
                    <td class="number">{{ $fmt($score['score'] ?? null) }}</td>
                    <td class="number">{{ $fmt($score['maximum'] ?? null) }}</td>
                    <td class="number">{{ ($score['percentage'] ?? null) !== null ? $fmt($score['percentage']).'%' : '—' }}</td>
                    <td>{{ $entry['status'] }}@if($score && !$score['final'] && $score['score'] !== null)<small>Nilai sementara</small>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada kelas yang diikuti.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="summary"><strong>Rata-rata final: {{ $fmt($average) }} (0–100)</strong> · {{ $completed }} nilai final dari kelas tervalidasi.</p>
    <p class="document-note">Nilai muncul setelah validasi guru. Kelas yang belum divalidasi tetap tercantum tanpa nilai. Nilai sementara tidak masuk rata-rata. Tanda — berarti belum ada nilai, berbeda dari nilai 0.</p>
</section>
@endsection
