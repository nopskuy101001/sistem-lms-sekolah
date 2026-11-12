<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChunkedVideoUploadService
{
    public const MAX_BYTES = 104_857_600;

    public const CHUNK_BYTES = 1_048_576;

    /** @return array{complete: bool, received: int, total: int, token: ?string} */
    public function accept(
        User $user,
        UploadedFile $chunk,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $originalName,
        int $totalSize,
    ): array {
        $this->validateIdentity($uploadId);
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks || $totalChunks > 100) {
            throw ValidationException::withMessages(['chunk' => 'Urutan potongan video tidak valid.']);
        }
        if ($totalSize < 1 || $totalSize > self::MAX_BYTES || ! preg_match('/\.mp4$/i', $originalName)) {
            throw ValidationException::withMessages(['chunk' => 'Video harus berupa MP4 dengan ukuran maksimal 100 MB.']);
        }

        $directory = $this->chunkDirectory($user, $uploadId);
        $manifestPath = $directory.'/manifest.json';
        $manifest = [
            'user_id' => $user->id,
            'upload_id' => $uploadId,
            'original_name' => basename($originalName),
            'total_chunks' => $totalChunks,
            'total_size' => $totalSize,
            'created_at' => now()->toIso8601String(),
        ];

        if (Storage::disk('local')->exists($manifestPath)) {
            $existing = json_decode((string) Storage::disk('local')->get($manifestPath), true);
            foreach (['user_id', 'upload_id', 'original_name', 'total_chunks', 'total_size'] as $field) {
                if (($existing[$field] ?? null) != $manifest[$field]) {
                    throw ValidationException::withMessages(['chunk' => 'Data sesi unggahan video berubah. Pilih ulang file dan coba lagi.']);
                }
            }
        } else {
            Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
        }

        $stored = $chunk->storeAs($directory, 'chunk-'.$chunkIndex.'.part', 'local');
        if (! $stored) {
            throw ValidationException::withMessages(['chunk' => 'Potongan video gagal disimpan.']);
        }

        $received = collect(Storage::disk('local')->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.part'))
            ->count();
        if ($received < $totalChunks) {
            return ['complete' => false, 'received' => $received, 'total' => $totalChunks, 'token' => null];
        }

        $stagingDirectory = 'temporary/video-staging/'.$user->id;
        $stagingPath = $stagingDirectory.'/'.$uploadId.'.mp4';
        Storage::disk('local')->makeDirectory($stagingDirectory);
        $output = fopen(Storage::disk('local')->path($stagingPath), 'wb');
        if (! $output) {
            throw new RuntimeException('File staging video tidak dapat dibuat.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $partPath = $directory.'/chunk-'.$index.'.part';
                if (! Storage::disk('local')->exists($partPath)) {
                    throw ValidationException::withMessages(['chunk' => 'Ada potongan video yang belum diterima.']);
                }
                $input = Storage::disk('local')->readStream($partPath);
                if (! is_resource($input)) {
                    throw new RuntimeException('Potongan video tidak dapat dibaca.');
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        if (Storage::disk('local')->size($stagingPath) !== $totalSize || ! $this->looksLikeMp4($stagingPath)) {
            Storage::disk('local')->delete($stagingPath);
            Storage::disk('local')->deleteDirectory($directory);
            throw ValidationException::withMessages(['chunk' => 'File tidak dikenali sebagai video MP4 yang valid atau unggahan tidak lengkap.']);
        }

        $stagingManifest = $stagingDirectory.'/'.$uploadId.'.json';
        Storage::disk('local')->put($stagingManifest, json_encode([
            ...$manifest,
            'staging_path' => $stagingPath,
            'completed_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        Storage::disk('local')->deleteDirectory($directory);

        return ['complete' => true, 'received' => $totalChunks, 'total' => $totalChunks, 'token' => $uploadId];
    }

    /** @return array{path: string, name: string, size: int} */
    public function consume(User $user, string $token, string $destination, string $disk): array
    {
        $this->validateIdentity($token);
        $manifestPath = 'temporary/video-staging/'.$user->id.'/'.$token.'.json';
        abort_unless(Storage::disk('local')->exists($manifestPath), 422, 'Sesi unggahan video tidak ditemukan atau sudah digunakan.');

        $manifest = json_decode((string) Storage::disk('local')->get($manifestPath), true);
        $stagingPath = $manifest['staging_path'] ?? null;
        if (($manifest['user_id'] ?? null) !== $user->id
            || ! is_string($stagingPath)
            || ! Storage::disk('local')->exists($stagingPath)) {
            throw ValidationException::withMessages(['video_upload_token' => 'Sesi unggahan video tidak valid. Unggah ulang file MP4.']);
        }

        $target = trim($destination, '/').'/'.Str::uuid().'.mp4';
        $stream = Storage::disk('local')->readStream($stagingPath);
        $stored = is_resource($stream) && Storage::disk($disk)->put($target, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $stored) {
            throw ValidationException::withMessages(['video_upload_token' => 'Video selesai diunggah tetapi gagal dipindahkan ke penyimpanan.']);
        }

        Storage::disk('local')->delete([$stagingPath, $manifestPath]);

        return [
            'path' => $target,
            'name' => (string) ($manifest['original_name'] ?? 'video.mp4'),
            'size' => (int) ($manifest['total_size'] ?? 0),
        ];
    }

    private function looksLikeMp4(string $path): bool
    {
        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }
        $header = fread($stream, 4096);
        fclose($stream);

        return is_string($header) && strpos($header, 'ftyp') !== false;
    }

    private function validateIdentity(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages(['chunk' => 'Identitas unggahan video tidak valid.']);
        }
    }

    private function chunkDirectory(User $user, string $uploadId): string
    {
        return 'temporary/video-chunks/'.$user->id.'/'.$uploadId;
    }
}
