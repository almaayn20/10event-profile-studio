<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The part of a picture that shows, chosen by hand: the point kept in the middle of the view, and a zoom. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_images', function (Blueprint $table) {
            $table->float('crop_x')->nullable();       // 0–1 of the picture's width
            $table->float('crop_y')->nullable();       // 0–1 of its height
            $table->float('crop_zoom')->nullable();    // 1 = just covers its place on the page
        });
    }

    public function down(): void
    {
        Schema::table('project_images', function (Blueprint $table) {
            $table->dropColumn(['crop_x', 'crop_y', 'crop_zoom']);
        });
    }
};
