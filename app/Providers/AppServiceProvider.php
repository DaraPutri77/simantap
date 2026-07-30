<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleLoan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(
            ! app()->isProduction(),
        );

        Model::preventSilentlyDiscardingAttributes(
            ! app()->isProduction(),
        );

        Password::defaults(
            fn (): Password => Password::min(
                (int) config(
                    'simantap.security.password_min_length',
                    12,
                ),
            )
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols(),
        );

        Gate::before(
            fn (User $user): ?bool => $user->hasRole(
                RoleName::Administrator->value,
            )
                ? true
                : null,
        );

        Relation::enforceMorphMap([
            'inventory_receipt' => InventoryReceipt::class,
            'inventory_request' => InventoryRequest::class,
            'item' => Item::class,
            'user' => User::class,
            'vehicle' => Vehicle::class,
            'vehicle_loan' => VehicleLoan::class,
            'vehicle_condition_check' => VehicleConditionCheck::class,
            'maintenance_record' => MaintenanceRecord::class,
        ]);
    }
}
