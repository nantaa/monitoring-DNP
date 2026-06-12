<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspektur_lists', function (Blueprint $table) {
            $table->string('id')->primary(); // I001, I002, ...
            $table->string('nama');
            $table->string('skp')->nullable();
            $table->date('skp_expired')->nullable();
            $table->jsonb('spesialisasi')->default('[]'); // ['Umum','Listrik']
            $table->string('phone')->nullable();
            $table->string('domisili')->nullable();
            $table->integer('senior_level')->default(1);
            $table->integer('joined_year')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('skp_expired');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspektur_lists');
    }
};
