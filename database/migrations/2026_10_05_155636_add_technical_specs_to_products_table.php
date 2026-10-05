<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technische Daten als geordnete Liste von {label, value}-Paaren.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('technical_specs')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('technical_specs');
        });
    }
};
