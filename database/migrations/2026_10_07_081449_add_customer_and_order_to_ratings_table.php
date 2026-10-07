<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bewertungen werden an den Kauf gebunden: Kunde und Bestellung, aus der
 * die Berechtigung stammt. Beide nullable, weil bestehende Bewertungen aus
 * der Zeit davor anonym sind und Kunden bzw. Bestellungen gelöscht werden
 * können, ohne dass die Bewertung verschwindet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();

            // Eine Bewertung pro Kunde und Produkt. NULL (Bestand) ist davon
            // nicht betroffen, weil NULL-Werte in Unique-Indizes nicht kollidieren.
            $table->unique(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'product_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
