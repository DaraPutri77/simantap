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
        Schema::table('items', function (Blueprint $table) {
            // Menambahkan kolom harga khusus Admin (bisa dikosongkan)
            $table->decimal('harga', 15, 2)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Menghapus kolom harga jika migrasi di-rollback
            $table->dropColumn('harga');
        });
    }
};