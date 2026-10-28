@extends('layouts.app')
@section('title', 'Ujian · '.$quiz->title)

@section('content')
<div class="exam-shell" data-monitoring="{{ $quiz->monitoring_enabled ? 'on' : 'off' }}">
    <header class="exam-toolbar">
        <div class="exam-title">
            <span>{{ $quiz->monitoring_enabled ? 'Mode Ujian Terpantau' : ($quiz->require_fullscreen ? 'Mode Ujian Layar Penuh' : 'Mode Ujian') }}</span>
            <h1>{{ $quiz->title }}</h1>
        </div>
        <div class="exam-toolbar-stats">
            <span class="exam-status is-saved" id="save-status">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>
                <span>Tersimpan</span>
            </span>
            @if($quiz->monitoring_enabled || $quiz->require_fullscreen)
                <span class="exam-status {{ $violationCount > 0 ? 'is-alert' : '' }}" id="integrity-status" title="Jumlah peringatan integritas">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                    <span><b id="violation-count">{{ $violationCount }}</b> peringatan</span>
                </span>
            @endif
            @if($deadline)
                <span class="exam-status exam-timer">Sisa <strong id="countdown">--:--</strong></span>
            @endif
        </div>
    </header>

    <form method="POST" action="{{ route('attempts.submit', $attempt) }}" id="quiz-form">
        @csrf
        <div class="exam-layout">
            <aside class="exam-sidebar">
                <div class="exam-progress"><span id="answer-progress" style="width:0%"></span></div>
                <div class="exam-progress-copy"><span>Terjawab</span><strong><b id="answered-count">0</b>/{{ $quiz->questions->count() }}</strong></div>
                <details class="exam-navigation" id="exam-navigation" open>
                <summary>Navigasi Soal</summary>
                <p>Pilih nomor untuk berpindah soal. Jawaban tersimpan otomatis.</p>
                <nav class="exam-question-nav" aria-label="Nomor soal">
                    @foreach($quiz->questions as $i => $question)
                        <a href="#question-{{ $question->id }}" data-question-link="{{ $question->id }}">{{ $i + 1 }}</a>
                    @endforeach
                </nav>
                @if($quiz->monitoring_enabled)
                    <div class="exam-integrity-note">
                        <b>Mode terpantau aktif.</b><br>
                        Perpindahan tab dan hilangnya fokus dapat dicatat untuk ditinjau guru{{ $quiz->require_fullscreen ? ', termasuk ketika keluar dari layar penuh' : '' }}.
                    </div>
                @elseif($quiz->require_fullscreen)
                    <div class="exam-integrity-note">
                        <b>Layar penuh wajib.</b><br>
                        Keluar dari layar penuh akan menambah peringatan dan kamu harus masuk kembali sebelum melanjutkan.
                    </div>
                @endif
                @if($quiz->penalty_enabled)
                    <div class="exam-integrity-note">
                        <b>Penalti nilai aktif.</b><br>
                        Setiap peringatan memotong {{ $quiz->penalty_percent_per_violation }}% dari total poin, maksimal {{ \App\Models\Quiz::MAX_VIOLATION_PENALTY_PERCENT }}%.
                    </div>
                @endif
                </details>
            </aside>

            <section class="exam-questions">
                @foreach($quiz->questions as $i => $question)
                    @php $saved = $question->decodeAnswer($savedAnswers->get($question->id)?->answer); @endphp
                    @if($question->group && ($i === 0 || $quiz->questions[$i - 1]->question_group_id !== $question->question_group_id))
                        <section class="quiz-stimulus-card rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5 mb-4">
                            <div class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">Stimulus TKA</div>
                            @if($question->group->title)<h2 class="quiz-readable-text font-semibold text-slate-900 mb-2">{{ $question->group->title }}</h2>@endif
                            @if($question->group->stimulus)<div class="quiz-readable-text text-sm leading-7 text-slate-700">{{ $question->group->stimulus }}</div>@endif
                            @include('quizzes.partials.stimulus-media', ['group' => $question->group])
                        </section>
                    @endif
                    <article class="exam-question quiz-question-card" id="question-{{ $question->id }}" data-question="{{ $question->id }}">
                        <div class="exam-question-head">
                            <span class="exam-question-number">Soal {{ $i + 1 }} dari {{ $quiz->questions->count() }} · {{ $question->typeLabel() }}</span>
                            <span class="exam-points">{{ $question->points }} poin</span>
                        </div>
                        @if($question->cognitiveLevelLabel())
                            <div class="text-xs text-indigo-600 mb-2">Level kognitif: {{ $question->cognitiveLevelLabel() }}</div>
                        @endif
                        <p class="exam-question-text quiz-readable-text">{{ $question->body }}</p>

                        @if($question->isPilihanGanda())
                            <div class="exam-options">
                                @foreach($question->options() as $key => $text)
                                    <label class="exam-option">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" @checked($saved === $key)>
                                        <span class="exam-option-key">{{ strtoupper($key) }}</span>
                                        <span class="quiz-readable-text">{{ $text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($question->isPilihanGandaKompleks())
                            <p class="text-xs text-slate-500 mb-3">Pilih semua jawaban yang benar.</p>
                            <div class="exam-options">
                                @foreach($question->options() as $key => $text)
                                    <label class="exam-option">
                                        <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $key }}" @checked(in_array($key, $saved, true))>
                                        <span class="exam-option-key">{{ strtoupper($key) }}</span>
                                        <span class="quiz-readable-text">{{ $text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($question->isKategori())
                            <p class="text-xs text-slate-500 mb-3">Tentukan kategori Benar atau Salah untuk setiap pernyataan.</p>
                            <div class="space-y-3">
                                @foreach($question->category_items ?? [] as $itemIndex => $item)
                                    <div class="rounded-xl border border-slate-200 p-4">
                                        <p class="quiz-readable-text text-sm mb-3"><b>{{ $itemIndex + 1 }}.</b> {{ $item['statement'] }}</p>
                                        <div class="flex gap-4 text-sm">
                                            @foreach(['benar' => 'Benar', 'salah' => 'Salah'] as $value => $label)
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" name="answers[{{ $question->id }}][{{ $itemIndex }}]" value="{{ $value }}" @checked(($saved[$itemIndex] ?? null) === $value)>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <textarea name="answers[{{ $question->id }}]" rows="5" placeholder="Tulis jawaban kamu di sini..." class="exam-answer">{{ $saved }}</textarea>
                        @endif
                    </article>
                @endforeach

                <div class="exam-submit">
                    <p>Pastikan semua jawaban sudah terisi. Setelah dikumpulkan, jawaban tidak dapat diubah kembali.</p>
                    <button type="submit" class="lms-button lms-button-primary">Kumpulkan jawaban</button>
                </div>
            </section>
        </div>
    </form>
</div>

@if($quiz->monitoring_enabled || $quiz->require_fullscreen)
    <div class="exam-entry" id="exam-entry">
        <div class="exam-entry-card">
            <div class="exam-entry-icon"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg></div>
            <h2>{{ $quiz->monitoring_enabled && $quiz->require_fullscreen ? 'Mode ujian terpantau & layar penuh' : ($quiz->require_fullscreen ? 'Mode layar penuh wajib' : 'Mode ujian terpantau') }}</h2>
            <p>
                @if($quiz->monitoring_enabled)
                    Sistem mencatat perubahan fokus pada halaman—bukan isi aplikasi, pesan, kamera, atau layar perangkatmu.
                @else
                    Kuis harus dikerjakan dalam layar penuh. Keluar dari layar penuh akan dicatat sebagai peringatan, tetapi pemantauan perpindahan tab lainnya tidak diaktifkan.
                @endif
            </p>
            <div class="exam-rules">
                <div class="exam-rule"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v14H3zM8 21h8M12 17v4"/></svg><span>Tetap berada pada halaman kuis sampai jawaban dikumpulkan.</span></div>
                @if($quiz->monitoring_enabled)
                    <div class="exam-rule"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg><span>Perpindahan tab atau jendela akan menjadi indikator yang dapat ditinjau guru.</span></div>
                @endif
                @if($quiz->require_fullscreen)
                    <div class="exam-rule"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h6M4 4v6M20 4h-6M20 4v6M4 20h6M4 20v-6M20 20h-6M20 20v-6"/></svg><span>Layar penuh diwajibkan selama kuis. Jika keluar, siswa harus masuk kembali untuk melanjutkan.</span></div>
                @endif
                @if($quiz->penalty_enabled)
                    <div class="exam-rule"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Setiap peringatan mengurangi {{ $quiz->penalty_percent_per_violation }}% dari total poin kuis, dengan batas total {{ \App\Models\Quiz::MAX_VIOLATION_PENALTY_PERCENT }}%.</span></div>
                @endif
                @if($quiz->shuffle_questions)
                    <div class="exam-rule"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg><span>Urutan soal diacak untuk setiap siswa dan tetap sama selama percobaan ini.</span></div>
                @endif
            </div>
            <p id="fullscreen-error" class="text-xs text-red-600 mb-3" hidden>Browser tidak mengizinkan layar penuh. Izinkan fullscreen atau gunakan browser lain untuk mengerjakan kuis.</p>
            <button type="button" class="lms-button lms-button-primary w-full" id="enter-exam">Saya mengerti, mulai mengerjakan</button>
        </div>
    </div>

    <div class="exam-warning" id="focus-warning" role="dialog" aria-modal="true" aria-labelledby="focus-warning-title">
        <div class="exam-warning-card">
            <span><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg></span>
            <h3 id="focus-warning-title">{{ $quiz->require_fullscreen ? 'Mode layar penuh terputus' : 'Fokus kuis terputus' }}</h3>
            <p>{{ $quiz->require_fullscreen ? 'Satu peringatan keluar dari layar penuh telah dicatat untuk ditinjau guru.' : 'Aktivitas ini dicatat sebagai indikator untuk ditinjau guru.' }}@if($quiz->penalty_enabled) Peringatan ini mengurangi {{ $quiz->penalty_percent_per_violation }}% dari total poin kuis.@endif Kembali ke mode ujian untuk melanjutkan.</p>
            <button type="button" class="lms-button lms-button-primary" id="return-to-exam">Kembali mengerjakan</button>
        </div>
    </div>
@endif

<script>
(function () {
    document.body.classList.add('exam-mode');

    const form = document.getElementById('quiz-form');
    const csrf = @json(csrf_token());
    const saveUrl = @json(route('attempts.answers.save', $attempt));
    const securityUrl = @json(route('attempts.security-events.store', $attempt));
    const monitoringEnabled = @json((bool) $quiz->monitoring_enabled);
    const fullscreenRequired = @json((bool) $quiz->require_fullscreen);
    const securityModeEnabled = monitoringEnabled || fullscreenRequired;
    const totalQuestions = {{ $quiz->questions->count() }};
    const saveStatus = document.getElementById('save-status');
    const saveStatusText = saveStatus.querySelector('span');
    const warning = document.getElementById('focus-warning');
    let examActive = false;
    let submitting = false;
    let autoSubmitting = false;
    let hiddenAt = null;
    let blurTimer = null;
    let fullscreenWasActive = false;
    let violationCount = {{ $violationCount }};
    const saveTimers = new Map();

    const examShell = document.querySelector('.exam-shell');
    const toolbar = document.querySelector('.exam-toolbar');
    const navigation = document.getElementById('exam-navigation');
    const compactNavigation = window.matchMedia('(max-width: 760px), (max-height: 520px) and (max-width: 920px)');
    const syncNavigation = () => { navigation.open = !compactNavigation.matches; };
    syncNavigation();
    compactNavigation.addEventListener('change', syncNavigation);
    const updateToolbarOffset = () => {
        const top = parseFloat(getComputedStyle(toolbar).top) || 0;
        examShell.style.setProperty('--exam-content-offset', Math.ceil(toolbar.getBoundingClientRect().height + top + 12) + 'px');
    };
    if ('ResizeObserver' in window) new ResizeObserver(updateToolbarOffset).observe(toolbar);
    window.addEventListener('resize', updateToolbarOffset);
    document.fonts?.ready.then(updateToolbarOffset);
    updateToolbarOffset();
    navigation.querySelectorAll('[data-question-link]').forEach(link => {
        link.addEventListener('click', event => {
            const question = document.getElementById('question-' + link.dataset.questionLink);
            if (!question) return;
            event.preventDefault();
            if (compactNavigation.matches) navigation.open = false;
            requestAnimationFrame(() => {
                updateToolbarOffset();
                question.setAttribute('tabindex', '-1');
                question.focus({ preventScroll: true });
                question.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
            });
        });
    });

    function setSaveState(state, text) {
        saveStatus.classList.remove('is-saving', 'is-saved', 'is-alert');
        saveStatus.classList.add(state);
        saveStatusText.textContent = text;
    }

    function setViolationCount(count) {
        violationCount = Number(count);
        const countElement = document.getElementById('violation-count');
        const status = document.getElementById('integrity-status');
        if (countElement) countElement.textContent = violationCount;
        if (status) status.classList.toggle('is-alert', violationCount > 0);
    }

    function showWarning() {
        if (!warning || submitting) return;
        warning.classList.add('show');
    }

    async function postJson(url, payload, keepalive = false) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) throw new Error('Request gagal');
        return response.json();
    }

    function recordEvent(eventType, durationSeconds = null, keepalive = false) {
        const mayRecord = monitoringEnabled || (fullscreenRequired && eventType === 'fullscreen_exit');
        if (!mayRecord || !examActive || submitting) return;

        const payload = {
            event_type: eventType,
            duration_seconds: durationSeconds,
            metadata: {
                visibility_state: document.visibilityState,
                fullscreen: Boolean(document.fullscreenElement),
                viewport: window.innerWidth + 'x' + window.innerHeight,
            },
        };

        if (['tab_hidden', 'window_blur', 'fullscreen_exit'].includes(eventType)) {
            setViolationCount(violationCount + 1);
        }

        postJson(securityUrl, payload, keepalive)
            .then(data => setViolationCount(data.violation_count))
            .catch(() => {});
    }

    function answerValue(questionId) {
        const fields = [...form.querySelectorAll('[name^="answers[' + questionId + ']"]')];
        if (!fields.length) return '';
        if (fields[0].type === 'checkbox') return fields.filter(field => field.checked).map(field => field.value);
        const isCategory = fields.some(field => /\[\d+\]\[\d+\]$/.test(field.name));
        if (isCategory) {
            const values = {};
            fields.filter(field => field.checked).forEach(field => {
                const index = field.name.match(/\[\d+\]\[(\d+)\]/)?.[1];
                if (index !== undefined) values[index] = field.value;
            });
            return values;
        }
        if (fields[0].type === 'radio') return fields.find(field => field.checked)?.value || '';
        return fields[0].value;
    }

    function isAnswered(value) {
        if (Array.isArray(value)) return value.length > 0;
        if (value && typeof value === 'object') return Object.keys(value).length > 0;
        return String(value).trim() !== '';
    }

    function updateProgress() {
        let answered = 0;
        document.querySelectorAll('[data-question]').forEach(question => {
            const id = question.dataset.question;
            const hasAnswer = isAnswered(answerValue(id));
            document.querySelector('[data-question-link="' + id + '"]')?.classList.toggle('answered', hasAnswer);
            if (hasAnswer) answered++;
        });
        document.getElementById('answered-count').textContent = answered;
        document.getElementById('answer-progress').style.width = (totalQuestions ? (answered / totalQuestions) * 100 : 0) + '%';
    }

    function saveAnswer(questionId) {
        setSaveState('is-saving', 'Menyimpan...');
        postJson(saveUrl, { answers: { [questionId]: answerValue(questionId) } })
            .then(() => setSaveState('is-saved', 'Tersimpan'))
            .catch(() => setSaveState('is-alert', 'Belum tersimpan'));
    }

    form.querySelectorAll('[name^="answers["]').forEach(field => {
        const questionId = field.name.match(/\[(\d+)\]/)?.[1];
        const eventName = ['radio', 'checkbox'].includes(field.type) ? 'change' : 'input';
        field.addEventListener(eventName, () => {
            updateProgress();
            clearTimeout(saveTimers.get(questionId));
            saveTimers.set(questionId, setTimeout(() => saveAnswer(questionId), ['radio', 'checkbox'].includes(field.type) ? 0 : 700));
        });
    });
    updateProgress();

    form.addEventListener('submit', event => {
        if (!autoSubmitting && !window.confirm('Kumpulkan jawaban sekarang? Jawaban tidak bisa diubah lagi.')) {
            event.preventDefault();
            return;
        }
        submitting = true;
        setSaveState('is-saving', 'Mengumpulkan...');
    });

    if (securityModeEnabled) {
        document.getElementById('enter-exam').addEventListener('click', async () => {
            const fullscreenError = document.getElementById('fullscreen-error');
            if (fullscreenRequired) {
                if (!document.documentElement.requestFullscreen) {
                    fullscreenError.hidden = false;
                    return;
                }
                try {
                    await document.documentElement.requestFullscreen();
                    fullscreenWasActive = true;
                } catch (_) {
                    fullscreenError.hidden = false;
                    return;
                }
            }
            examActive = true;
            document.getElementById('exam-entry').hidden = true;
        });

        document.getElementById('return-to-exam').addEventListener('click', async () => {
            if (fullscreenRequired && !document.fullscreenElement && document.documentElement.requestFullscreen) {
                try {
                    await document.documentElement.requestFullscreen();
                    fullscreenWasActive = true;
                } catch (_) {
                    return;
                }
            }
            warning.classList.remove('show');
        });

        if (monitoringEnabled) {
            document.addEventListener('visibilitychange', () => {
                if (!examActive || submitting) return;
                if (document.hidden) {
                    hiddenAt = Date.now();
                    recordEvent('tab_hidden', null, true);
                } else if (hiddenAt) {
                    const duration = Math.max(0, Math.round((Date.now() - hiddenAt) / 1000));
                    hiddenAt = null;
                    recordEvent('focus_restored', duration);
                    showWarning();
                }
            });

            window.addEventListener('blur', () => {
                clearTimeout(blurTimer);
                blurTimer = setTimeout(() => {
                    if (examActive && !document.hidden && !submitting) {
                        recordEvent('window_blur');
                    }
                }, 350);
            });

            window.addEventListener('focus', () => {
                clearTimeout(blurTimer);
                if (examActive && !document.hidden && violationCount > 0) showWarning();
            });
        }

        document.addEventListener('fullscreenchange', () => {
            if (examActive && fullscreenRequired && fullscreenWasActive && !document.fullscreenElement && !submitting) {
                recordEvent('fullscreen_exit');
                showWarning();
            }
        });
    }

    window.addEventListener('beforeunload', event => {
        if (submitting || !examActive) return;
        event.preventDefault();
        event.returnValue = '';
    });

    @if($deadline)
        let remaining = {{ (int) max(0, floor(now()->diffInSeconds($deadline, false))) }};
        const countdown = document.getElementById('countdown');
        function tick() {
            if (remaining <= 0) {
                countdown.textContent = '00:00';
                autoSubmitting = true;
                submitting = true;
                form.requestSubmit();
                return;
            }
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            countdown.textContent = (hours > 0 ? String(hours).padStart(2, '0') + ':' : '')
                + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            remaining--;
            window.setTimeout(tick, 1000);
        }
        tick();
    @endif
})();
</script>
@endsection
