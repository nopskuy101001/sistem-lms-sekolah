<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChunkedQuestionPackageUploadService
{
    public const MAX_BYTES = 125_829_120;

    /** @return array{complete:bool,received:int,total:int,token:?string} */
    public function accept(
        User $user,
        UploadedFile $chunk,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $originalName,
        int $totalSize,
    ): array {
        $this->validateToken($uploadId);
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks || $totalChunks > 120) {
            throw ValidationException::withMessages(['chunk' => 'Urutan potongan Paket ZIP tidak valid.']);
        }
        if ($totalSize < 1 || $totalSize > self::MAX_BYTES || ! preg_match('/\.zip$/i', $originalName)) {
            throw ValidationException::withMessages(['chunk' => 'Paket bank soal harus berupa ZIP dengan ukuran maksimal 120 MB.']);
        }

        $directory = 'temporary/question-package-chunks/'.$user->id.'/'.$uploadId;
        $manifestPath = $directory.'/manifest.json';
        $manifest = [
            'user_id' => $user->id,
            'upload_id' => $uploadId,
            'original_name' => basename($originalName),
            'total_chunks' => $totalChunks,
            'total_size' => $totalSize,
        ];
        if (Storage::disk('local')->exists($manifestPath)) {
            $existing = json_decode((string) Storage::disk('local')->get($manifestPath), true);
            foreach (array_keys($manifest) as $field) {
                if (($existing[$field] ?? null) != $manifest[$field]) {
                    throw ValidationException::withMessages(['chunk' => 'Data sesi unggahan Paket ZIP berubah. Pilih ulang file.']);
                }
            }
        } else {
            Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
        }

        if (! $chunk->storeAs($directory, 'chunk-'.$chunkIndex.'.part', 'local')) {
            throw ValidationException::withMessages(['chunk' => 'Potongan Paket ZIP gagal disimpan.']);
        }
        $received = collect(Storage::disk('local')->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.part'))->count();
        if ($received < $totalChunks) {
            return ['complete' => false, 'received' => $received, 'total' => $totalChunks, 'token' => null];
        }

        $stagingDirectory = 'temporary/question-package-staging/'.$user->id;
        $stagingPath = $stagingDirectory.'/'.$uploadId.'.zip';
        Storage::disk('local')->makeDirectory($stagingDirectory);
        $output = fopen(Storage::disk('local')->path($stagingPath), 'wb');
        if (! $output) {
            throw new RuntimeException('File staging Paket ZIP tidak dapat dibuat.');
        }
        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $partPath = $directory.'/chunk-'.$index.'.part';
                $input = Storage::disk('local')->readStream($partPath);
                if (! is_resource($input)) {
                    throw ValidationException::withMessages(['chunk' => 'Ada potongan Paket ZIP yang belum diterima.']);
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        if (Storage::disk('local')->size($stagingPath) !== $totalSize || ! $this->looksLikeZip($stagingPath)) {
            Storage::disk('local')->delete($stagingPath);
            Storage::disk('local')->deleteDirectory($directory);
            throw ValidationException::withMessages(['chunk' => 'Unggahan tidak lengkap atau file bukan Paket ZIP yang valid.']);
        }
        Storage::disk('local')->put($stagingDirectory.'/'.$uploadId.'.json', json_encode([
            ...$manifest,
            'staging_path' => $stagingPath,
        ], JSON_THROW_ON_ERROR));
        Storage::disk('local')->deleteDirectory($directory);

        return ['complete' => true, 'received' => $totalChunks, 'total' => $totalChunks, 'token' => $uploadId];
    }

    /** @return array{path:string,name:string} */
    public function consume(User $user, string $token): array
    {
        $this->validateToken($token);
        $manifestPath = 'temporary/question-package-staging/'.$user->id.'/'.$token.'.json';
        if (! Storage::disk('local')->exists($manifestPath)) {
            throw ValidationException::withMessages(['question_package_upload_token' => 'Sesi unggahan Paket ZIP tidak ditemukan atau sudah digunakan.']);
        }
        $manifest = json_decode((string) Storage::disk('local')->get($manifestPath), true);
        $path = $manifest['staging_path'] ?? null;
        if (($manifest['user_id'] ?? null) !== $user->id || ! is_string($path) || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['question_package_upload_token' => 'Sesi unggahan Paket ZIP tidak valid.']);
        }
        Storage::disk('local')->delete($manifestPath);

        return ['path' => $path, 'name' => (string) ($manifest['original_name'] ?? 'bank-soal.zip')];
    }

    public function cleanup(string $path): void
    {
        Storage::disk('local')->delete($path);
    }

    private function looksLikeZip(string $path): bool
    {
        $stream = Storage::disk('local')->readStream($path);
        $header = is_resource($stream) ? fread($stream, 4) : false;
        if (is_resource($stream)) {
            fclose($stream);
        }

        return is_string($header) && in_array($header, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    private function validateToken(string $token): void
    {
        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['chunk' => 'Identitas unggahan Paket ZIP tidak valid.']);
        }
    }
}
