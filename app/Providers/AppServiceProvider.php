<?php

namespace App\Providers;

use App\Listeners\VerifyDatabaseConnection;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable Laravel's default {"data": [...]} wrapping on resource collections
        // so the API v1 controllers can build the envelope shape manually
        // ({"data": [...], "meta": {...}} for lists, {"data": {...}} for single items).
        JsonResource::withoutWrapping();

        Event::listen(DiagnosingHealth::class, VerifyDatabaseConnection::class);
    }
}
