import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    Activity, Clock, CheckCircle2, AlertCircle, FileText, Banknote, ShieldCheck
} from 'lucide-react';
import AuthenticatedLayout from '@/Components/Layouts/AuthenticatedLayout';
import { STAGES, formatRp, formatDate, STATUS_COLORS, ROLES } from '@/lib/constants';
import type { Job, PageProps } from '@/types';

interface DashboardProps extends PageProps {
    stats: {
        total_active: number;
        overdue: number;
        stage_counts: Record<string, number>;
        total_nilai: number;
        unpaid_invoice?: number;
        overdue_payment?: number;
        suket_expiring_soon?: number;
    };
    recentJobs: Job[];
    role: string;
}

export default function Dashboard({ stats, recentJobs, role }: DashboardProps) {
    const isMkt = role === 'marketing';
    const isFin = role === 'finance';
    const isMgr = role === 'manager';
    const isInsp = role === 'inspektur';
    const isAdmin = role === 'admin';

    const renderSummaryCards = () => (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 20, marginBottom: 32 }}>
            <div style={{ background: 'var(--color-surface)', padding: 20, borderRadius: 12, border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
                <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(79,142,247,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-accent)' }}>
                    <Activity size={24} />
                </div>
                <div>
                    <div style={{ color: 'var(--color-text-muted)', fontSize: 13, marginBottom: 4 }}>Total Job Aktif</div>
                    <div style={{ color: 'var(--color-text)', fontSize: 24, fontWeight: 700 }}>{stats.total_active}</div>
                </div>
            </div>

            <div style={{ background: 'var(--color-surface)', padding: 20, borderRadius: 12, border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
                <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(248,113,113,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-danger)' }}>
                    <Clock size={24} />
                </div>
                <div>
                    <div style={{ color: 'var(--color-text-muted)', fontSize: 13, marginBottom: 4 }}>Job Overdue SLA</div>
                    <div style={{ color: 'var(--color-text)', fontSize: 24, fontWeight: 700 }}>{stats.overdue}</div>
                </div>
            </div>

            {(isMkt || isMgr || isFin) && (
                <div style={{ background: 'var(--color-surface)', padding: 20, borderRadius: 12, border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(52,211,153,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-success)' }}>
                        <Banknote size={24} />
                    </div>
                    <div>
                        <div style={{ color: 'var(--color-text-muted)', fontSize: 13, marginBottom: 4 }}>Nilai Kontrak Aktif</div>
                        <div style={{ color: 'var(--color-text)', fontSize: 20, fontWeight: 700 }}>{formatRp(stats.total_nilai)}</div>
                    </div>
                </div>
            )}

            {(isFin || isMgr) && (
                <div style={{ background: 'var(--color-surface)', padding: 20, borderRadius: 12, border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(251,191,36,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-warning)' }}>
                        <AlertCircle size={24} />
                    </div>
                    <div>
                        <div style={{ color: 'var(--color-text-muted)', fontSize: 13, marginBottom: 4 }}>Unpaid Invoice</div>
                        <div style={{ color: 'var(--color-text)', fontSize: 20, fontWeight: 700 }}>{formatRp(stats.unpaid_invoice)}</div>
                    </div>
                </div>
            )}

            {(isMgr || isAdmin) && (
                <div style={{ background: 'var(--color-surface)', padding: 20, borderRadius: 12, border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ width: 48, height: 48, borderRadius: 12, background: 'rgba(250,204,21,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-warning)' }}>
                        <ShieldCheck size={24} />
                    </div>
                    <div>
                        <div style={{ color: 'var(--color-text-muted)', fontSize: 13, marginBottom: 4 }}>Suket Expiring ({'<'} 90hr)</div>
                        <div style={{ color: 'var(--color-text)', fontSize: 24, fontWeight: 700 }}>{stats.suket_expiring_soon || 0}</div>
                    </div>
                </div>
            )}
        </div>
    );

    const renderStageProgress = () => (
        <div style={{ background: 'var(--color-surface)', padding: 24, borderRadius: 12, border: '1px solid var(--color-border)', marginBottom: 32 }}>
            <h3 style={{ margin: '0 0 20px', fontSize: 16, fontWeight: 600 }}>Pipeline Pekerjaan</h3>
            <div style={{ display: 'flex', gap: 8, height: 40, borderRadius: 8, overflow: 'hidden' }}>
                {STAGES.map(s => {
                    const count = stats.stage_counts[s.id] || 0;
                    const total = stats.total_active || 1;
                    const pct = Math.max(count > 0 ? 5 : 0, (count / total) * 100);
                    if (count === 0) return null;
                    return (
                        <div key={s.id} title={`${s.name}: ${count} job`} style={{
                            width: `${pct}%`, background: `var(--color-${s.tone})`,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            color: '#000', fontWeight: 700, fontSize: 12, transition: 'width 0.3s'
                        }}>
                            {count}
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, marginTop: 16 }}>
                {STAGES.map(s => (
                    <div key={s.id} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--color-text-muted)' }}>
                        <div style={{ width: 10, height: 10, borderRadius: '50%', background: `var(--color-${s.tone})` }} />
                        {s.name} ({stats.stage_counts[s.id] || 0})
                    </div>
                ))}
            </div>
        </div>
    );

    const renderRecentJobs = () => (
        <div style={{ background: 'var(--color-surface)', borderRadius: 12, border: '1px solid var(--color-border)', overflow: 'hidden' }}>
            <div style={{ padding: 20, borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h3 style={{ margin: 0, fontSize: 16, fontWeight: 600 }}>Pekerjaan Terakhir Diupdate</h3>
                <Link href="/jobs" style={{ fontSize: 13, color: 'var(--color-accent)', textDecoration: 'none', fontWeight: 500 }}>Lihat Semua →</Link>
            </div>
            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: 13 }}>
                    <thead>
                        <tr style={{ background: 'var(--color-surface-2)', color: 'var(--color-text-muted)' }}>
                            <th style={{ padding: '12px 20px', fontWeight: 500 }}>Job ID / Klien</th>
                            <th style={{ padding: '12px 20px', fontWeight: 500 }}>Pesawat</th>
                            <th style={{ padding: '12px 20px', fontWeight: 500 }}>Stage Saat Ini</th>
                            <th style={{ padding: '12px 20px', fontWeight: 500 }}>Status SLA</th>
                            <th style={{ padding: '12px 20px', fontWeight: 500 }}>Update Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recentJobs.map(job => {
                            const stageInfo = STAGES.find(s => s.id === job.stage);
                            const st = STATUS_COLORS[job.status as keyof typeof STATUS_COLORS] || STATUS_COLORS.on_track;
                            return (
                                <tr key={job.id} style={{ borderBottom: '1px solid var(--color-border)' }}>
                                    <td style={{ padding: '16px 20px' }}>
                                        <div style={{ fontWeight: 600, color: 'var(--color-accent)', marginBottom: 4 }}>{job.kode}</div>
                                        <div>{job.klien}</div>
                                    </td>
                                    <td style={{ padding: '16px 20px' }}>
                                        {job.pesawat} <span style={{ color: 'var(--color-text-muted)' }}>({job.units} unit)</span>
                                    </td>
                                    <td style={{ padding: '16px 20px' }}>
                                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '4px 10px', background: 'var(--color-surface-2)', borderRadius: 100, fontSize: 12 }}>
                                            <div style={{ width: 8, height: 8, borderRadius: '50%', background: `var(--color-${stageInfo?.tone})` }} />
                                            {stageInfo?.name}
                                        </div>
                                    </td>
                                    <td style={{ padding: '16px 20px' }}>
                                        <span style={{ padding: '4px 8px', borderRadius: 4, background: st.bg, color: st.text, fontSize: 11, fontWeight: 600 }}>
                                            {st.label}
                                        </span>
                                    </td>
                                    <td style={{ padding: '16px 20px', color: 'var(--color-text-muted)' }}>
                                        {formatDate(job.updated_at)}
                                    </td>
                                </tr>
                            );
                        })}
                        {recentJobs.length === 0 && (
                            <tr><td colSpan={5} style={{ padding: 32, textAlign: 'center', color: 'var(--color-text-muted)' }}>Belum ada data pekerjaan.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div style={{ marginBottom: 32 }}>
                <h1 style={{ margin: '0 0 8px', fontSize: 24, fontWeight: 700 }}>Dashboard Overview</h1>
                <p style={{ margin: 0, color: 'var(--color-text-muted)' }}>Ringkasan status operasional riksa uji PT DNP.</p>
            </div>

            {renderSummaryCards()}
            {renderStageProgress()}
            {renderRecentJobs()}
        </AuthenticatedLayout>
    );
}
