<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject', 150);
            $table->string('preheader', 200)->nullable();
            $table->string('status')->default('draft');
            // GrapesJS-Projektdaten: die editierbare Quelle. MJML und HTML
            // werden daraus im Browser erzeugt.
            $table->longText('design_json')->nullable();
            $table->longText('mjml')->nullable();
            $table->longText('html')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};
