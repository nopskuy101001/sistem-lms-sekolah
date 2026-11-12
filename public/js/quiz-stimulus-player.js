(function () {
    function formatTime(seconds) {
        if (!Number.isFinite(seconds)) return '--:--';
        var value = Math.max(0, Math.floor(seconds));
        var hours = Math.floor(value / 3600);
        var minutes = Math.floor((value % 3600) / 60);
        var remainder = value % 60;

        return (hours ? String(hours).padStart(2, '0') + ':' : '')
            + String(minutes).padStart(2, '0') + ':'
            + String(remainder).padStart(2, '0');
    }

    document.querySelectorAll('[data-stimulus-self-player]').forEach(function (player) {
        if (player.dataset.playerReady === 'true') return;
        player.dataset.playerReady = 'true';

        var video = player.querySelector('video');
        var play = player.querySelector('[data-video-play]');
        var progress = player.querySelector('[data-video-progress]');
        var time = player.querySelector('[data-video-time]');
        var mute = player.querySelector('[data-video-mute]');
        var volume = player.querySelector('[data-video-volume]');

        function updatePlayState() {
            var paused = video.paused || video.ended;
            play.textContent = paused ? '\u25b6' : '\u275a\u275a';
            play.setAttribute('aria-label', paused ? 'Putar video' : 'Jeda video');
        }

        function updateTimeline() {
            var duration = Number.isFinite(video.duration) ? video.duration : 0;
            progress.value = duration ? Math.round((video.currentTime / duration) * 1000) : 0;
            time.textContent = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
        }

        function updateVolume() {
            mute.textContent = video.muted || video.volume === 0 ? '\ud83d\udd07' : '\ud83d\udd0a';
            mute.setAttribute('aria-label', video.muted ? 'Aktifkan suara' : 'Matikan suara');
            volume.value = video.muted ? 0 : video.volume;
        }

        play.addEventListener('click', function () {
            if (video.paused || video.ended) {
                video.play().catch(function () {});
            } else {
                video.pause();
            }
        });
        video.addEventListener('click', function () {
            play.click();
        });
        video.addEventListener('dblclick', function (event) {
            event.preventDefault();
        });
        progress.addEventListener('input', function () {
            if (Number.isFinite(video.duration) && video.duration > 0) {
                video.currentTime = (Number(progress.value) / 1000) * video.duration;
            }
        });
        mute.addEventListener('click', function () {
            video.muted = !video.muted;
            updateVolume();
        });
        volume.addEventListener('input', function () {
            video.muted = false;
            video.volume = Number(volume.value);
            updateVolume();
        });

        ['play', 'pause', 'ended'].forEach(function (eventName) {
            video.addEventListener(eventName, updatePlayState);
        });
        ['loadedmetadata', 'durationchange', 'timeupdate'].forEach(function (eventName) {
            video.addEventListener(eventName, updateTimeline);
        });
        video.addEventListener('volumechange', updateVolume);

        updatePlayState();
        updateTimeline();
        updateVolume();
    });
})();
