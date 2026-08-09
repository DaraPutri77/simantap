<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path('routes/audit-logs.php'),
        );
    }
}
