<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('asset_code', 80)->unique();
            $table->string('bmn_code', 50)->nullable();
            $table->string('nup', 30)->nullable();
            $table->string('register_code', 100)->nullable()->unique();
            $table->string('type', 30)->index();
            $table->string('brand');
            $table->string('model')->nullable();
            $table->string('serial_number', 120)->nullable()->unique();
            $table->unsignedSmallInteger('acquisition_year')->nullable();
            $table->string('location')->nullable();
            $table->string('responsible_person')->nullable();
            $table->string('status', 40)
                ->default('available')
                ->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['bmn_code', 'nup'],
                'operational_assets_bmn_code_nup_unique',
            );
        });

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')
                ->nullable()
                ->change();
            $table->string('vehicle_snapshot')
                ->nullable()
                ->change();
        });

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->foreignId('operational_asset_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('operational_assets')
                ->restrictOnDelete();
            $table->string('operational_asset_snapshot')
                ->nullable()
                ->after('vehicle_snapshot');
            $table->string('operational_asset_status_before', 40)
                ->nullable()
                ->after('vehicle_status_before');
        });
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('maintenance_records', 'operational_asset_id')
            && DB::table('maintenance_records')
                ->whereNotNull('operational_asset_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Rollback ditolak karena terdapat histori pemeliharaan aset perangkat. Data harus dipertahankan.',
            );
        }

        Schema::table('maintenance_records', function (Blueprint $table): void {
            if (Schema::hasColumn('maintenance_records', 'operational_asset_id')) {
                $table->dropForeign(['operational_asset_id']);
                $table->dropColumn([
                    'operational_asset_id',
                    'operational_asset_snapshot',
                    'operational_asset_status_before',
                ]);
            }
        });

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')
                ->nullable(false)
                ->change();
            $table->string('vehicle_snapshot')
                ->nullable(false)
                ->change();
        });

        Schema::dropIfExists('operational_assets');
    }
};
