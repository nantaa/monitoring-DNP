<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alat_inventories', function (Blueprint $table) {
            $table->string('id')->primary(); // A001, A002, ...
            $table->string('nama');
            $table->string('merk')->nullable();
            $table->string('serial')->nullable();
            $table->jsonb('kategori')->default('[]'); // ['PUBT','PV']
            $table->date('kalibrasi_terakhir')->nullable();
            $table->date('kalibrasi_expired')->nullable();
            $table->string('lab')->nullable(); // KAN lab name
            $table->string('status')->default('tersedia'); // tersedia | sedang dipakai | rusak
            $table->timestamps();

            $table->index('kalibrasi_expired');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alat_inventories');
    }
};
