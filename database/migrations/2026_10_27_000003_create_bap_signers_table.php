<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda tangan Berita Acara yang diisi publik lewat tautan bertoken.
     */
    public function up(): void
    {
        Schema::create('bap_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->enum('role', ['pejabat', 'penilai']);
            $table->string('token', 64)->unique();
            $table->string('nama')->nullable();
            $table->string('nip')->nullable();
            $table->string('jabatan')->nullable();
            $table->longText('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bap_signers');
    }
};