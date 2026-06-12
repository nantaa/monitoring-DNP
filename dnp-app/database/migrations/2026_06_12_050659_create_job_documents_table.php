<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id');
            $table->foreign('job_id')->references('id')->on('dnp_jobs')->cascadeOnDelete();
            $table->integer('stage');
            $table->string('type'); // PO/SPK, Surat Permohonan, LHPP, etc.
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->default(0);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('job_id');
            $table->index(['job_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_documents');
    }
};
