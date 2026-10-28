<?php

namespace App\Http\Controllers;

use App\Models\TranscriptState;
use App\Services\TranscriptReportService;
use App\Services\TranscriptXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentTranscriptController extends Controller
{
    public function __construct(
        private readonly TranscriptReportService $reports,
        private readonly TranscriptXlsxExporter $xlsx,
    ) {}

    public function index(Request $request): View
    {
        return view('transcripts.student', $this->overview($request));
    }

    public function allCsv(Request $request): StreamedResponse
    {
        $overview = $this->overview($request);
        $student = $request->user();

        return response()->streamDownload(function () use ($overview, $student) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBFsep=,\r\n");
            $write = function (array $values) use ($output) {
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', $value) ? "'".$value : $value, $values);
                fputcsv($output, $values, ',', '"', '', "\r\n");
            };
            $write(['TRANSKRIP NILAI SELURUH KELAS']);
            $write(['Nama', $student->name, 'NIS', (string) ($student->nomor_induk ?? '')]);
            $write(['Kelas divalidasi', $overview['validatedCount'], 'Total kelas', $overview['classrooms']->count()]);
            $write(['Rata-rata nilai final (0-100)', $overview['average']]);
            $write(['No.', 'Kelas', 'Nilai', 'Status', 'Aktivitas', 'Jenis', 'Guru', 'Maksimum', 'Persentase (%)']);
            foreach ($overview['entries'] as $index => $entry) {
                $score = $entry['score'];
                $write([
                    $index + 1, $entry['classroom']->name, $score['score'] ?? null,
                    $entry['status'], $entry['activity']['title'] ?? '', $entry['activity']['type'] ?? '',
                    $entry['classroom']->teacher->name, $score['maximum'] ?? null, $score['percentage'] ?? null,
                ]);
            }
            fclose($output);
        }, $this->allFilename('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function allExcel(Request $request): StreamedResponse
    {
        $overview = $this->overview($request);
        $student = $request->user();

        return response()->streamDownload(function () use ($overview, $student) {
            $path = tempnam(sys_get_temp_dir(), 'student-transcript-all-');
            abort_if($path === false, 500, 'Folder sementara tidak tersedia.');
            try {
                $this->xlsx->writeStudentCombined($path, $overview, $student);
                readfile($path);
            } finally {
                if (is_file($path)) unlink($path);
            }
        }, $this->allFilename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function overview(Request $request): array
    {
        $classrooms = $request->user()->joinedClassrooms()->with('teacher')->orderBy('name')->get();
        $states = TranscriptState::whereIn('classroom_id', $classrooms->pluck('id'))->get()->keyBy('classroom_id');
        $entries = collect();
        $percentages = collect();
        $validatedCount = 0;

        foreach ($classrooms as $classroom) {
            $state = $states->get($classroom->id);
            if (! $state?->finalized_at || ! is_array($state->snapshot)) {
                $entries->push(['classroom' => $classroom, 'activity' => null, 'score' => null, 'status' => 'Belum divalidasi']);
                continue;
            }

            $validatedCount++;
            $report = $this->reports->buildForStudent($classroom, $request->user());
            $row = $report['rows']->first();
            if (! $row) {
                $entries->push(['classroom' => $classroom, 'activity' => null, 'score' => null, 'status' => 'Belum tercantum dalam transkrip final']);
                continue;
            }
            if ($report['activities']->isEmpty()) {
                $entries->push(['classroom' => $classroom, 'activity' => null, 'score' => null, 'status' => 'Belum ada komponen nilai']);
                continue;
            }
            foreach ($report['activities'] as $activity) {
                $score = $row['scores'][$activity['key']];
                $entries->push(['classroom' => $classroom, 'activity' => $activity, 'score' => $score, 'status' => $score['status']]);
                if ($score['final'] && $score['percentage'] !== null) {
                    $percentages->push($score['percentage']);
                }
            }
        }

        return [
            'classrooms' => $classrooms,
            'entries' => $entries,
            'validatedCount' => $validatedCount,
            'completed' => $percentages->count(),
            'average' => $percentages->isEmpty() ? null : round($percentages->avg(), 2),
        ];
    }

    private function allFilename(string $extension): string
    {
        return 'transkrip-saya-semua-kelas-'.now()->format('Y-m-d').'.'.$extension;
    }

}
