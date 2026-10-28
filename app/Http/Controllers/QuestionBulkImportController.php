<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Quiz;
use App\Services\QuestionBulkImportService;
use App\Services\QuestionImportTemplateXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionBulkImportController extends Controller
{
    use AuthorizesClassroom;

    public function __construct(
        private readonly QuestionBulkImportService $importer,
        private readonly QuestionImportTemplateXlsxExporter $template,
    ) {}

    public function import(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['question_file' => 'Soal tidak bisa diimport karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        $data = $request->validate([
            'question_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);
        $result = $this->importer->import($quiz, $data['question_file']);

        $message = $result['questions'].' soal berhasil diimport';
        if ($result['groups'] > 0) {
            $message .= ' beserta '.$result['groups'].' stimulus';
        }

        return back()->with('success', $message.'.');
    }

    public function templateCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=,\r\n");
            fputcsv($output, QuestionImportTemplateXlsxExporter::HEADERS, ',', '"', '\\', "\r\n");
            fclose($output);
        }, 'template-import-soal.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function templateXlsx(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'template-soal-');
            abort_if($temporaryPath === false, 500, 'Folder sementara tidak tersedia.');

            try {
                $this->template->write($temporaryPath);
                readfile($temporaryPath);
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }, 'template-import-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
