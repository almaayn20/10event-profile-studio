<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The description's type size chosen by hand (null = fitted automatically) and its alignment (null = the profile's own). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->float('desc_size')->nullable();
            $table->string('desc_align', 12)->nullable();     // justify · center
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['desc_size', 'desc_align']);
        });
    }
};
