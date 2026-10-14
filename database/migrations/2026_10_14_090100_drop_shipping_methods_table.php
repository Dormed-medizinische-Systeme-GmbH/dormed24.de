<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versandarten stehen jetzt in config/shipping.php
 * (.docs/adr/0006-admin-nur-inhaltspflege.md); die Tabelle wird nicht mehr
 * gelesen. Bestellungen verweisen nicht per FK auf sie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipping_methods');
    }

    public function down(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
