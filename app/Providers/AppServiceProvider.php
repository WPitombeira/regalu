<?php

namespace App\Providers;

use App\Jobs\SendAssignment;
use App\Jobs\SendEventInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Livewire::addPersistentMiddleware([AuthenticateSession::class]);

        Queue::route(SendEventInvitation::class, 'mail');
        Queue::route(SendAssignment::class, 'mail');
    }
}
