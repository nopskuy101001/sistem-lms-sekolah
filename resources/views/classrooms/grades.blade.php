@extends('layouts.app')
@section('title', 'Nilai - '.$classroom->name)

@php
    // tampilkan angka tanpa nol di belakang koma: 85.50 -> 85.5, 90.00 -> 90
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

@section('content')
@include('classrooms._header-modern', ['tab' => 'grades'])

@if(auth()->user()->isSiswa())
    <div class="mb-4 flex flex-wrap justify-end">
        <a href="{{ route('student.transcripts.index') }}" class="lms-button lms-button-primary">Buka transkrip saya</a>
    </div>
@endif

@if($isManager && auth()->user()->isGuru())
    <div class="flex justify-end mb-4">
        <a href="{{ route('transcripts.index', ['classroom' => $classroom->id]) }}" class="lms-button lms-button-primary">Buka transkrip & ekspor</a>
    </div>
@endif

@if($transcript['finalized'])
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Transkrip kelas sudah Final Validation. Nilai yang ditampilkan mengacu pada snapshot final saat tersedia.</div>
@endif

@if($assignments->isEmpty() && $quizzes->isEmpty() && $videoLessons->isEmpty())
    <p class="text-sm text-slate-500">Belum ada tugas, kuis, atau video interaktif di kelas ini.</p>
@elseif($students->isEmpty())
    <p class="text-sm text-slate-500">Belum ada siswa di kelas ini.</p>
@else
    <p id="grades-scroll-help" class="mb-2 text-xs text-slate-500">Geser tabel ke samping untuk melihat seluruh nilai.</p>
    <div class="mobile-data-table overflow-x-auto bg-white rounded-xl border border-slate-200" role="region" aria-label="Nilai siswa" aria-describedby="grades-scroll-help" tabindex="0">
        <table class="academic-score-table min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th scope="col" class="score-student text-left px-4 py-3 font-medium bg-slate-50">Siswa</th>
                    @foreach($assignments as $assignment)
                        <th class="px-3 py-3 font-medium text-center whitespace-nowrap">
                            <a href="{{ route('assignments.show', $assignment) }}" class="score-activity-title hover:text-indigo-700">{{ $assignment->title }}</a>
                            <div class="text-xs font-normal text-slate-400">/{{ $assignment->points }}</div>
                        </th>
                    @endforeach
                    @foreach($quizzes as $quiz)
                        <th class="px-3 py-3 font-medium text-center whitespace-nowrap">
                            <a href="{{ route('quizzes.show', $quiz) }}" class="score-activity-title hover:text-indigo-700">{{ $quiz->title }}</a>
                            <div class="text-xs font-normal text-slate-400">Kuis /{{ (int) $quiz->questions_sum_points }}</div>
                        </th>
                    @endforeach
                    @foreach($videoLessons as $videoLesson)
                        <th class="px-3 py-3 font-medium text-center whitespace-nowrap">
                            <a href="{{ route('video-lessons.show', $videoLesson) }}" class="score-activity-title hover:text-indigo-700">{{ $videoLesson->title }}</a>
                            <div class="text-xs font-normal text-slate-400">Video /100</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($students as $student)
                    <tr class="border-t border-slate-100">
                        <td class="score-student px-4 py-3 font-medium bg-white">{{ $student->name }}</td>

                        @foreach($assignments as $assignment)
                            @php
                                $sub = $submissions->get($student->id)?->get($assignment->id);
                                $transcriptScore = $transcriptScores->get($student->id)['assignment-'.$assignment->id] ?? null;
                            @endphp
                            <td class="px-3 py-3 text-center">
                                @if($transcriptScore && $transcriptScore['score'] !== null)
                                    {{ $fmt($transcriptScore['score']) }}
                                    @if($transcriptScore['manual'] ?? false)<span class="block text-[10px] text-violet-600">koreksi guru</span>@endif
                                @elseif(!$transcript['finalized'] && $sub && $sub->score !== null)
                                    {{ $fmt($sub->score) }}
                                @elseif(!$transcript['finalized'] && $sub)
                                    <span class="text-sky-600 text-xs">Dikumpulkan</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                        @endforeach

                        @foreach($quizzes as $quiz)
                            @php
                                $att = $attempts->get($student->id)?->get($quiz->id);
                                $transcriptScore = $transcriptScores->get($student->id)['quiz-'.$quiz->id] ?? null;
                            @endphp
                            <td class="px-3 py-3 text-center">
                                @if($transcriptScore && $transcriptScore['score'] !== null)
                                    {{ $fmt($transcriptScore['score']) }}
                                    @if($transcriptScore['manual'] ?? false)
                                        <span class="block text-[10px] text-violet-600">koreksi guru</span>
                                    @elseif(!$transcriptScore['final'])
                                        <span class="block text-xs text-amber-600">menunggu esai</span>
                                    @endif
                                @elseif(!$transcript['finalized'] && $att && $att->isFinished())
                                    {{ $fmt($att->score) }}
                                    @unless($att->is_graded)
                                        <span class="block text-xs text-amber-600">menunggu esai</span>
                                    @endunless
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                        @endforeach
                        @foreach($videoLessons as $videoLesson)
                            @php
                                $videoAttempt = $videoAttempts->get($student->id)?->get($videoLesson->id);
                                $transcriptScore = $transcriptScores->get($student->id)['video-'.$videoLesson->id] ?? null;
                            @endphp
                            <td class="px-3 py-3 text-center">
                                @if($transcriptScore && $transcriptScore['score'] !== null)
                                    <span class="text-emerald-700 font-semibold">{{ $fmt($transcriptScore['score']) }}</span>
                                    @if($transcriptScore['manual'] ?? false)<span class="block text-[10px] text-violet-600">koreksi guru</span>@endif
                                @elseif(!$transcript['finalized'] && $videoAttempt?->completed_at)
                                    <span class="text-emerald-700 font-semibold">{{ $fmt($videoAttempt->score) }}</span>
                                @elseif(!$transcript['finalized'] && $videoAttempt)
                                    <span class="text-xs text-amber-600">Dalam proses</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
