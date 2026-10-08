<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            // Schlüssel aus Article::CATEGORIES.
            $table->string('category');
            $table->string('teaser', 300)->nullable();
            // Markdown; wird beim Anzeigen bereinigt in HTML übersetzt.
            $table->longText('content');
            // Leer = Entwurf, Zukunft = geplant, erreicht = sichtbar.
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
