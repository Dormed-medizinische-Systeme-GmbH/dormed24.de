<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fachrichtungen als Liste von Slugs aus config/specialties.php.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('specialties')->nullable()->after('category_slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('specialties');
        });
    }
};
