<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project pages built from a form (pictures, client, title, description, link, statistics), the company profile PDF
 * they are placed into — kept as a history of versions — and the studio's own settings (the team password).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('profile_versions', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('name');                                   // the file name a download gets
            $table->unsignedSmallInteger('pages');
            $table->unsignedBigInteger('bytes');
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('source', 16);                             // upload · insert · update · remove · restore
            $table->string('note')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();     // the project page the change was about
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('client', 160)->default('');
            $table->string('title', 160)->default('');
            $table->text('description')->nullable();
            $table->string('details_url', 600)->nullable();
            $table->string('details_label', 60)->nullable();
            $table->json('stats')->nullable();
            $table->string('status', 16)->default('draft');           // draft · approved
            $table->unsignedInteger('layout_seed')->default(1);
            $table->json('layout')->nullable();
            $table->string('page_path')->nullable();                  // the last printed page (PDF) and its picture
            $table->string('thumb_path')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->foreignId('profile_version_id')->nullable()->constrained('profile_versions')->nullOnDelete();
            $table->unsignedSmallInteger('profile_page')->nullable(); // where the page sits in the current profile
            $table->boolean('profile_stale')->default(false);         // edited since it was put in the profile
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('project_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('path');
            $table->string('preview_path')->nullable();               // a light copy for the editor
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('bytes');
            $table->unsignedTinyInteger('priority')->nullable();      // 1 · 2 · 3 — the large ones; null = an ordinary one
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_images');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('profile_versions');
        Schema::dropIfExists('settings');
    }
};
