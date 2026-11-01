<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AllocationStrategy;
use App\Services\Payments\FifoAllocationStrategy;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AllocationStrategy::class, FifoAllocationStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
