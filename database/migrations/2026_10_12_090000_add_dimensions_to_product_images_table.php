<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pixelmaße des gespeicherten WebP/AVIF, damit das Frontend width/height
     * setzen kann (kein Layout-Sprung beim Laden). Bestehende Bilder bekommen
     * die Werte durch `php artisan product-images:convert`.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedSmallInteger('width')->nullable()->after('path');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
