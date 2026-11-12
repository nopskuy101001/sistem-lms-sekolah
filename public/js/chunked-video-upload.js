(function () {
    var chunkSize = 1024 * 1024;
    var maximumSize = 100 * 1024 * 1024;

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            return (character === 'x' ? random : (random & 3 | 8)).toString(16);
        });
    }

    async function responseError(response) {
        try {
            var data = await response.json();
            if (data.errors) return Object.values(data.errors).flat()[0];
            return data.message || 'Unggahan video gagal.';
        } catch (_) {
            return response.status === 413
                ? 'Potongan video ditolak server. Periksa batas unggahan server.'
                : 'Unggahan video gagal.';
        }
    }

    document.querySelectorAll('[data-chunked-video-form]').forEach(function (form) {
        var input = form.querySelector('[data-chunked-video-file]');
        var token = form.querySelector('[data-chunked-video-token]');
        var status = form.querySelector('[data-video-upload-status]');
        var progress = form.querySelector('[data-video-upload-progress]');
        var submit = form.querySelector('[type="submit"], button:not([type])');
        var sourceType = form.querySelector('[data-video-source-type]');
        if (!input || !token || !status || !progress || !submit) return;

        input.addEventListener('change', function () {
            token.value = '';
            progress.value = 0;
            if (input.files[0] && sourceType) sourceType.value = 'mp4';
            status.textContent = input.files[0]
                ? 'Siap mengunggah ' + input.files[0].name + ' saat formulir disimpan.'
                : '';
        });

        form.addEventListener('submit', async function (event) {
            var file = input.files[0];
            if (!file || token.value || form.dataset.videoUploadReady === 'true') return;
            event.preventDefault();
            if (!/\.mp4$/i.test(file.name)) {
                status.textContent = 'Pilih file dengan ekstensi .mp4.';
                return;
            }
            if (file.size > maximumSize) {
                status.textContent = 'Ukuran video melebihi batas 100 MB.';
                return;
            }

            var uploadId = uuid();
            var totalChunks = Math.ceil(file.size / chunkSize);
            var csrf = form.querySelector('[name="_token"]')?.value || '';
            submit.disabled = true;
            input.disabled = true;

            try {
                for (var index = 0; index < totalChunks; index++) {
                    var payload = new FormData();
                    payload.append('upload_id', uploadId);
                    payload.append('chunk_index', String(index));
                    payload.append('total_chunks', String(totalChunks));
                    payload.append('original_name', file.name);
                    payload.append('total_size', String(file.size));
                    payload.append('chunk', file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize)), 'chunk.part');

                    var response = await fetch(form.dataset.videoUploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: payload,
                    });
                    if (!response.ok) throw new Error(await responseError(response));
                    var result = await response.json();
                    var percent = Math.round(((index + 1) / totalChunks) * 100);
                    progress.value = percent;
                    status.textContent = 'Mengunggah video... ' + percent + '%';
                    if (result.complete) token.value = result.token;
                }

                if (!token.value) throw new Error('Video belum selesai dirakit oleh server.');
                status.textContent = 'Video selesai diunggah. Menyimpan formulir...';
                form.dataset.videoUploadReady = 'true';
                input.value = '';
                input.disabled = false;
                form.requestSubmit();
            } catch (error) {
                status.textContent = error.message || 'Unggahan video gagal.';
                submit.disabled = false;
                input.disabled = false;
            }
        });
    });
})();
