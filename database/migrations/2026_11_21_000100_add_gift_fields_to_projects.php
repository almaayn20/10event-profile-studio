<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 10 Gift page: its colour (the card, the logo tab and the category tag) and the client's logo — the file, its
 * size, and where it sits on the page and where its tab ends when placed by hand. The event studio leaves them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('color', 7)->nullable();
            $table->json('logo')->nullable();          // {path, w, h, box: {x, y, w, h} | null, tab: float | null}
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['color', 'logo']);
        });
    }
};
