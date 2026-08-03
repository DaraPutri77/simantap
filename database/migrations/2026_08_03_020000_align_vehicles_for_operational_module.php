<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $hasPublicId = Schema::hasColumn('vehicles', 'public_id');
        $hasStorageLocation = Schema::hasColumn(
            'vehicles',
            'storage_location',
        );
        $hasResponsiblePerson = Schema::hasColumn(
            'vehicles',
            'responsible_person',
        );

        Schema::table('vehicles', function (Blueprint $table) use (
            $hasPublicId,
            $hasStorageLocation,
            $hasResponsiblePerson,
        ): void {
            if (! $hasPublicId) {
                $table->uuid('public_id')
                    ->nullable()
                    ->after('id');
            }

            if (! $hasStorageLocation) {
                $table->string('storage_location')
                    ->nullable()
                    ->after('registration_expiry_date');
            }

            if (! $hasResponsiblePerson) {
                $table->string('responsible_person')
                    ->nullable()
                    ->after('storage_location');
            }
        });

        DB::table('vehicles')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(static function (object $vehicle): void {
                DB::table('vehicles')
                    ->where('id', $vehicle->id)
                    ->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
            });

        if (! $hasPublicId) {
            Schema::table(
                'vehicles',
                static function (Blueprint $table): void {
                    $table->unique('public_id');
                },
            );
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('vehicles', 'responsible_person')
                ? 'responsible_person'
                : null,
            Schema::hasColumn('vehicles', 'storage_location')
                ? 'storage_location'
                : null,
            Schema::hasColumn('vehicles', 'public_id')
                ? 'public_id'
                : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('vehicles', static function (
            Blueprint $table,
        ) use ($columns): void {
            if (in_array('public_id', $columns, true)) {
                $table->dropUnique('vehicles_public_id_unique');
            }

            $table->dropColumn($columns);
        });
    }
};
