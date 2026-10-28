/* VideoTask: YouTube IFrame API + native MP4 checkpoint player. No npm required. */
(() => {
    'use strict';
    const config = window.VideoTaskLesson;
    if (!config) return;

    const qs = (id) => document.getElementById(id);
    const overlay = qs('question-overlay');
    const questionTitle = qs('question-title');
    const questionTime = qs('question-time');
    const choicesElement = qs('choices');
    const answerForm = qs('answer-form');
    const answerButton = qs('submit-answer');
    const continueButton = qs('continue-button');
    const feedback = qs('answer-feedback');
    const instruction = qs('question-instruction');
    const status = qs('player-status');
    const errorBox = qs('player-error');
    const finishBanner = qs('finish-banner');
    const checkpointProgress = qs('checkpoint-progress');
    const questions = [...config.questions].sort((a, b) => a.time_seconds - b.time_seconds || a.id - b.id);
    const answered = new Set(config.answeredIds.map(Number));

    function updateProgress(answeredCount = answered.size, totalCount = questions.length) {
        if (checkpointProgress) {
            checkpointProgress.textContent = `${answeredCount}/${totalCount} checkpoint`;
        }
    }

    updateProgress();

    // Satu origin terdaftar per API client, tanpa wildcard '*'.
    function notifyParent(event, data = {}) {
        if (!config.embed || !config.parentOrigin || window.parent === window) return;
        window.parent.postMessage({type: `videotask:${event}`, lessonId: config.lessonId, ...data}, config.parentOrigin);
    }

    if (config.embed && config.parentOrigin) {
        window.addEventListener('message', (event) => {
            if (event.origin !== config.parentOrigin || event.source !== window.parent) return;
            if (event.data?.type === 'videotask:pause') pause();
            if (event.data?.type === 'videotask:resume' && !currentQuestion) play();
        });
    }


    let youtubePlayer = null;
    let mp4 = null;
    let ready = false;
    let currentQuestion = null;
    let selectedIndex = null;
    let submitting = false;
    let submitted = false;

    function clock(seconds) {
        const s = Math.floor(Math.max(0, seconds));
        return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function getTime() {
        if (!ready) return 0;
        if (mp4) return mp4.currentTime || 0;
        try { return youtubePlayer?.getCurrentTime() || 0; } catch { return 0; }
    }

    function pause() {
        if (mp4) mp4.pause();
        else if (youtubePlayer && ready) youtubePlayer.pauseVideo();
    }

    function play() {
        if (mp4) {
            mp4.play().catch(() => { status.textContent = 'Klik tombol Play pada video untuk melanjutkan.'; });
        } else if (youtubePlayer && ready) {
            youtubePlayer.playVideo();
        }
    }

    function nextQuestion() {
        return questions.find(q => !answered.has(Number(q.id)));
    }

    function reveal(q) {
        if (currentQuestion || submitting) return;
        pause();
        currentQuestion = q;
        selectedIndex = null;
        submitted = false;
        questionTitle.textContent = q.question_text;
        questionTime.textContent = clock(q.time_seconds);
        choicesElement.replaceChildren();
        feedback.replaceChildren();
        feedback.classList.add('hidden');
        continueButton.classList.add('hidden');
        answerForm.classList.remove('hidden');
        answerButton.disabled = true;
        answerButton.textContent = 'Kirim jawaban →';
        instruction.textContent = 'Pilih satu jawaban yang paling tepat.';

        q.choices.forEach((choice, index) => {
            const label = document.createElement('label');
            label.className = 'answer-choice';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'answer-option';
            input.value = String(index);
            input.addEventListener('change', () => {
                if (submitted) return;
                selectedIndex = index;
                answerButton.disabled = false;
                choicesElement.querySelectorAll('.answer-choice').forEach(el => el.classList.remove('selected'));
                label.classList.add('selected');
            });
            const bubble = document.createElement('span');
            bubble.className = 'choice-letter';
            bubble.textContent = String.fromCharCode(65 + index);
            const text = document.createElement('span');
            text.className = 'choice-text';
            text.textContent = choice;
            label.append(input, bubble, text);
            choicesElement.append(label);
        });

        overlay.classList.remove('hidden');
        status.textContent = `⏸ Video dijeda pada ${clock(q.time_seconds)} untuk menjawab pertanyaan`;
        notifyParent('checkpoint', {questionId: q.id, timeSeconds: q.time_seconds});
        overlay.querySelector('input')?.focus({ preventScroll: true });
    }

    function tick() {
        if (!ready) return;
        if (currentQuestion) {
            pause();
            return;
        }
        const upcoming = nextQuestion();
        if (upcoming && getTime() + 0.06 >= upcoming.time_seconds) {
            reveal(upcoming);
        }
    }

    answerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentQuestion || selectedIndex === null || submitting || submitted) return;
        submitting = true;
        answerButton.disabled = true;
        answerButton.textContent = 'Menyimpan jawaban...';
        pause();

        try {
            const response = config.preview ? null : await fetch(config.answerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(config.csrf ? {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content} : {}),
                },
                body: JSON.stringify({question_id: currentQuestion.id, selected_index: selectedIndex}),
            });
            const result = config.preview ? {
                correct: selectedIndex === currentQuestion.correct_index,
                correct_index: currentQuestion.correct_index,
                explanation: currentQuestion.explanation,
                answered_count: answered.size + 1,
                total_count: questions.length,
                completed: answered.size + 1 === questions.length,
                score: null,
            } : await response.json();
            if (response && !response.ok) throw new Error(result.message || (result.errors ? Object.values(result.errors).flat().join(' ') : 'Gagal menyimpan jawaban.'));

            submitting = false;
            submitted = true;
            answered.add(Number(currentQuestion.id));
            updateProgress(result.answered_count, result.total_count);
            const checkpoint = document.querySelector(`[data-checkpoint="${Number(currentQuestion.id)}"]`);
            if (checkpoint) {
                const icon = checkpoint.querySelector('.checkpoint-icon');
                icon.textContent = '✓';
                icon.classList.add('checkpoint-done');
                checkpoint.querySelector('span').textContent = 'Sudah dijawab';
            }
            choicesElement.querySelectorAll('.answer-choice').forEach((label, index) => {
                label.querySelector('input').disabled = true;
                if (index === Number(result.correct_index)) label.classList.add('correct-choice');
                if (index === selectedIndex && !result.correct) label.classList.add('wrong-choice');
            });
            feedback.className = `feedback ${result.correct ? 'feedback-correct' : 'feedback-wrong'}`;
            const heading = document.createElement('strong');
            heading.textContent = result.correct ? '✓ Jawaban benar!' : '✕ Jawaban belum tepat';
            feedback.append(heading);
            if (result.explanation) {
                const explanation = document.createElement('p');
                explanation.textContent = result.explanation;
                feedback.append(explanation);
            }
            answerButton.classList.add('hidden');
            continueButton.classList.remove('hidden');
            instruction.textContent = `${result.answered_count} dari ${result.total_count} pertanyaan telah dijawab.`;
            continueButton.textContent = result.completed ? '▶ Lanjutkan / selesai' : '▶ Lanjutkan video';
            notifyParent('answered', {
                questionId: currentQuestion.id, correct: !!result.correct,
                answeredCount: result.answered_count, totalCount: result.total_count,
            });
            if (result.completed) {
                finishBanner.classList.remove('hidden');
                notifyParent('completed', {
                    score: result.score,
                    totalCount: result.total_count,
                    percentage: config.preview ? null : result.score,
                });
            }
            continueButton.focus();
        } catch (error) {
            submitting = false;
            answerButton.disabled = false;
            answerButton.textContent = 'Coba kirim lagi →';
            showError(error.message || 'Terjadi kesalahan koneksi.');
        }
    });

    continueButton.addEventListener('click', () => {
        if (!submitted) return;
        overlay.classList.add('hidden');
        currentQuestion = null;
        errorBox.classList.add('hidden');
        answerButton.classList.remove('hidden');
        status.textContent = '▶ Video dilanjutkan';
        play();
        tick(); // Jika timestamp soal berikutnya sudah terlewati, tampilkan sekarang.
    });

    if (config.sourceType === 'mp4') {
        mp4 = qs('mp4-player');
        if (!mp4) return;
        ready = true;
        notifyParent('ready', {answeredCount: answered.size, totalCount: questions.length, completed: !!config.completed});
        mp4.addEventListener('timeupdate', tick);
        mp4.addEventListener('seeking', tick);
        mp4.addEventListener('play', tick);
        mp4.addEventListener('ended', () => {
            if (nextQuestion()) status.textContent = 'Video selesai, tetapi masih ada soal yang belum terjawab. Periksa timestamp soal.';
            else status.textContent = '✓ Video selesai. Anda dapat melihat hasil kuis.';
            notifyParent('video-ended', {completed: !nextQuestion()});
        });
        mp4.addEventListener('error', () => showError('Video MP4 tidak dapat diputar. Periksa URL atau jalankan php artisan storage:link untuk file unggahan.'));
        tick();
    } else if (config.sourceType === 'youtube' && config.youtubeId) {
        const initYoutube = () => {
            if (youtubePlayer || !window.YT?.Player) return;
            youtubePlayer = new window.YT.Player('youtube-player', {
                width: '100%', height: '100%', videoId: config.youtubeId,
                playerVars: {playsinline: 1, rel: 0, fs: 0, origin: window.location.origin, widget_referrer: config.parentOrigin || window.location.origin},
                events: {
                    onReady: () => { ready = true; tick(); status.textContent = '▶ Tekan Play untuk memulai'; notifyParent('ready', {answeredCount: answered.size, totalCount: questions.length, completed: !!config.completed}); },
                    onStateChange: (event) => {
                        if (event.data === window.YT.PlayerState.ENDED) {
                            status.textContent = nextQuestion() ? 'Video berakhir sebelum semua pertanyaan tampil.' : '✓ Video selesai. Lihat hasil kuis Anda.';
                            notifyParent('video-ended', {completed: !nextQuestion()});
                        } else if (event.data === window.YT.PlayerState.PLAYING) {
                            tick();
                        }
                    },
                    onError: () => showError('Video YouTube tidak dapat ditampilkan. Pastikan video publik dan mengizinkan embed.'),
                },
            });
        };
        window.onYouTubeIframeAPIReady = initYoutube;
        if (window.YT?.Player) initYoutube();
        setInterval(tick, 200); // API YouTube tidak memiliki event timeupdate.
    }
})();
