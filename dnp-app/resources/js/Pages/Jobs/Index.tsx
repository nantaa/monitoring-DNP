import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    LayoutDashboard, Columns, List, Plus, Search, ChevronRight, AlertCircle, FileText
} from 'lucide-react';
import AuthenticatedLayout from '@/Components/Layouts/AuthenticatedLayout';
import { STAGES, formatRp, formatDate, STATUS_COLORS } from '@/lib/constants';
import type { Job, PageProps } from '@/types';

interface JobsProps extends PageProps {
    jobs: { data: Job[]; current_page: number; last_page: number; links: any[] };
    filters: { stage?: string; search?: string };
}

export default function JobsIndex({ jobs, filters }: JobsProps) {
    const [view, setView] = useState<'list' | 'kanban'>(() => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('view') === 'kanban' ? 'kanban' : 'list';
    });
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/jobs', { search, view }, { preserveState: true });
    };

    const renderKanban = () => {
        return (
            <div style={{ display: 'flex', gap: 16, overflowX: 'auto', paddingBottom: 16, minHeight: 'calc(100vh - 200px)' }}>
                {STAGES.map(stage => {
                    const stageJobs = jobs.data.filter(j => j.stage === stage.id);
                    return (
                        <div key={stage.id} style={{ width: 320, flexShrink: 0, background: 'var(--color-surface)', borderRadius: 12, border: `1px solid var(--color-border)`, display: 'flex', flexDirection: 'column' }}>
                            <div style={{ padding: '16px', borderBottom: '1px solid var(--color-border)', borderTop: `4px solid var(--color-${stage.tone})`, borderRadius: '12px 12px 0 0' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <h3 style={{ margin: 0, fontSize: 14, fontWeight: 600 }}>{stage.short}</h3>
                                    <span style={{ background: 'var(--color-surface-2)', padding: '2px 8px', borderRadius: 100, fontSize: 12, fontWeight: 600 }}>{stageJobs.length}</span>
                                </div>
                                <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 4 }}>{stage.name}</div>
                            </div>
                            <div style={{ padding: 12, display: 'flex', flexDirection: 'column', gap: 12, overflowY: 'auto', flex: 1 }}>
                                {stageJobs.map(job => {
                                    const st = STATUS_COLORS[job.status as keyof typeof STATUS_COLORS] || STATUS_COLORS.on_track;
                                    return (
                                        <div key={job.id} onClick={() => alert('View Job ' + job.kode)} style={{ background: 'var(--color-surface-2)', padding: 16, borderRadius: 8, border: '1px solid var(--color-border)', cursor: 'pointer', transition: 'border 0.2s' }}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                                                <span style={{ fontWeight: 600, fontSize: 13, color: 'var(--color-accent)' }}>{job.kode}</span>
                                                <span style={{ padding: '2px 6px', borderRadius: 4, background: st.bg, color: st.text, fontSize: 10, fontWeight: 700 }}>{st.label}</span>
                                            </div>
                                            <div style={{ fontSize: 13, fontWeight: 500, marginBottom: 4 }}>{job.klien}</div>
                                            <div style={{ fontSize: 12, color: 'var(--color-text-muted)', display: 'flex', justifyContent: 'space-between' }}>
                                                <span>{job.pesawat} ({job.units} unit)</span>
                                                <span>{formatDate(job.stage_started_at)}</span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    };

    const renderList = () => {
        return (
            <div style={{ background: 'var(--color-surface)', borderRadius: 12, border: '1px solid var(--color-border)', overflow: 'hidden' }}>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: 13 }}>
                        <thead>
                            <tr style={{ background: 'var(--color-surface-2)', color: 'var(--color-text-muted)' }}>
                                <th style={{ padding: '12px 20px', fontWeight: 500 }}>Job ID</th>
                                <th style={{ padding: '12px 20px', fontWeight: 500 }}>Klien & Lokasi</th>
                                <th style={{ padding: '12px 20px', fontWeight: 500 }}>Pesawat</th>
                                <th style={{ padding: '12px 20px', fontWeight: 500 }}>Stage</th>
                                <th style={{ padding: '12px 20px', fontWeight: 500 }}>Status SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobs.data.map(job => {
                                const stageInfo = STAGES.find(s => s.id === job.stage);
                                const st = STATUS_COLORS[job.status as keyof typeof STATUS_COLORS] || STATUS_COLORS.on_track;
                                return (
                                    <tr key={job.id} onClick={() => alert('View Job ' + job.kode)} style={{ borderBottom: '1px solid var(--color-border)', cursor: 'pointer' }}>
                                        <td style={{ padding: '16px 20px', fontWeight: 600, color: 'var(--color-accent)' }}>{job.kode}</td>
                                        <td style={{ padding: '16px 20px' }}>
                                            <div style={{ fontWeight: 500 }}>{job.klien}</div>
                                            <div style={{ color: 'var(--color-text-muted)', fontSize: 12, marginTop: 2 }}>{job.lokasi}</div>
                                        </td>
                                        <td style={{ padding: '16px 20px' }}>{job.pesawat} <span style={{ color: 'var(--color-text-muted)' }}>({job.units} unit)</span></td>
                                        <td style={{ padding: '16px 20px' }}>
                                            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '4px 10px', background: 'var(--color-surface-2)', borderRadius: 100, fontSize: 12 }}>
                                                <div style={{ width: 8, height: 8, borderRadius: '50%', background: `var(--color-${stageInfo?.tone})` }} />
                                                {stageInfo?.name}
                                            </div>
                                        </td>
                                        <td style={{ padding: '16px 20px' }}>
                                            <span style={{ padding: '4px 8px', borderRadius: 4, background: st.bg, color: st.text, fontSize: 11, fontWeight: 600 }}>{st.label}</span>
                                        </td>
                                    </tr>
                                );
                            })}
                            {jobs.data.length === 0 && (
                                <tr><td colSpan={5} style={{ padding: 32, textAlign: 'center', color: 'var(--color-text-muted)' }}>Pencarian tidak menemukan hasil.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Daftar Pekerjaan" />
            
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 24, flexWrap: 'wrap', gap: 16 }}>
                <div>
                    <h1 style={{ margin: '0 0 8px', fontSize: 24, fontWeight: 700 }}>Pipeline Pekerjaan</h1>
                    <p style={{ margin: 0, color: 'var(--color-text-muted)' }}>Kelola proses sertifikasi riksa uji dari PO hingga Suket terbit.</p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <form onSubmit={handleSearch} style={{ position: 'relative' }}>
                        <Search size={16} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }} />
                        <input
                            type="text"
                            placeholder="Cari klien, kode..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            style={{ padding: '10px 16px 10px 36px', background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 8, color: 'var(--color-text)', fontSize: 14, outline: 'none' }}
                        />
                    </form>
                    <div style={{ display: 'flex', background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 8, padding: 4 }}>
                        <button onClick={() => setView('list')} style={{ padding: '6px 12px', background: view === 'list' ? 'var(--color-surface-2)' : 'transparent', border: 'none', borderRadius: 6, color: view === 'list' ? 'var(--color-text)' : 'var(--color-text-muted)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
                            <List size={16} /> List
                        </button>
                        <button onClick={() => setView('kanban')} style={{ padding: '6px 12px', background: view === 'kanban' ? 'var(--color-surface-2)' : 'transparent', border: 'none', borderRadius: 6, color: view === 'kanban' ? 'var(--color-text)' : 'var(--color-text-muted)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
                            <Columns size={16} /> Kanban
                        </button>
                    </div>
                </div>
            </div>

            {view === 'kanban' ? renderKanban() : renderList()}

        </AuthenticatedLayout>
    );
}
