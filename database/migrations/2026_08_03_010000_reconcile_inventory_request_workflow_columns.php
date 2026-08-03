<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_requests')) {
            return;
        }

        $this->addApproverReference();
        $this->addDeliveryReference();
    }

    public function down(): void
    {
        // Compatibility migration: keep workflow audit data intact on rollback.
    }

    private function addApproverReference(): void
    {
        if (Schema::hasColumn('inventory_requests', 'approved_by')) {
            return;
        }

        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    private function addDeliveryReference(): void
    {
        if (Schema::hasColumn('inventory_requests', 'delivered_by')) {
            return;
        }

        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
