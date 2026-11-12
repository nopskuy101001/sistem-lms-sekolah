<?php

namespace App\Services;

use App\Models\QuestionBank;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class QuestionBankPackageService
{
    public const WORKBOOK = 'soal.xlsx';

    public const MANIFEST = 'manifest.json';

    public function __construct(private readonly QuestionBankXlsxExporter $xlsx) {}

    public function write(string $path, QuestionBank $bank): void
    {
        $workbook = tempnam(sys_get_temp_dir(), 'bank-soal-xlsx-');
        if ($workbook === false) {
            throw new RuntimeException('Folder sementara tidak tersedia.');
        }

        $zip = new ZipArchive;
        $opened = false;

        try {
            $this->xlsx->write($workbook, $bank, true);
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Paket ZIP bank soal tidak dapat dibuat.');
            }
            $opened = true;
            if (! $zip->addFile($workbook, self::WORKBOOK)) {
                throw new RuntimeException('Excel soal gagal dimasukkan ke paket ZIP.');
            }

            $media = [];
            foreach ($bank->payload['groups'] ?? [] as $group) {
                $key = (string) ($group['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $image = $this->addMedia($zip, $key, 'image', $group['media_path'] ?? null);
                $video = $this->addMedia($zip, $key, 'video', $group['video_path'] ?? null);
                $media[$key] = array_filter([
                    'image' => $image,
                    'video' => $video,
                    'video_url' => $group['video_url'] ?? null,
                ]);
            }

            $manifest = [
                'format' => 'ruangkelas-question-package',
                'version' => 1,
                'workbook' => self::WORKBOOK,
                'bank' => [
                    'title' => $bank->title,
                    'question_count' => $bank->question_count,
                    'total_points' => $bank->total_points,
                ],
                'media' => $media,
                'created_at' => now()->toIso8601String(),
            ];
            if (! $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Manifest paket bank soal gagal dibuat.');
            }
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($workbook);
        }
    }

    private function addMedia(ZipArchive $zip, string $groupKey, string $kind, ?string $storagePath): ?array
    {
        if (! $storagePath) {
            return null;
        }
        if (! Storage::disk('local')->exists($storagePath)) {
            throw new RuntimeException('Media stimulus tidak ditemukan: '.basename($storagePath));
        }

        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $packagePath = 'media/'.$groupKey.'/'.$kind.($extension !== '' ? '.'.$extension : '');
        $realPath = Storage::disk('local')->path($storagePath);
        if (! $zip->addFile($realPath, $packagePath)) {
            throw new RuntimeException('Media stimulus gagal dimasukkan ke paket: '.basename($storagePath));
        }

        return [
            'path' => $packagePath,
            'name' => basename($storagePath),
            'size' => filesize($realPath) ?: 0,
            'sha256' => hash_file('sha256', $realPath),
        ];
    }
}
