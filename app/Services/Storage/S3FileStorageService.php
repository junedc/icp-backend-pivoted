<?php

namespace App\Services\Storage;

use App\Contracts\FileStorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3FileStorageService implements FileStorageServiceInterface
{
    public function __construct(
        private readonly string $disk = 's3'
    )
    {
    }

    public function storeImage(UploadedFile $file, string $directory = 'images', ?string $filename = null): string
    {
        $name = $filename
            ? $this->sanitizeFilename($filename)
            : (Str::uuid()->toString() . '.' . $file->getClientOriginalExtension());

        $path = $file->storePubliclyAs($directory, $name, $this->disk);

        return Storage::disk($this->disk)->url($path);
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function url(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?? $filename;
        $filename = preg_replace('/-+/', '-', $filename) ?? $filename;
        return $filename;
    }
}
