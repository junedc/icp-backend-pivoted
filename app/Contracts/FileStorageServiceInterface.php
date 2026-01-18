<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface FileStorageServiceInterface
{
    /**
     * Store an uploaded image and return a public URL (or accessible URL).
     */
    public function storeImage(UploadedFile $file, string $directory = 'images', ?string $filename = null): string;

    /**
     * Delete a stored file by its path/key.
     */
    public function delete(string $path): void;

    /**
     * Get a URL to a stored file by its path/key.
     */
    public function url(string $path): string;
}
