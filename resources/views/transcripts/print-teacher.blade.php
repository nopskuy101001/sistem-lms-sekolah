@extends('transcripts.print-layout')
@section('title', 'Transkrip Nilai - '.$classroom->name)
@php
    $fmt = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $parts = $report['activities']->isEmpty() ? collect([collect()]) : $report['activities']->chunk(6);
@endphp
@section('content')
@foreach($parts as $part)
    <section class="print-section">
        <header class="document-heading">
            <p class="school-name">SMK NEGERI 1 CERME</p>
            <h1>TRANSKRIP NILAI SISWA</h1>
            <p>{{ $classroom->name }}@if($classroom->section) · {{ $classroom->section }}@endif</p>
        </header>
        <div class="document-meta">
            <div><strong>Guru:</strong> {{ $classroom->teacher->name }}<br><strong>Mata pelajaran:</strong> {{ $classroom->subject ?: '—' }}</div>
            <div><strong>Status:</strong> {{ $report['finalized'] ? 'Final / tervalidasi' : 'Draf — belum divalidasi' }}<br>
                @if($report['finalized'])<strong>Validasi:</strong> {{ $report['finalized_at']?->translatedFormat('d M Y H:i') }}<br>@endif
                <strong>Dicetak:</strong> {{ now()->translatedFormat('d M Y H:i') }}</div>
        </div>
        <p class="summary">{{ $report['student_count'] }} siswa · {{ $report['activity_count'] }} komponen nilai · Rata-rata kelas: {{ $fmt($report['class_average']) }} (0–100)</p>
        <table class="teacher-table">
            <caption>{{ $classroom->name }} — Rekap nilai @if($parts->count() > 1) · Bagian {{ $loop->iteration }} dari {{ $parts->count() }}@endif</caption>
            <colgroup><col style="width:4%"><col style="width:12%"><col style="width:21%">@foreach($part as $activity)<col>@endforeach<col style="width:10%"></colgroup>
            <thead><tr><th scope="col">No.</th><th scope="col">NIS</th><th scope="col">Nama siswa</th>@foreach($part as $activityIndex => $activity)<th scope="col">K{{ $activityIndex + 1 }}<small>Maks. {{ $fmt($activity['maximum']) }}</small></th>@endforeach<th scope="col">Rata-rata final<small>0–100</small></th></tr></thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td class="number">{{ $loop->iteration }}</td>
                        <td>{{ $row['student']->nomor_induk ?: '—' }}</td>
                        <th scope="row" class="student-name">{{ $row['student']->name }}</th>
                        @foreach($part as $activity)
                            @php $score = $row['scores'][$activity['key']]; @endphp
                            <td class="number">
                                @if($score['score'] !== null)
                                    <strong>{{ $fmt($score['score']) }} / {{ $fmt($score['maximum']) }}</strong>
                                    <small>{{ $score['percentage'] !== null ? $fmt($score['percentage']).'%' : '—' }}{{ !$score['final'] ? ' · sementara' : '' }}</small>
                                @else
                                    —<small>{{ $score['status'] }}</small>
                                @endif
                            </td>
                        @endforeach
                        <td class="number"><strong>{{ $fmt($row['average']) }}</strong><small>{{ $row['completed'] }}/{{ $report['activity_count'] }} final</small></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $part->count() + 4 }}">Belum ada siswa di kelas ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($part->isNotEmpty())
            <div class="activity-legend">
                <h2>Keterangan komponen nilai</h2>
                @foreach($part as $activityIndex => $activity)<p><strong>K{{ $activityIndex + 1 }}:</strong> {{ $activity['type'] }} — {{ $activity['title'] }}</p>@endforeach
            </div>
        @else
            <p>Belum ada komponen nilai.</p>
        @endif
        <p class="document-note">Rata-rata dihitung dari seluruh nilai final dalam skala 0–100. Nilai sementara tidak masuk rata-rata. Tanda — berarti belum ada nilai, berbeda dari nilai 0.</p>
    </section>
@endforeach
@endsection
