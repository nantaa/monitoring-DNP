<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dnp_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode')->unique();
            $table->string('klien');
            $table->string('lokasi');
            $table->string('owner_marketing');
            $table->string('pic_klien')->nullable();
            $table->string('pic_klien_phone')->nullable();
            $table->string('pesawat'); // LIFT, ESC, PAPA, FIRE, LISTRIK, BOILER, PV, PTP
            $table->integer('units')->default(1);
            $table->bigInteger('nilai')->default(0); // nilai kontrak dalam rupiah
            $table->string('no_po')->nullable();
            $table->date('tgl_po')->nullable();
            $table->integer('stage')->default(1);
            $table->date('stage_started_at')->nullable();
            $table->string('disnaker_tujuan')->nullable();

            // Stage 3 — Scheduling
            $table->jsonb('inspektur_ids')->default('[]');
            $table->string('inspektur')->nullable(); // denormalized names
            $table->date('tgl_pelaksanaan')->nullable();
            $table->integer('durasi_hari')->default(1);
            $table->string('no_surat_tugas')->nullable();
            $table->date('tgl_surat_tugas')->nullable();
            $table->date('tgl_h5')->nullable();
            $table->boolean('h5_confirmed')->default(false);
            $table->string('h5_method')->nullable(); // teman_k3 | langsung
            $table->timestamp('h5_confirmed_at')->nullable();
            $table->string('h5_confirmed_by')->nullable();

            // Stage 4 — Field
            $table->date('field_completed_at')->nullable();

            // Stage 5 — Evaluation
            $table->string('peer_review_status')->nullable(); // null | submitted | approved | rejected
            $table->timestamp('peer_review_submitted_at')->nullable();
            $table->string('peer_review_submitted_by')->nullable();
            $table->timestamp('peer_review_approved_at')->nullable();
            $table->string('peer_review_approved_by')->nullable();
            $table->string('laik_status')->nullable(); // laik | laik_bersyarat | tidak_laik
            $table->jsonb('evaluations')->default('[]');
            $table->jsonb('stage2_checklist')->default('{}');

            // Stage 6 — Suket
            $table->jsonb('units_tracking')->default('[]');
            $table->jsonb('disnaker_followups')->default('[]');

            // Stage 7 — Billing
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->integer('top_days')->default(30);
            $table->date('payment_due_date')->nullable();
            $table->string('payment_status')->nullable(); // sent | paid | overdue
            $table->timestamp('payment_paid_at')->nullable();
            $table->bigInteger('payment_amount_received')->default(0);
            $table->boolean('tanda_terima_kembali')->default(false);

            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('history')->default('[]');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('stage');
            $table->index('owner_marketing');
            $table->index(['payment_status', 'payment_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dnp_jobs');
    }
};
