# 5. Error Handling & Logging — DNP Riksa Uji Monitor

## Error Code Registry

All application errors use the prefix `DNP_ERR_` followed by an HTTP-style category and a short descriptor.

| Code | HTTP | Meaning | User-Facing Message |
|------|------|---------|---------------------|
| `DNP_ERR_401` | 401 | Unauthenticated | "Sesi Anda telah berakhir. Silakan login kembali." |
| `DNP_ERR_403_ROLE` | 403 | Insufficient role for action | "Anda tidak memiliki izin untuk tindakan ini." |
| `DNP_ERR_403_STAGE` | 403 | Wrong stage for action | "Tindakan ini tidak tersedia di tahap saat ini." |
| `DNP_ERR_403_LOCKED` | 403 | Record is locked | "Data sudah dikunci oleh Manager dan tidak dapat diubah." |
| `DNP_ERR_404_JOB` | 404 | Job not found | "Pekerjaan tidak ditemukan." |
| `DNP_ERR_409_DUPLICATE` | 409 | Duplicate kode/invoice | "Nomor sudah digunakan. Gunakan nomor yang berbeda." |
| `DNP_ERR_422_GATE` | 422 | Stage gate conditions unmet | "Syarat belum terpenuhi: {details}" |
| `DNP_ERR_422_VALIDATION` | 422 | Input validation failed | "Data tidak valid: {field} {reason}" |
| `DNP_ERR_422_SKP_EXPIRED` | 422 | Assigned inspektur has expired SKP | "SKP inspektur {nama} sudah kadaluarsa pada {date}." |
| `DNP_ERR_422_ALAT_EXPIRED` | 422 | Assigned alat has expired calibration | "Kalibrasi alat {nama} sudah kadaluarsa. Pilih alat lain." |
| `DNP_ERR_422_NILAI_LOCKED` | 422 | Nilai kontrak locked, cannot edit | "Nilai kontrak terkunci setelah Tahap 2. Hubungi Manager." |
| `DNP_ERR_429` | 429 | Rate limit exceeded | "Terlalu banyak permintaan. Coba lagi dalam 1 menit." |
| `DNP_ERR_500` | 500 | Internal server error | "Terjadi kesalahan sistem. Tim teknis sudah diberitahu." |
| `DNP_ERR_503` | 503 | Supabase or dependency unavailable | "Sistem sedang tidak tersedia. Coba lagi dalam beberapa menit." |

**Principle: Never expose internals.** Stack traces, SQL errors, or storage paths must never reach the client response. Database errors are caught and mapped to `DNP_ERR_*` codes by the Laravel Exception Handler or a frontend error interceptor.

---

## Error Response Format

```json
{
  "error": {
    "code": "DNP_ERR_422_GATE",
    "message": "Syarat belum terpenuhi untuk lanjut ke Tahap 4",
    "details": {
      "unmet_conditions": [
        "tgl_pelaksanaan belum diisi",
        "Minimal 1 inspektur harus ditugaskan",
        "Surat Tugas belum digenerate"
      ]
    }
  }
}
```

---

## Frontend Error Handling

### Axios / Inertia Interceptor

```typescript
// resources/js/lib/axios.ts
import axios from 'axios';

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true, // For Sanctum CSRF/Session
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const code = error.response?.data?.error?.code || 'DNP_ERR_500';
    throw new AppError(code, error.message);
  }
);
```

### UI Error Boundaries

```tsx
// components/ErrorBoundary.tsx
class JobErrorBoundary extends React.Component {
  state = { hasError: false, errorCode: '' };

  static getDerivedStateFromError(error: AppError) {
    return { hasError: true, errorCode: error.code };
  }

  render() {
    if (this.state.hasError) {
      return <ErrorScreen code={this.state.errorCode} />;
    }
    return this.props.children;
  }
}
```

### Toast Notifications for User Feedback

```typescript
// Optimistic update pattern with rollback
async function advanceStage(jobId: string) {
  const previousStage = job.stage;
  setJob({ ...job, stage: job.stage + 1 }); // optimistic

  try {
    await api.advanceStage(jobId);
    toast.success('Pekerjaan berhasil dilanjutkan ke tahap berikutnya.');
  } catch (err) {
    setJob({ ...job, stage: previousStage }); // rollback
    toast.error(getErrorMessage(err.code));
  }
}
```

---

## Logging Strategy

### Log Levels

| Level | When to Use | Examples |
|-------|-------------|---------|
| `ERROR` | Unrecoverable failures, 5xx responses | DB connection fail, Laravel Queue Worker crash |
| `WARN` | Recoverable issues, unexpected states | Retry succeeded, SKP near-expiry detected |
| `INFO` | Normal significant events | Job advanced, Suket terbit, Invoice paid |
| `DEBUG` | Developer diagnostics (not in production) | Recommendation algorithm score breakdown |

### Log Format (JSON structured)

```json
{
  "timestamp": "2026-06-12T10:30:00.000Z",
  "level": "INFO",
  "service": "dnp-monitor",
  "event": "job.stage.advanced",
  "job_id": "uuid",
  "job_kode": "DNP/2026/0001",
  "from_stage": 2,
  "to_stage": 3,
  "actor_id": "auth-uuid",
  "actor_role": "admin",
  "duration_ms": 142
}
```

### What to Log

**Always log:**
- Job stage transitions (who, from, to, timestamp)
- Manager override actions (field changed, old value, new value)
- LHPP lock events (who approved, timestamp)
- Suket status changes (unit, from, to, who)
- Invoice creation and payment marking
- Failed authentication attempts (email, IP, timestamp)
- File uploads (job_id, doc_type, file_size, uploader)
- Gate validation failures (job_id, unmet conditions)

**Never log:**
- Passwords (even hashed)
- Full JWT tokens
- File contents
- Personal contact data beyond reference IDs

---

## Monitoring & Alerting

### VPS & Application Monitoring
- Server Load (CPU/RAM/Disk): Monitor via Netdata, Datadog, or Zabbix.
- Database: PostgreSQL slow queries and connection pool via `pg_stat_statements`.
- Application: Laravel Pulse or Sentry/Bugsnag for tracking 5xx errors and slow requests.

### Custom Alerts (via Laravel Scheduler + Queues)

| Alert | Trigger | Recipients |
|-------|---------|-----------|
| Alat kalibrasi akan expire | 30 days before `kalibrasi_expired` | ADM, MGR |
| SKP Ahli K3 akan expire | 60 days before `skp_expired` | MGR |
| Sertifikat PJK3 akan expire | 90 days before `expired` | MGR, Direksi |
| Invoice overdue | Daily cron: `jatuh_tempo < today AND status != 'paid'` | FIN, MGR |
| LHPP SLA breach | Job at Stage 5 > 3 hari kerja without submission | MGR |
| H-5 reminder | 5 days before `tgl_pelaksanaan` | ADM, INS assigned |
| Suket follow-up | Job at Stage 6 unit `Di Disnaker` > 30 days | MGR |

---

## Laravel Exception Handling Pattern

```php
// bootstrap/app.php or app/Exceptions/Handler.php
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'error' => [
                    'code' => 'DNP_ERR_422_VALIDATION',
                    'message' => 'Validasi gagal.',
                    'details' => $e->errors()
                ]
            ], 422);
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'error' => [
                    'code' => 'DNP_ERR_403_ROLE',
                    'message' => 'Anda tidak memiliki izin.'
                ]
            ], 403);
        }

        // Default 500
        return response()->json([
            'error' => [
                'code' => 'DNP_ERR_500',
                'message' => 'Terjadi kesalahan sistem.'
            ]
        ], 500);
    }
}
```

---

## Incident Response

### Severity Levels

| Severity | Example | Response Time | Escalation |
|----------|---------|--------------|------------|
| P1 — Critical | Data loss, DB down, all users locked out | 30 min | Developer + Director |
| P2 — High | Stage gate broken, invoice cannot be issued | 2 hours | Developer + MGR |
| P3 — Medium | Single feature broken, workaround exists | 8 hours | Developer |
| P4 — Low | UI glitch, non-blocking | Next sprint | Developer |

### Runbook: DB Connection Failure
1. SSH into the VPS and check PostgreSQL status: `systemctl status postgresql`
2. Check `storage/logs/laravel.log` for connection string issues
3. Check PostgreSQL connection pool / max_connections
4. Restart PostgreSQL service if frozen: `systemctl restart postgresql`
5. If persists > 30 min: escalate to Senior Sysadmin

### Runbook: Data Corruption Suspicion
1. Immediately put the application in maintenance mode: `php artisan down`
2. Export `audit_log` for affected time range
3. Identify offending user and action
4. Restore DB from the last clean VPS daily snapshot or pg_dump file
5. Replay `audit_log` events post-backup to re-apply legitimate changes
6. Bring application back online: `php artisan up`
