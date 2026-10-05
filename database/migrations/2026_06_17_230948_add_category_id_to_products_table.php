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
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('manufacturer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Kategorien sind inzwischen Config (kein Model mehr) — FK per Spaltenname.
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
