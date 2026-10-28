<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\TranscriptState;
use App\Models\TranscriptScoreOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\TranscriptReportService;
use App\Services\TranscriptXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranscriptController extends Controller
{
    public function __construct(
        private readonly TranscriptReportService $reports,
        private readonly TranscriptXlsxExporter $xlsx,
    ) {}

    public function index(Request $request): View
    {
        $classrooms = $request->user()->teachingClassrooms()
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $classroom = null;
        if ($request->filled('classroom')) {
            $classroom = $classrooms->firstWhere('id', (int) $request->integer('classroom'));
            abort_unless($classroom, 403, 'Anda tidak mengajar kelas yang dipilih.');
        } else {
            $classroom = $classrooms->first();
        }

        $report = $classroom ? $this->reports->build($classroom) : null;

        return view('transcripts.index', compact('classrooms', 'classroom', 'report'));
    }

    /** Override transcript-only score, preserving original attempts and submissions. */
    public function updateScore(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'activity_type' => ['required', 'in:assignment,quiz,video'],
            'activity_id' => ['required', 'integer', 'min:1'],
            'score' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $classroom, $data) {
            $this->lockDraft($classroom);
            if (! $classroom->students()->where('users.id', $data['student_id'])->exists()) {
                throw ValidationException::withMessages(['student_id' => 'Siswa tidak terdaftar pada kelas ini.']);
            }
            $activityKey = $data['activity_type'].'-'.$data['activity_id'];
            $activity = $this->reports->build($classroom)['activities']->firstWhere('key', $activityKey);
            if (! $activity || (float) $activity['maximum'] <= 0) {
                throw ValidationException::withMessages(['activity_id' => 'Komponen nilai tidak valid atau belum memiliki skor maksimum.']);
            }
            if ((float) $data['score'] > (float) $activity['maximum']) {
                throw ValidationException::withMessages(['score' => 'Nilai tidak boleh melebihi maksimum '.$this->number((float) $activity['maximum']).'.']);
            }

            $identity = [
                'classroom_id' => $classroom->id,
                'user_id' => (int) $data['student_id'],
                'activity_type' => $data['activity_type'],
                'activity_id' => (int) $data['activity_id'],
            ];
            $previous = TranscriptScoreOverride::where($identity)->first();
            TranscriptScoreOverride::updateOrCreate($identity, [
                'score' => (float) $data['score'],
                'reason' => $data['reason'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            DB::table('transcript_score_audits')->insert([
                ...$identity,
                'actor_id' => $request->user()->id,
                'action' => 'set',
                'before_score' => $previous?->score,
                'after_score' => $data['score'],
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('transcripts.index', ['classroom' => $classroom->id])
            ->with('success', 'Nilai transkrip tersimpan. Nilai asli pengerjaan tetap utuh.');
    }

    /** Remove an override so automatic grading becomes the source again. */
    public function clearScore(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'activity_type' => ['required', 'in:assignment,quiz,video'],
            'activity_id' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $classroom, $data) {
            $this->lockDraft($classroom);
            $identity = [
                'classroom_id' => $classroom->id,
                'user_id' => (int) $data['student_id'],
                'activity_type' => $data['activity_type'],
                'activity_id' => (int) $data['activity_id'],
            ];
            $previous = TranscriptScoreOverride::where($identity)->first();
            if ($previous) {
                $previous->delete();
                DB::table('transcript_score_audits')->insert([
                    ...$identity,
                    'actor_id' => $request->user()->id,
                    'action' => 'clear',
                    'before_score' => $previous->score,
                    'after_score' => null,
                    'reason' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('transcripts.index', ['classroom' => $classroom->id])
            ->with('success', 'Koreksi dihapus. Sumber nilai otomatis kembali digunakan.');
    }

    /** Confirm once, then freeze class transcript and exports as an immutable snapshot. */
    public function finalize(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $request->validate(['final_validation' => ['required', 'accepted']]);

        DB::transaction(function () use ($request, $classroom) {
            $state = $this->lockDraft($classroom);
            $report = $this->reports->build($classroom);
            if ($report['student_count'] === 0 || $report['activity_count'] === 0) {
                throw ValidationException::withMessages(['final_validation' => 'Transkrip harus memiliki siswa dan komponen nilai.']);
            }
            $state->update([
                'snapshot' => $this->reports->snapshot($report),
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('transcripts.index', ['classroom' => $classroom->id])
            ->with('success', 'Final Validation selesai. Transkrip kelas sekarang terkunci.');
    }

    private function lockDraft(Classroom $classroom): TranscriptState
    {
        TranscriptState::firstOrCreate(['classroom_id' => $classroom->id]);
        $state = TranscriptState::where('classroom_id', $classroom->id)->lockForUpdate()->firstOrFail();
        if ($state->finalized_at !== null) {
            abort(409, 'Transkrip sudah Final Validation dan tidak dapat diubah.');
        }
        return $state;
    }

    public function csv(Request $request, Classroom $classroom): StreamedResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $report = $this->reports->build($classroom);
        $filename = $this->filename($classroom, 'csv');

        return response()->streamDownload(function () use ($report) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=,\r\n");

            $headers = ['No.', 'Nomor Induk', 'Nama Siswa'];
            foreach ($report['activities'] as $activity) {
                $headers[] = $activity['type'].': '.$activity['title'].' (/'.$this->number($activity['maximum']).') [%]';
            }
            $headers[] = 'Rata-rata (%)';
            fputcsv($output, $headers, ',', '"', '\\', "\r\n");

            foreach ($report['rows'] as $index => $row) {
                $values = [
                    $index + 1,
                    $this->csvSafe($row['student']->nomor_induk ?? ''),
                    $this->csvSafe($row['student']->name),
                ];
                foreach ($report['activities'] as $activity) {
                    $percentage = $row['scores'][$activity['key']]['percentage'];
                    $values[] = $percentage !== null ? $this->csvNumber($percentage) : '';
                }
                $values[] = $row['average'] !== null ? $this->csvNumber($row['average']) : '';
                fputcsv($output, $values, ',', '"', '\\', "\r\n");
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function excel(Request $request, Classroom $classroom): StreamedResponse
    {
        $this->authorizeTeacher($request, $classroom);
        $report = $this->reports->build($classroom);
        $filename = $this->filename($classroom, 'xlsx');

        return response()->streamDownload(function () use ($report) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'transcript-xlsx-');
            if ($temporaryPath === false) {
                abort(500, 'Folder sementara tidak tersedia.');
            }

            try {
                $this->xlsx->write($temporaryPath, $report);
                readfile($temporaryPath);
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function authorizeTeacher(Request $request, Classroom $classroom): void
    {
        abort_unless(
            $request->user()->isGuru() && $classroom->teacher_id === $request->user()->id,
            403,
            'Hanya guru pengampu kelas ini yang dapat mengunduh transkrip.'
        );
    }

    private function filename(Classroom $classroom, string $extension): string
    {
        return 'transkrip-'.Str::slug($classroom->name).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function csvNumber(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
