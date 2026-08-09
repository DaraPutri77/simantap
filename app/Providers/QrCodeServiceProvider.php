<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class QrCodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/qr-codes.php'));
    }
}
