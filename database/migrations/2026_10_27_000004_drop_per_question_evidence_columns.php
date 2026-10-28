<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti dukung kini melekat pada aspek (f01_aspect_links) dan penilaian F-02
     * melekat pada indikator (f02_indicator_scores), sehingga kolom per butir
     * pertanyaan beserta tabel f02_scores tidak dipakai lagi.
     */
    public function up(): void
    {
        Schema::dropIfExists('f02_scores');

        Schema::table('f01_answers', function (Blueprint $table) {
            $table->dropColumn('drive_link');
        });

        Schema::table('f01_questions', function (Blueprint $table) {
            $table->dropColumn('requires_drive_link');
        });
    }

    public function down(): void
    {
        Schema::table('f01_answers', function (Blueprint $table) {
            $table->text('drive_link')->nullable();
        });

        Schema::table('f01_questions', function (Blueprint $table) {
            $table->boolean('requires_drive_link')->default(true);
        });

        Schema::create('f02_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('f01_question_id')->constrained('f01_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};