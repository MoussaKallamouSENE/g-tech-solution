<?php

namespace App\Providers;

use App\Notifications\Contracts\WhatsAppProvider;
use App\Notifications\Providers\WhatsAppBusinessProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppProvider::class, WhatsAppBusinessProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
