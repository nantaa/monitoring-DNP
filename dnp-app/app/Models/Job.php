<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model
{
    use HasUuids;

    protected $table = 'dnp_jobs';

    protected $fillable = [
        'kode', 'klien', 'lokasi', 'owner_marketing', 'pic_klien', 'pic_klien_phone',
        'pesawat', 'units', 'nilai', 'no_po', 'tgl_po', 'stage', 'stage_started_at',
        'disnaker_tujuan', 'inspektur_ids', 'inspektur', 'tgl_pelaksanaan', 'durasi_hari',
        'no_surat_tugas', 'tgl_surat_tugas', 'tgl_h5', 'h5_confirmed', 'h5_method',
        'h5_confirmed_at', 'h5_confirmed_by', 'field_completed_at',
        'peer_review_status', 'peer_review_submitted_at', 'peer_review_submitted_by',
        'peer_review_approved_at', 'peer_review_approved_by', 'laik_status',
        'evaluations', 'stage2_checklist', 'units_tracking', 'disnaker_followups',
        'invoice_no', 'invoice_date', 'top_days', 'payment_due_date', 'payment_status',
        'payment_paid_at', 'payment_amount_received', 'tanda_terima_kembali',
        'completed_at', 'notes', 'history', 'created_by',
    ];

    protected $casts = [
        'inspektur_ids'       => 'array',
        'evaluations'         => 'array',
        'stage2_checklist'    => 'array',
        'units_tracking'      => 'array',
        'disnaker_followups'  => 'array',
        'history'             => 'array',
        'h5_confirmed'        => 'boolean',
        'tanda_terima_kembali'=> 'boolean',
        'tgl_po'              => 'date',
        'tgl_pelaksanaan'     => 'date',
        'tgl_surat_tugas'     => 'date',
        'tgl_h5'              => 'date',
        'invoice_date'        => 'date',
        'payment_due_date'    => 'date',
        'field_completed_at'  => 'date',
        'completed_at'        => 'datetime',
        'h5_confirmed_at'     => 'datetime',
        'peer_review_submitted_at' => 'datetime',
        'peer_review_approved_at'  => 'datetime',
        'payment_paid_at'     => 'datetime',
        'stage_started_at'    => 'date',
        'nilai'               => 'integer',
        'units'               => 'integer',
        'durasi_hari'         => 'integer',
        'top_days'            => 'integer',
        'payment_amount_received' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(JobDocument::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'record_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Compute job SLA status: on_track | warning | overdue | completed */
    public function getStatusAttribute(): string
    {
        if ($this->stage === 7 && $this->completed_at) return 'completed';
        $slaMap = [1 => null, 2 => 1, 3 => 1, 4 => null, 5 => 3, 6 => 60, 7 => 1];
        $sla = $slaMap[$this->stage] ?? null;
        if (!$sla || !$this->stage_started_at) return 'on_track';
        if ($this->stage === 5) $sla = $sla * ($this->units ?: 1);
        $days = now()->diffInDays($this->stage_started_at, false) * -1;
        if ($days > $sla) return 'overdue';
        if ($days >= $sla - 1) return 'warning';
        return 'on_track';
    }
}
