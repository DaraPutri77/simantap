<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\MaintenanceRecord;
use App\Models\OperationalAsset;
use App\Models\StockAdjustment;
use App\Models\Unit;
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
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

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
            'inventory_request' => InventoryRequest::class,
            'inventory_receipt' => InventoryReceipt::class,
            'item' => Item::class,
            'item_category' => ItemCategory::class,
            'stock_adjustment' => StockAdjustment::class,
            'unit' => Unit::class,
            'user' => User::class,
            'vehicle' => Vehicle::class,
            'vehicle_loan' => VehicleLoan::class,
            'vehicle_condition_check' => VehicleConditionCheck::class,
            'maintenance_record' => MaintenanceRecord::class,
            'operational_asset' => OperationalAsset::class,
        ]);
    }
}
