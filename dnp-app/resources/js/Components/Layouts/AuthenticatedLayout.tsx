import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, Columns, List, Bell, LogOut, Menu, X,
    Building2, ChevronRight, AlertTriangle,
} from 'lucide-react';
import { ROLES } from '@/lib/constants';
import type { PageProps } from '@/types';

interface Props { children: React.ReactNode; }

export default function AuthenticatedLayout({ children }: Props) {
    const { auth, flash } = usePage<PageProps>().props;
    const role = auth.user.role;
    const roleInfo = ROLES[role];
    const [sidebarOpen, setSidebarOpen] = useState(true);

    const nav = [
        { label: 'Dashboard',   href: '/dashboard', icon: LayoutDashboard },
        { label: 'Kanban',      href: '/jobs?view=kanban', icon: Columns },
        { label: 'Daftar Pekerjaan', href: '/jobs', icon: List },
    ];

    return (
        <div style={{ display: 'flex', minHeight: '100vh', background: 'var(--color-bg)' }}>
            {/* Sidebar */}
            <aside style={{
                width: sidebarOpen ? 240 : 64, flexShrink: 0, transition: 'width 0.2s ease',
                background: 'var(--color-surface)', borderRight: '1px solid var(--color-border)',
                display: 'flex', flexDirection: 'column', zIndex: 50,
            }}>
                {/* Logo */}
                <div style={{ padding: '20px 16px', borderBottom: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div style={{ width: 36, height: 36, background: 'var(--color-accent)', borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                        <Building2 size={20} color="#fff" />
                    </div>
                    {sidebarOpen && (
                        <div style={{ overflow: 'hidden' }}>
                            <div style={{ fontWeight: 700, fontSize: 14, color: 'var(--color-text)', whiteSpace: 'nowrap' }}>DNP Monitor</div>
                            <div style={{ fontSize: 11, color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>Riksa Uji K3</div>
                        </div>
                    )}
                </div>

                {/* User Badge */}
                {sidebarOpen && (
                    <div style={{ margin: '12px', padding: '10px 12px', background: 'var(--color-surface-2)', borderRadius: 8, border: '1px solid var(--color-border)' }}>
                        <div style={{ fontWeight: 600, fontSize: 13, color: 'var(--color-text)' }}>{auth.user.name}</div>
                        <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>{roleInfo?.tagline}</div>
                        <span style={{ display: 'inline-block', marginTop: 6, padding: '2px 8px', background: 'rgba(79,142,247,0.2)', color: 'var(--color-accent)', borderRadius: 4, fontSize: 10, fontWeight: 700 }}>
                            {roleInfo?.label}
                        </span>
                    </div>
                )}

                {/* Navigation */}
                <nav style={{ flex: 1, padding: '8px 8px', display: 'flex', flexDirection: 'column', gap: 2 }}>
                    {nav.map(item => {
                        const Icon = item.icon;
                        const active = window.location.pathname === item.href.split('?')[0];
                        return (
                            <Link key={item.href} href={item.href} style={{
                                display: 'flex', alignItems: 'center', gap: 12, padding: '9px 10px',
                                borderRadius: 8, textDecoration: 'none', transition: 'all 0.15s',
                                background: active ? 'rgba(79,142,247,0.2)' : 'transparent',
                                color: active ? 'var(--color-accent)' : 'var(--color-text-muted)',
                                fontWeight: active ? 600 : 400, fontSize: 14,
                            }}>
                                <Icon size={18} style={{ flexShrink: 0 }} />
                                {sidebarOpen && <span style={{ whiteSpace: 'nowrap', overflow: 'hidden' }}>{item.label}</span>}
                            </Link>
                        );
                    })}
                </nav>

                {/* Logout */}
                <div style={{ padding: '8px', borderTop: '1px solid var(--color-border)' }}>
                    <button
                        onClick={() => router.post('/logout')}
                        style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '9px 10px', width: '100%', border: 'none', background: 'transparent', cursor: 'pointer', borderRadius: 8, color: 'var(--color-text-muted)', fontSize: 14 }}
                    >
                        <LogOut size={18} />
                        {sidebarOpen && <span>Keluar</span>}
                    </button>
                </div>
            </aside>

            {/* Main */}
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                {/* Header */}
                <header style={{ height: 56, background: 'var(--color-surface)', borderBottom: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', padding: '0 20px', gap: 12, flexShrink: 0 }}>
                    <button onClick={() => setSidebarOpen(p => !p)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--color-text-muted)', display: 'flex', alignItems: 'center' }}>
                        <Menu size={20} />
                    </button>
                    <div style={{ flex: 1 }} />
                    <Bell size={18} style={{ color: 'var(--color-text-muted)', cursor: 'pointer' }} />
                </header>

                {/* Flash */}
                {flash?.success && (
                    <div style={{ margin: '12px 20px 0', padding: '10px 14px', background: 'rgba(52,211,153,0.15)', border: '1px solid rgba(52,211,153,0.4)', borderRadius: 8, color: '#34d399', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                        <ChevronRight size={16} /> {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div style={{ margin: '12px 20px 0', padding: '10px 14px', background: 'rgba(248,113,113,0.15)', border: '1px solid rgba(248,113,113,0.4)', borderRadius: 8, color: '#f87171', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
                        <AlertTriangle size={16} /> {flash.error}
                    </div>
                )}

                {/* Content */}
                <main style={{ flex: 1, overflow: 'auto', padding: '24px' }}>
                    {children}
                </main>
            </div>
        </div>
    );
}
