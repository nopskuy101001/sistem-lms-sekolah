<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        // Soal tidak boleh diubah jika sudah ada siswa yang mengerjakan.
        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['body' => 'Soal tidak bisa ditambah karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in([
                Question::TYPE_PG,
                Question::TYPE_PGK_MCMA,
                Question::TYPE_PGK_KATEGORI,
                Question::TYPE_ESAI,
            ])],
            'body' => ['required', 'string', 'max:5000'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'question_group_id' => [
                'nullable',
                Rule::exists('question_groups', 'id')->where('quiz_id', $quiz->id),
            ],
            'competency' => ['nullable', 'string', 'max:255'],
            'subcompetency' => ['nullable', 'string', 'max:255'],
            'cognitive_level' => ['nullable', Rule::in(['pemahaman', 'penerapan', 'penalaran'])],
            'option_a' => ['required_if:type,pilihan_ganda,pilihan_ganda_kompleks', 'nullable', 'string', 'max:500'],
            'option_b' => ['required_if:type,pilihan_ganda,pilihan_ganda_kompleks', 'nullable', 'string', 'max:500'],
            'option_c' => ['nullable', 'string', 'max:500'],
            'option_d' => ['nullable', 'string', 'max:500'],
            'correct_option' => ['required_if:type,pilihan_ganda', 'nullable', 'in:a,b,c,d'],
            'correct_options' => ['required_if:type,pilihan_ganda_kompleks', 'nullable', 'array', 'min:2'],
            'correct_options.*' => ['in:a,b,c,d'],
            'category_statements' => ['required_if:type,kategori', 'nullable', 'array', 'min:2', 'max:10'],
            'category_statements.*' => ['nullable', 'string', 'max:1000'],
            'category_answers' => ['required_if:type,kategori', 'nullable', 'array'],
            'category_answers.*' => ['nullable', 'in:benar,salah'],
        ]);

        if ($data['type'] === Question::TYPE_PG) {
            // kunci jawaban harus menunjuk ke opsi yang terisi
            if (blank($data['option_'.$data['correct_option']] ?? null)) {
                return back()->withErrors(['correct_option' => 'Kunci jawaban harus salah satu opsi yang terisi.'])->withInput();
            }
            $data['correct_options'] = null;
            $data['category_items'] = null;
        } elseif ($data['type'] === Question::TYPE_PGK_MCMA) {
            $filledOptions = collect(['a', 'b', 'c', 'd'])
                ->filter(fn ($key) => filled($data['option_'.$key] ?? null));
            $correctOptions = collect($data['correct_options'] ?? [])
                ->filter(fn ($key) => $filledOptions->contains($key))
                ->unique()->sort()->values();

            if ($correctOptions->count() < 2) {
                return back()->withErrors([
                    'correct_options' => 'Pilih minimal dua jawaban benar yang opsinya terisi.',
                ])->withInput();
            }

            $data['correct_option'] = null;
            $data['correct_options'] = $correctOptions->all();
            $data['category_items'] = null;
        } elseif ($data['type'] === Question::TYPE_PGK_KATEGORI) {
            $categoryItems = collect($data['category_statements'] ?? [])
                ->map(function ($statement, $index) use ($data) {
                    $statement = trim((string) $statement);
                    $answer = $data['category_answers'][$index] ?? null;

                    return filled($statement) && in_array($answer, ['benar', 'salah'], true)
                        ? ['statement' => $statement, 'answer' => $answer]
                        : null;
                })
                ->filter()->values();

            if ($categoryItems->count() < 2) {
                return back()->withErrors([
                    'category_statements' => 'Isi minimal dua pernyataan beserta kategori Benar/Salah.',
                ])->withInput();
            }

            $data['option_a'] = $data['option_b'] = $data['option_c'] = $data['option_d'] = null;
            $data['correct_option'] = null;
            $data['correct_options'] = null;
            $data['category_items'] = $categoryItems->all();
        } else {
            $data['option_a'] = $data['option_b'] = $data['option_c'] = $data['option_d'] = null;
            $data['correct_option'] = null;
            $data['correct_options'] = null;
            $data['category_items'] = null;
        }

        unset($data['category_statements'], $data['category_answers']);

        $quiz->questions()->create($data);

        return back()->with('success', 'Soal ditambahkan.');
    }

    public function storeGroup(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['stimulus' => 'Stimulus tidak bisa ditambah karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'stimulus' => ['nullable', 'string', 'max:20000'],
            'stimulus_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'media_alt' => ['nullable', 'string', 'max:255'],
            'table_text' => ['nullable', 'string', 'max:20000'],
        ]);

        if (blank($data['stimulus'] ?? null) && ! $request->hasFile('stimulus_image') && blank($data['table_text'] ?? null)) {
            return back()->withErrors([
                'stimulus' => 'Isi minimal salah satu: teks, gambar/grafik, atau tabel stimulus.',
            ])->withInput();
        }

        $tableData = $this->parseTable($data['table_text'] ?? null);
        if ($tableData instanceof RedirectResponse) {
            return $tableData;
        }

        $groupData = [
            'title' => $data['title'] ?? null,
            'stimulus' => trim((string) ($data['stimulus'] ?? '')),
            'media_alt' => $data['media_alt'] ?? null,
            'table_data' => $tableData,
        ];

        if ($request->hasFile('stimulus_image')) {
            $file = $request->file('stimulus_image');
            $groupData['media_path'] = $file->store('quiz-stimuli', 'local');
            $groupData['media_name'] = $file->getClientOriginalName();
        }

        $quiz->questionGroups()->create($groupData);

        return back()->with('success', 'Stimulus TKA ditambahkan.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $quiz = $question->quiz;
        $this->authorizeManage($quiz->classroom);

        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['body' => 'Soal tidak bisa dihapus karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        $question->delete();

        return back()->with('success', 'Soal dihapus.');
    }

    public function destroyGroup(QuestionGroup $questionGroup): RedirectResponse
    {
        $quiz = $questionGroup->quiz;
        $this->authorizeManage($quiz->classroom);

        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['stimulus' => 'Stimulus tidak bisa dihapus karena sudah ada siswa yang mengerjakan kuis ini.']);
        }

        if ($questionGroup->media_path) {
            Storage::disk('local')->delete($questionGroup->media_path);
        }
        $questionGroup->delete();

        return back()->with('success', 'Stimulus dihapus. Soal terkait tetap tersimpan tanpa stimulus.');
    }

    public function groupMedia(QuestionGroup $questionGroup): StreamedResponse
    {
        $this->authorizeAccess($questionGroup->quiz->classroom);
        abort_unless(
            $questionGroup->media_path && Storage::disk('local')->exists($questionGroup->media_path),
            404
        );

        return Storage::disk('local')->response(
            $questionGroup->media_path,
            $questionGroup->media_name,
            ['Cache-Control' => 'private, max-age=3600'],
            'inline'
        );
    }

    /** @return array<int, array<int, string>>|null|RedirectResponse */
    private function parseTable(?string $input): array|null|RedirectResponse
    {
        if (blank($input)) {
            return null;
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', trim($input)))
            ->filter(fn ($line) => trim((string) $line) !== '')
            ->values();
        $firstLine = (string) $lines->first();
        $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ';') ? ';' : ',');

        $rows = $lines->map(function ($line) use ($delimiter) {
            $cells = $delimiter === "\t" ? explode("\t", $line) : str_getcsv($line, $delimiter);

            return collect($cells)->map(fn ($cell) => trim((string) $cell))->values()->all();
        })->all();

        $columnCount = collect($rows)->max(fn ($row) => count($row)) ?? 0;
        if (count($rows) < 2 || $columnCount < 2) {
            return back()->withErrors([
                'table_text' => 'Tabel harus memiliki minimal dua baris dan dua kolom.',
            ])->withInput();
        }
        if (count($rows) > 30 || $columnCount > 12) {
            return back()->withErrors([
                'table_text' => 'Tabel dibatasi maksimal 30 baris dan 12 kolom.',
            ])->withInput();
        }

        return collect($rows)
            ->map(fn ($row) => array_pad($row, $columnCount, ''))
            ->all();
    }
}
