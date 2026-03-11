<?php

namespace App\Providers;

use App\Livewire\Hooks\EnsureLivewireActionPermission;
use Illuminate\Pagination\Paginator;
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
        // Force Bootstrap paginator markup globally (AdminLTE uses Bootstrap).
        Paginator::useBootstrapFive();

        Livewire::componentHook(EnsureLivewireActionPermission::class);
    }
}
