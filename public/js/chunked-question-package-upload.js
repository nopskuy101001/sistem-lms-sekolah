(function () {
    var chunkSize = 1024 * 1024;
    var maximumSize = 120 * 1024 * 1024;

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
            return data.message || 'Unggahan Paket ZIP gagal.';
        } catch (_) {
            return 'Unggahan Paket ZIP gagal.';
        }
    }

    document.querySelectorAll('[data-question-package-form]').forEach(function (form) {
        var input = form.querySelector('[data-question-package-file]');
        var token = form.querySelector('[data-question-package-token]');
        var progress = form.querySelector('[data-question-package-progress]');
        var status = form.querySelector('[data-question-package-status]');
        var submit = form.querySelector('[type="submit"], button:not([type])');
        if (!input || !token || !progress || !status || !submit) return;

        input.addEventListener('change', function () {
            token.value = '';
            progress.value = 0;
            status.textContent = input.files[0] && /\.zip$/i.test(input.files[0].name)
                ? 'Paket ZIP akan diunggah bertahap saat tombol Import ditekan.'
                : '';
        });

        form.addEventListener('submit', async function (event) {
            var file = input.files[0];
            if (!file) {
                if (token.value) return;
                event.preventDefault();
                status.textContent = 'Pilih file CSV, Excel, atau Paket ZIP terlebih dahulu.';
                return;
            }
            if (!/\.zip$/i.test(file.name) || token.value || form.dataset.packageUploadReady === 'true') return;

            event.preventDefault();
            if (file.size > maximumSize) {
                status.textContent = 'Ukuran Paket ZIP melebihi batas 120 MB.';
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

                    var response = await fetch(form.dataset.packageUploadUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: payload,
                    });
                    if (!response.ok) throw new Error(await responseError(response));
                    var result = await response.json();
                    var percent = Math.round(((index + 1) / totalChunks) * 100);
                    progress.value = percent;
                    status.textContent = 'Mengunggah Paket ZIP... ' + percent + '%';
                    if (result.complete) token.value = result.token;
                }

                if (!token.value) throw new Error('Paket ZIP belum selesai dirakit oleh server.');
                status.textContent = 'Paket selesai diunggah. Memproses soal dan media...';
                form.dataset.packageUploadReady = 'true';
                input.value = '';
                input.disabled = false;
                form.requestSubmit();
            } catch (error) {
                status.textContent = error.message || 'Unggahan Paket ZIP gagal.';
                submit.disabled = false;
                input.disabled = false;
            }
        });
    });
})();
