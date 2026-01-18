<?php

namespace App\Providers;

use App\Contracts\FileStorageServiceInterface;
use App\Services\Storage\LocalFileStorageService;
use App\Services\Storage\S3FileStorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FileStorageServiceInterface::class, function () {
            return app()->environment('local')
                ? new LocalFileStorageService('public')
                : new S3FileStorageService('s3');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
