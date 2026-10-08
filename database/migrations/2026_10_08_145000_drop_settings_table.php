<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontaktdaten und Bankverbindung stehen jetzt in config/shop.php
 * (.docs/adr/0004-keine-konfiguration-im-admin.md); die Tabelle wird nicht
 * mehr gelesen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
};
