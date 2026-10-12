<?php

namespace App\Providers;

use App\Services\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
    }

    public function boot(): void
    {
        //
    }
}
