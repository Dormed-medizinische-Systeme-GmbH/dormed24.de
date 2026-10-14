<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot der gewählten Versandart (config/shipping.php). Key und Name
 * werden kopiert, weil sich beide in der Config später ändern dürfen.
 * Nullable, weil Bestellungen vor dieser Migration keine Versandart haben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_method_key', 64)->nullable()->after('shipping_amount');
            $table->string('shipping_method_name')->nullable()->after('shipping_method_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['shipping_method_key', 'shipping_method_name']);
        });
    }
};
