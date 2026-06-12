<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('record_id');
            $table->string('operation'); // INSERT | UPDATE | DELETE
            $table->jsonb('old_data')->nullable();
            $table->jsonb('new_data')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'created_at']);
            $table->index('table_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
