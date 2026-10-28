<?php

namespace App\Http\Controllers;

use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Services\QuestionBankXlsxExporter;
use App\Services\QuestionBankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionBankController extends Controller
{
    public function __construct(
        private readonly QuestionBankService $banks,
        private readonly QuestionBankXlsxExporter $exporter,
    ) {}

    public function index(Request $request): View
    {
        $questionBanks = QuestionBank::query()
            ->where('teacher_id', $request->user()->id)
            ->latest()
            ->get();
        $classrooms = $request->user()->teachingClassrooms()->orderBy('name')->get();

        return view('question-banks.index', compact('questionBanks', 'classrooms'));
    }

    public function show(Request $request, QuestionBank $questionBank): View
    {
        $this->authorizeBank($request, $questionBank);
        $classrooms = $request->user()->teachingClassrooms()->orderBy('name')->get();
        $payload = $questionBank->payload ?? [];
        $groups = collect($payload['groups'] ?? [])->keyBy('key');
        $questions = collect($payload['questions'] ?? [])->values();

        return view('question-banks.show', compact(
            'questionBank',
            'classrooms',
            'payload',
            'groups',
            'questions'
        ));
    }

    public function media(Request $request, QuestionBank $questionBank, string $groupKey): StreamedResponse
    {
        $this->authorizeBank($request, $questionBank);
        $group = collect($questionBank->payload['groups'] ?? [])->firstWhere('key', $groupKey);
        $path = $group['media_path'] ?? null;

        abort_unless($group && $path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $group['media_name'] ?? basename($path),
            ['Cache-Control' => 'private, max-age=3600'],
            'inline'
        );
    }

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeOwner($request, $quiz);
        if (! $quiz->questions()->exists()) {
            return back()->withErrors(['question_bank' => 'Tambahkan minimal satu soal sebelum menyimpan ke bank soal.']);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);
        $bank = $this->banks->saveQuiz($quiz, $request->user(), $data['title']);

        return redirect()->route('question-banks.index')
            ->with('success', 'Paket “'.$bank->title.'” berhasil disimpan ke bank soal.');
    }

    public function excel(Request $request, QuestionBank $questionBank): StreamedResponse
    {
        $this->authorizeBank($request, $questionBank);

        return response()->streamDownload(function () use ($questionBank): void {
            $path = tempnam(sys_get_temp_dir(), 'bank-soal-');
            abort_if($path === false, 500, 'Folder sementara tidak tersedia.');

            try {
                $this->exporter->write($path, $questionBank);
                readfile($path);
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }, 'bank-soal-'.$questionBank->id.'-'.(Str::slug($questionBank->title) ?: 'paket').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function createQuiz(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $this->authorizeBank($request, $questionBank);
        $data = $request->validate([
            'classroom_id' => [
                'required',
                'integer',
                Rule::exists('classrooms', 'id')->where('teacher_id', $request->user()->id),
            ],
            'title' => ['nullable', 'string', 'max:200'],
        ]);
        $classroom = $request->user()->teachingClassrooms()->findOrFail($data['classroom_id']);
        $quiz = $this->banks->createQuiz($questionBank, $classroom, $data['title'] ?? null);

        return redirect()->route('quizzes.show', $quiz)
            ->with('success', 'Kuis draft berhasil dibuat dari bank soal. Atur jadwal lalu publikasikan.');
    }

    public function destroy(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $this->authorizeBank($request, $questionBank);
        $this->banks->delete($questionBank);

        return back()->with('success', 'Paket bank soal berhasil dihapus.');
    }

    private function authorizeOwner(Request $request, Quiz $quiz): void
    {
        abort_unless($quiz->classroom->teacher_id === $request->user()->id, 403);
    }

    private function authorizeBank(Request $request, QuestionBank $bank): void
    {
        abort_unless($bank->teacher_id === $request->user()->id, 403);
    }
}
