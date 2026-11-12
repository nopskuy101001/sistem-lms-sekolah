<?php

namespace App\Http\Controllers;

use App\Models\TranscriptState;
use App\Services\TranscriptReportService;
use App\Services\TranscriptXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function pdf(Request $request): Response
    {
        return Pdf::loadView('transcripts.print-student', [
            ...$this->overview($request),
            'student' => $request->user(),
            'isPdf' => true,
        ])->setPaper('a4', 'landscape')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ])
            ->download($this->allFilename('pdf'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function print(Request $request): Response
    {
        return response()->view('transcripts.print-student', [
            ...$this->overview($request),
            'student' => $request->user(),
            'isPdf' => false,
            'pdfUrl' => route('student.transcripts.pdf'),
        ])->header('Cache-Control', 'private, no-store');
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
            $write(['Rata-rata seluruh kelas tervalidasi (0-100)', $overview['average']]);
            $write(['No.', 'Kelas', 'Guru', 'Status', 'Rata-rata akhir (0-100)', 'Tanggal validasi']);
            foreach ($overview['entries'] as $index => $entry) {
                $write([
                    $index + 1,
                    $entry['classroom']->name,
                    $entry['classroom']->teacher->name,
                    $entry['status'],
                    $entry['average'],
                    $entry['finalized_at']?->format('d-m-Y H:i'),
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
        $classAverages = collect();
        $validatedCount = 0;

        foreach ($classrooms as $classroom) {
            $state = $states->get($classroom->id);
            if (! $state?->finalized_at || ! is_array($state->snapshot)) {
                $entries->push([
                    'classroom' => $classroom,
                    'average' => null,
                    'status' => 'Belum divalidasi',
                    'finalized_at' => null,
                ]);
                continue;
            }

            $validatedCount++;
            $report = $this->reports->buildForStudent($classroom, $request->user());
            $row = $report['rows']->first();
            if (! $row) {
                $entries->push([
                    'classroom' => $classroom,
                    'average' => null,
                    'status' => 'Belum tercantum dalam transkrip final',
                    'finalized_at' => $report['finalized_at'],
                ]);
                continue;
            }
            if ($report['activities']->isEmpty()) {
                $entries->push([
                    'classroom' => $classroom,
                    'average' => null,
                    'status' => 'Belum ada komponen nilai',
                    'finalized_at' => $report['finalized_at'],
                ]);
                continue;
            }

            $average = $row['average'];
            $entries->push([
                'classroom' => $classroom,
                'average' => $average,
                'status' => $average !== null ? 'Tervalidasi' : 'Belum ada nilai terhitung',
                'finalized_at' => $report['finalized_at'],
            ]);
            if ($average !== null) {
                $classAverages->push($average);
            }
        }

        return [
            'classrooms' => $classrooms,
            'entries' => $entries,
            'validatedCount' => $validatedCount,
            'scoredClassCount' => $classAverages->count(),
            'average' => $classAverages->isEmpty() ? null : round($classAverages->avg(), 2),
        ];
    }

    private function allFilename(string $extension): string
    {
        return 'transkrip-saya-semua-kelas-'.now()->format('Y-m-d').'.'.$extension;
    }

}
