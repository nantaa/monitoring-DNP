import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LogIn, Lock, Mail, Shield } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Login" />
            <div style={{
                minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: 'var(--color-bg)', padding: 24,
            }}>
                {/* Background glow */}
                <div style={{ position: 'fixed', top: '20%', left: '30%', width: 600, height: 600, background: 'radial-gradient(circle, rgba(79,142,247,0.08) 0%, transparent 70%)', pointerEvents: 'none' }} />

                <div style={{ width: '100%', maxWidth: 420, position: 'relative' }}>
                    {/* Logo */}
                    <div style={{ textAlign: 'center', marginBottom: 40 }}>
                        <div style={{ width: 64, height: 64, background: 'linear-gradient(135deg, #4f8ef7 0%, #6366f1 100%)', borderRadius: 16, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16, boxShadow: '0 0 40px rgba(79,142,247,0.3)' }}>
                            <Shield size={32} color="#fff" />
                        </div>
                        <h1 style={{ margin: 0, fontSize: 28, fontWeight: 700, color: 'var(--color-text)' }}>DNP Monitor</h1>
                        <p style={{ margin: '8px 0 0', color: 'var(--color-text-muted)', fontSize: 14 }}>Sistem Monitoring Riksa Uji K3</p>
                    </div>

                    {/* Card */}
                    <div style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 16, padding: 32 }}>
                        <h2 style={{ margin: '0 0 24px', fontSize: 18, fontWeight: 600, color: 'var(--color-text)' }}>Masuk ke Akun</h2>

                        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <div>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: 'var(--color-text-muted)', marginBottom: 6 }}>Email</label>
                                <div style={{ position: 'relative' }}>
                                    <Mail size={16} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }} />
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        placeholder="nama@dnp.co.id"
                                        autoComplete="email"
                                        style={{
                                            width: '100%', boxSizing: 'border-box', padding: '10px 12px 10px 38px',
                                            background: 'var(--color-surface-2)', border: `1px solid ${errors.email ? '#f87171' : 'var(--color-border)'}`,
                                            borderRadius: 8, color: 'var(--color-text)', fontSize: 14, outline: 'none',
                                        }}
                                    />
                                </div>
                                {errors.email && <p style={{ margin: '4px 0 0', fontSize: 12, color: '#f87171' }}>{errors.email}</p>}
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, color: 'var(--color-text-muted)', marginBottom: 6 }}>Password</label>
                                <div style={{ position: 'relative' }}>
                                    <Lock size={16} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }} />
                                    <input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        autoComplete="current-password"
                                        style={{
                                            width: '100%', boxSizing: 'border-box', padding: '10px 12px 10px 38px',
                                            background: 'var(--color-surface-2)', border: `1px solid ${errors.password ? '#f87171' : 'var(--color-border)'}`,
                                            borderRadius: 8, color: 'var(--color-text)', fontSize: 14, outline: 'none',
                                        }}
                                    />
                                </div>
                                {errors.password && <p style={{ margin: '4px 0 0', fontSize: 12, color: '#f87171' }}>{errors.password}</p>}
                            </div>

                            <button
                                id="login-btn"
                                type="submit"
                                disabled={processing}
                                style={{
                                    marginTop: 8, padding: '12px', background: 'linear-gradient(135deg, #4f8ef7 0%, #6366f1 100%)',
                                    border: 'none', borderRadius: 8, color: '#fff', fontSize: 15, fontWeight: 600,
                                    cursor: processing ? 'not-allowed' : 'pointer', opacity: processing ? 0.7 : 1,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, transition: 'opacity 0.2s',
                                }}
                            >
                                <LogIn size={18} /> {processing ? 'Memproses...' : 'Masuk'}
                            </button>
                        </form>

                        {/* Demo creds hint */}
                        <div style={{ marginTop: 24, padding: 12, background: 'var(--color-surface-2)', borderRadius: 8, fontSize: 12, color: 'var(--color-text-muted)' }}>
                            <strong style={{ color: 'var(--color-text)' }}>Demo:</strong> terzha@dnp.co.id / password
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
