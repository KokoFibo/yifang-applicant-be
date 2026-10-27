<?php

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instance per request: perusahaan yang cocok dengan domain
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
    }
}
