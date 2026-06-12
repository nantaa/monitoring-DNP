<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id')->nullable();
            $table->foreign('job_id')->references('id')->on('dnp_jobs')->nullOnDelete();
            $table->string('channel'); // whatsapp | email
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('template');
            $table->jsonb('payload')->default('{}');
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
