/* VideoTask checkpoint editor: duration-aware sliders for YouTube and MP4. */
(() => {
    'use strict';

    const config = window.VideoTaskEditor;
    if (!config) return;

    const sliders = [...document.querySelectorAll('.video-time-range')];
    const durationLabel = document.getElementById('video-editor-duration');
    const positionLabel = document.getElementById('video-editor-position');
    const mp4 = document.getElementById('video-editor-mp4');
    let youtubePlayer = null;
    let youtubeReady = false;
    let duration = 0;
    let fallbackTimer = null;

    if (!sliders.length) return;

    sliders.forEach((slider) => {
        slider.disabled = true;
    });

    function clock(seconds) {
        const value = Math.max(0, Math.floor(Number(seconds) || 0));
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        const remainder = value % 60;

        if (hours > 0) {
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
    }

    function updateOutput(slider) {
        const output = document.querySelector(`[data-time-output-for="${slider.id}"]`);
        if (output) output.textContent = clock(slider.value);
    }

    function currentTime() {
        if (mp4) return Number(mp4.currentTime) || 0;
        if (youtubePlayer && youtubeReady) {
            try {
                return Number(youtubePlayer.getCurrentTime()) || 0;
            } catch {
                return 0;
            }
        }
        return 0;
    }

    function pausePreview() {
        if (mp4) mp4.pause();
        if (youtubePlayer && youtubeReady) youtubePlayer.pauseVideo();
    }

    function seekPreview(seconds) {
        const target = Math.max(0, Math.min(Number(seconds) || 0, duration || 86400));
        pausePreview();

        if (mp4 && Number.isFinite(mp4.duration)) {
            mp4.currentTime = target;
        } else if (youtubePlayer && youtubeReady) {
            youtubePlayer.seekTo(target, true);
        }

        if (positionLabel) positionLabel.textContent = `Posisi video ${clock(target)}`;
    }

    function applyDuration(seconds) {
        const detected = Number(seconds);
        if (!Number.isFinite(detected) || detected <= 0) return false;

        duration = detected;
        const maximum = Math.min(86400, Math.max(0, Math.ceil(detected) - 1));

        sliders.forEach((slider) => {
            const initial = Number(slider.dataset.initialTime ?? slider.value) || 0;
            slider.max = String(maximum);
            slider.value = String(Math.min(Math.max(0, initial), maximum));
            slider.disabled = false;
            updateOutput(slider);
        });

        if (durationLabel) {
            durationLabel.textContent = `Durasi ${clock(detected)} · slider 00:00–${clock(maximum)}`;
        }
        if (fallbackTimer) clearTimeout(fallbackTimer);
        return true;
    }

    function enableFallback(message) {
        if (duration > 0) return;
        sliders.forEach((slider) => {
            slider.disabled = false;
            updateOutput(slider);
        });
        if (durationLabel) durationLabel.textContent = message;
    }

    sliders.forEach((slider) => {
        slider.addEventListener('input', () => {
            updateOutput(slider);
            seekPreview(slider.value);
        });
    });

    document.querySelectorAll('[data-use-player-time]').forEach((button) => {
        button.addEventListener('click', () => {
            const slider = document.getElementById(button.dataset.timeTarget);
            if (!slider || slider.disabled) return;

            const maximum = Number(slider.max) || 86400;
            slider.value = String(Math.min(Math.floor(currentTime()), maximum));
            updateOutput(slider);
            seekPreview(slider.value);
        });
    });

    if (mp4) {
        const readMp4Duration = () => applyDuration(mp4.duration);
        mp4.addEventListener('loadedmetadata', readMp4Duration);
        mp4.addEventListener('durationchange', readMp4Duration);
        mp4.addEventListener('timeupdate', () => {
            if (positionLabel) positionLabel.textContent = `Posisi video ${clock(mp4.currentTime)}`;
        });
        mp4.addEventListener('error', () => enableFallback('Durasi MP4 gagal dibaca; periksa kembali sumber video.'));
        if (mp4.readyState >= 1) readMp4Duration();
    } else if (config.sourceType === 'youtube' && config.youtubeId) {
        const initYoutube = () => {
            if (youtubePlayer || !window.YT?.Player) return;

            const readYoutubeDuration = (attempt = 0) => {
                if (applyDuration(youtubePlayer.getDuration()) || attempt >= 20) return;
                setTimeout(() => readYoutubeDuration(attempt + 1), 300);
            };

            youtubePlayer = new window.YT.Player('video-editor-youtube', {
                width: '100%',
                height: '100%',
                videoId: config.youtubeId,
                playerVars: {
                    playsinline: 1,
                    rel: 0,
                    origin: window.location.origin,
                },
                events: {
                    onReady: (event) => {
                        youtubeReady = true;
                        readYoutubeDuration();
                    },
                    onStateChange: () => {
                        if (!duration) applyDuration(youtubePlayer.getDuration());
                    },
                    onError: () => enableFallback('Durasi YouTube gagal dibaca; periksa akses dan izin embed video.'),
                },
            });

            setInterval(() => {
                if (youtubeReady && positionLabel) {
                    positionLabel.textContent = `Posisi video ${clock(currentTime())}`;
                }
            }, 250);
        };

        window.onYouTubeIframeAPIReady = initYoutube;
        if (window.YT?.Player) initYoutube();
    } else {
        enableFallback('Sumber video tidak tersedia untuk membaca durasi.');
    }

    fallbackTimer = setTimeout(() => {
        enableFallback('Durasi belum berhasil dideteksi; muat ulang halaman atau periksa sumber video.');
    }, 10000);
})();
