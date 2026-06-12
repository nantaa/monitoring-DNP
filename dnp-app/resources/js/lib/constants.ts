export const STAGES = [
    { id: 1, name: 'PO/SPK',                      short: 'Marketing',       role: 'marketing', sla: null, tone: 'slate' },
    { id: 2, name: 'Verifikasi Dokumen',           short: 'Adm. Dokumen',    role: 'admin',     sla: 1,    tone: 'amber' },
    { id: 3, name: 'Penjadwalan',                  short: 'Adm. Riksa Uji', role: 'admin',     sla: 1,    tone: 'amber' },
    { id: 4, name: 'Pelaksanaan RU',               short: 'Tim Ahli',        role: 'inspektur', sla: null, tone: 'teal'  },
    { id: 5, name: 'Penyusunan LHPP & Review',    short: 'Tim Ahli + Kadiv',role: 'inspektur', sla: 3,    tone: 'teal'  },
    { id: 6, name: 'Pengurusan Suket',             short: 'Kadiv RU',        role: 'manager',   sla: 60,   tone: 'gold'  },
    { id: 7, name: 'Penagihan & Selesai',          short: 'Adm. Keuangan',   role: 'finance',   sla: 1,    tone: 'green' },
] as const;

export const ROLES: Record<string, { name: string; label: string; tagline: string; color: string }> = {
    marketing: { name: 'Marketing',           label: 'MKT', tagline: 'Penawaran & Negosiasi',       color: '#818cf8' },
    admin:     { name: 'Admin Dokumen & RU',  label: 'ADM', tagline: 'Verifikasi & Penjadwalan',     color: '#fb923c' },
    inspektur: { name: 'Tim Ahli / Inspektur',label: 'INS', tagline: 'Pelaksanaan & LHPP',           color: '#34d399' },
    manager:   { name: 'Kadiv RU / Manager',  label: 'MGR', tagline: 'Review, Suket & Approval',     color: '#facc15' },
    finance:   { name: 'Admin Keuangan',      label: 'FIN', tagline: 'Penagihan & Invoice',           color: '#4ade80' },
};

export const STAGE_PERMISSIONS: Record<number, string[]> = {
    1: ['marketing', 'manager'],
    2: ['admin', 'manager'],
    3: ['admin', 'manager'],
    4: ['inspektur', 'manager'],
    5: ['inspektur', 'manager'],
    6: ['manager', 'admin'],
    7: ['finance', 'manager', 'admin'],
};

export const PESAWAT_TYPES = [
    { code: 'LIFT',    name: 'Lift / Dumbwaiter',        form: 'Form 36/38/39', validity: 12 },
    { code: 'ESC',     name: 'Eskalator / Travelator',   form: 'Form 52',       validity: 12 },
    { code: 'PAPA',    name: 'PAPA (Crane/Forklift/dll)',form: 'Form A 52',     validity: 12 },
    { code: 'FIRE',    name: 'Proteksi Kebakaran',        form: 'Form 65 K',     validity: 12 },
    { code: 'LISTRIK', name: 'Instalasi Listrik & PP',    form: 'Form 55 L',     validity: 12 },
    { code: 'BOILER',  name: 'Pesawat Uap (Boiler)',      form: 'Form 6',        validity: 12 },
    { code: 'PV',      name: 'Bejana Tekan',              form: 'Form 45 A.1',   validity: 24 },
    { code: 'PTP',     name: 'PTP (Compressor/Genset)',   form: 'Form 54 A',     validity: 24 },
];

export const DISNAKER_LIST = [
    'Disnaker DKI Jakarta', 'Disnaker Jabar', 'Disnaker Banten', 'Disnaker Jateng',
    'Disnaker Jatim', 'Disnaker Sumut', 'Disnaker Sumsel', 'Disnaker Lampung',
    'Disnaker Kaltim', 'Disnaker Sulsel', 'Disnaker Bali',
];

export const STAGE_COLORS: Record<number, string> = {
    1: '#818cf8', 2: '#fb923c', 3: '#fbbf24',
    4: '#34d399', 5: '#2dd4bf', 6: '#facc15', 7: '#4ade80',
};

export const STATUS_COLORS = {
    on_track:  { bg: 'rgba(52,211,153,0.15)',  text: '#34d399', label: 'On Track' },
    warning:   { bg: 'rgba(251,191,36,0.15)',  text: '#fbbf24', label: 'Mendekati SLA' },
    overdue:   { bg: 'rgba(248,113,113,0.15)', text: '#f87171', label: 'Overdue' },
    completed: { bg: 'rgba(74,222,128,0.15)',  text: '#4ade80', label: 'Selesai' },
};

export const LAIK_COLORS = {
    laik:           { bg: 'rgba(52,211,153,0.2)',  text: '#34d399', label: 'LAIK' },
    laik_bersyarat: { bg: 'rgba(251,191,36,0.2)',  text: '#fbbf24', label: 'LAIK BERSYARAT' },
    tidak_laik:     { bg: 'rgba(248,113,113,0.2)', text: '#f87171', label: 'TIDAK LAIK' },
};

export function formatRp(n: number | null | undefined): string {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

export function formatDate(d: string | null | undefined): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function daysBetween(a: string, b: string): number {
    return Math.floor((new Date(b).getTime() - new Date(a).getTime()) / 86400000);
}
