<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionBulkImportController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\StudentTranscriptController;
use App\Http\Controllers\VideoLessonController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LMS Sekolah - Modul Kurikulum
|--------------------------------------------------------------------------
*/

// ---------- Tamu ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

// ---------- Sudah login ----------
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/dashboard', [ClassroomController::class, 'index'])->name('dashboard');

    Route::middleware(RoleMiddleware::class.':guru')->prefix('teacher')->group(function () {
        Route::get('/transcripts', [TranscriptController::class, 'index'])->name('transcripts.index');
        Route::get('/transcripts/{classroom}/csv', [TranscriptController::class, 'csv'])->name('transcripts.csv');
        Route::put('/transcripts/{classroom}/scores', [TranscriptController::class, 'updateScore'])->name('transcripts.scores.update');
        Route::delete('/transcripts/{classroom}/scores', [TranscriptController::class, 'clearScore'])->name('transcripts.scores.clear');
        Route::post('/transcripts/{classroom}/finalize', [TranscriptController::class, 'finalize'])->name('transcripts.finalize');
        Route::get('/transcripts/{classroom}/excel', [TranscriptController::class, 'excel'])->name('transcripts.excel');
        Route::get('/question-banks', [QuestionBankController::class, 'index'])->name('question-banks.index');
        Route::get('/question-banks/{questionBank}/excel', [QuestionBankController::class, 'excel'])->name('question-banks.excel');
        Route::get('/question-banks/{questionBank}', [QuestionBankController::class, 'show'])->name('question-banks.show');
        Route::get('/question-banks/{questionBank}/stimuli/{groupKey}/media', [QuestionBankController::class, 'media'])->name('question-banks.media');
        Route::post('/quizzes/{quiz}/question-banks', [QuestionBankController::class, 'store'])->name('question-banks.store');
        Route::post('/question-banks/{questionBank}/quizzes', [QuestionBankController::class, 'createQuiz'])->name('question-banks.quizzes.store');
        Route::delete('/question-banks/{questionBank}', [QuestionBankController::class, 'destroy'])->name('question-banks.destroy');
        Route::post('/quizzes/{quiz}/questions/import', [QuestionBulkImportController::class, 'import'])->name('questions.import');
        Route::get('/question-import/template.csv', [QuestionBulkImportController::class, 'templateCsv'])->name('questions.import.template.csv');
        Route::get('/question-import/template.xlsx', [QuestionBulkImportController::class, 'templateXlsx'])->name('questions.import.template.xlsx');
    });

    Route::middleware(RoleMiddleware::class.':siswa')->prefix('student')->name('student.')->group(function () {
        Route::get('/transcripts', [StudentTranscriptController::class, 'index'])->name('transcripts.index');
        Route::get('/transcripts/csv', [StudentTranscriptController::class, 'allCsv'])->name('transcripts.all.csv');
        Route::get('/transcripts/excel', [StudentTranscriptController::class, 'allExcel'])->name('transcripts.all.excel');
    });

    // Kelas
    Route::post('/classrooms/join', [ClassroomController::class, 'join'])->name('classrooms.join');
    Route::post('/classrooms', [ClassroomController::class, 'store'])
        ->middleware(RoleMiddleware::class.':guru,admin')
        ->name('classrooms.store');
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
    Route::post('/classrooms/{classroom}/refresh-code', [ClassroomController::class, 'refreshCode'])->name('classrooms.code.refresh');
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    Route::post('/classrooms/{classroom}/leave', [ClassroomController::class, 'leave'])->name('classrooms.leave');
    Route::get('/classrooms/{classroom}/classwork', [ClassroomController::class, 'classwork'])->name('classrooms.classwork');
    Route::get('/classrooms/{classroom}/people', [ClassroomController::class, 'people'])->name('classrooms.people');
    Route::get('/classrooms/{classroom}/grades', [ClassroomController::class, 'grades'])->name('classrooms.grades');
    Route::delete('/classrooms/{classroom}/students/{user}', [ClassroomController::class, 'removeStudent'])->name('classrooms.students.remove');

    // Pengumuman
    Route::post('/classrooms/{classroom}/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Materi
    Route::post('/classrooms/{classroom}/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

    // Tugas
    Route::post('/classrooms/{classroom}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('/assignments/{assignment}/download', [AssignmentController::class, 'download'])->name('assignments.download');
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');

    // Pengumpulan tugas
    Route::post('/assignments/{assignment}/submit', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::put('/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');
    Route::get('/submissions/{submission}/download', [SubmissionController::class, 'download'])->name('submissions.download');

    // Kuis & soal
    Route::post('/classrooms/{classroom}/quizzes', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
    Route::put('/quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
    Route::put('/quizzes/{quiz}/security', [QuizController::class, 'updateSecurity'])->name('quizzes.security.update');
    Route::post('/quizzes/{quiz}/publish', [QuizController::class, 'togglePublish'])->name('quizzes.publish');
    Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
    Route::post('/quizzes/{quiz}/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::post('/quizzes/{quiz}/question-groups', [QuestionController::class, 'storeGroup'])->name('question-groups.store');
    Route::get('/question-groups/{questionGroup}/media', [QuestionController::class, 'groupMedia'])->name('question-groups.media');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::delete('/question-groups/{questionGroup}', [QuestionController::class, 'destroyGroup'])->name('question-groups.destroy');

    // Pengerjaan kuis
    Route::post('/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])->name('attempts.start');
    Route::get('/attempts/{attempt}/take', [QuizAttemptController::class, 'take'])->name('attempts.take');
    Route::post('/attempts/{attempt}/answers', [QuizAttemptController::class, 'saveAnswers'])->name('attempts.answers.save');
    Route::post('/attempts/{attempt}/security-events', [QuizAttemptController::class, 'securityEvent'])->name('attempts.security-events.store');
    Route::post('/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('attempts.submit');
    Route::get('/attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('attempts.show');
    Route::put('/attempts/{attempt}/grade', [QuizAttemptController::class, 'grade'])->name('attempts.grade');

    // Video Interaktif: modul native LMS, iframe internal same-origin, tanpa API key.
    Route::post('/classrooms/{classroom}/video-lessons', [VideoLessonController::class, 'store'])->name('video-lessons.store');
    Route::get('/video-lessons/{videoLesson}', [VideoLessonController::class, 'show'])->name('video-lessons.show');
    Route::get('/video-lessons/{videoLesson}/player', [VideoLessonController::class, 'player'])->name('video-lessons.player');
    Route::post('/video-lessons/{videoLesson}/answer', [VideoLessonController::class, 'answer'])->middleware('throttle:30,1')->name('video-lessons.answer');
    Route::get('/video-lessons/{videoLesson}/edit', [VideoLessonController::class, 'edit'])->name('video-lessons.edit');
    Route::put('/video-lessons/{videoLesson}', [VideoLessonController::class, 'update'])->name('video-lessons.update');
    Route::put('/video-lessons/{videoLesson}/playback', [VideoLessonController::class, 'updatePlayback'])->name('video-lessons.playback.update');
    Route::post('/video-lessons/{videoLesson}/publish', [VideoLessonController::class, 'publish'])->name('video-lessons.publish');
    Route::delete('/video-lessons/{videoLesson}', [VideoLessonController::class, 'destroy'])->name('video-lessons.destroy');
    Route::post('/video-lessons/{videoLesson}/checkpoints', [VideoLessonController::class, 'checkpointStore'])->name('video-checkpoints.store');
    Route::put('/video-lessons/{videoLesson}/checkpoints/{checkpoint}', [VideoLessonController::class, 'checkpointUpdate'])->name('video-checkpoints.update');
    Route::delete('/video-lessons/{videoLesson}/checkpoints/{checkpoint}', [VideoLessonController::class, 'checkpointDestroy'])->name('video-checkpoints.destroy');

    // Admin: kelola pengguna
    Route::middleware(RoleMiddleware::class.':admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::post('/users/import', [AdminUserController::class, 'importStudents'])->name('admin.users.import');
        Route::get('/users/import/template.csv', [AdminUserController::class, 'studentTemplateCsv'])->name('admin.users.import.template.csv');
        Route::get('/users/import/template.xlsx', [AdminUserController::class, 'studentTemplateXlsx'])->name('admin.users.import.template.xlsx');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
