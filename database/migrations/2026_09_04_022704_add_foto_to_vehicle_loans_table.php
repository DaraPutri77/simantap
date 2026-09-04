<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_loans', function (Blueprint $table) {
            // Menambahkan kolom foto setelah kolom reason (boleh kosong)
            $table->string('foto')->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_loans', function (Blueprint $table) {
            // Menghapus kolom foto jika migrasi di-rollback
            $table->dropColumn('foto');
        });
    }
};