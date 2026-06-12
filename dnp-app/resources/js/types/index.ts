export interface User {
    id: number;
    name: string;
    email: string;
    role: 'manager' | 'admin' | 'marketing' | 'inspektur' | 'finance';
}

export interface Stage {
    id: number;
    name: string;
    short: string;
    role: string;
    sla: number | null;
    tone: string;
}

export interface Job {
    id: string;
    kode: string;
    klien: string;
    lokasi: string;
    owner_marketing: string;
    pic_klien: string | null;
    pic_klien_phone: string | null;
    pesawat: string;
    units: number;
    nilai: number;
    no_po: string | null;
    tgl_po: string | null;
    stage: number;
    stage_started_at: string | null;
    disnaker_tujuan: string | null;
    inspektur_ids: string[];
    inspektur: string | null;
    tgl_pelaksanaan: string | null;
    durasi_hari: number;
    no_surat_tugas: string | null;
    tgl_surat_tugas: string | null;
    tgl_h5: string | null;
    h5_confirmed: boolean;
    h5_method: string | null;
    h5_confirmed_at: string | null;
    h5_confirmed_by: string | null;
    field_completed_at: string | null;
    peer_review_status: 'submitted' | 'approved' | 'rejected' | null;
    peer_review_submitted_at: string | null;
    peer_review_approved_at: string | null;
    peer_review_approved_by: string | null;
    laik_status: 'laik' | 'laik_bersyarat' | 'tidak_laik' | null;
    evaluations: Evaluation[];
    stage2_checklist: Record<string, boolean>;
    units_tracking: UnitTracking[];
    disnaker_followups: DisnakerFollowup[];
    invoice_no: string | null;
    invoice_date: string | null;
    top_days: number;
    payment_due_date: string | null;
    payment_status: 'sent' | 'paid' | 'overdue' | null;
    payment_paid_at: string | null;
    payment_amount_received: number;
    tanda_terima_kembali: boolean;
    completed_at: string | null;
    notes: string | null;
    history: HistoryEntry[];
    status: 'on_track' | 'warning' | 'overdue' | 'completed';
    documents?: JobDocument[];
    created_at: string;
    updated_at: string;
}

export interface Evaluation {
    unit_no: number;
    unit_label: string;
    status: 'laik' | 'laik_bersyarat' | 'tidak_laik';
    findings: string;
    recommendation: string;
}

export interface UnitTracking {
    unit_no: number;
    unit_label: string;
    laik_status: string;
    status: 'draft' | 'submitted' | 'issued' | 'rejected';
    tgl_submit: string | null;
    no_registrasi: string | null;
    no_suket: string | null;
    tgl_suket: string | null;
    suket_expired_at: string | null;
    suket_validity_months: number | null;
    notes: string;
}

export interface DisnakerFollowup {
    ts: string;
    by: string;
    status: string;
    notes: string;
}

export interface HistoryEntry {
    stage: number;
    ts: string;
    by: string;
    action: string;
}

export interface JobDocument {
    id: number;
    job_id: string;
    stage: number;
    type: string;
    original_name: string;
    stored_path: string;
    mime_type: string | null;
    file_size: number;
    uploaded_by: string | null;
    created_at: string;
}

export interface InspekturList {
    id: string;
    nama: string;
    skp: string | null;
    skp_expired: string | null;
    spesialisasi: string[];
    phone: string | null;
    domisili: string | null;
    senior_level: number;
    joined_year: number | null;
    active: boolean;
}

export interface PageProps {
    auth: { user: User };
    flash: { success?: string; error?: string };
}
