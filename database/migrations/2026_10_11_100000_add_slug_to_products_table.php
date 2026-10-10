<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Slug aus dem Produktnamen für die Shop-URL (/{kategorie}/{slug}). Wird
     * beim Anlegen einmal erzeugt und danach nie mehr geändert; bestehende
     * Produkte bekommen ihn hier. Bei gleichem Namen hängt die ID an.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        $taken = [];

        foreach (DB::table('products')->orderBy('id')->get(['id', 'name']) as $row) {
            $slug = Str::slug((string) $row->name, '-', 'de') ?: 'produkt';

            if (isset($taken[$slug])) {
                $slug .= '-'.$row->id;
            }

            $taken[$slug] = true;

            DB::table('products')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
