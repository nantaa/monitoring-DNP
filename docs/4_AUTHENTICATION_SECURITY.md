# 4. Authentication & Security — DNP Riksa Uji Monitor

## Authentication Method

**Provider:** Laravel Auth (Session/Sanctum)
**Method:** Email + Password with optional MFA (2FA via Laravel Fortify/Jetstream)
**Token format:** Stateful Cookie / CSRF Token for web, Bearer Token for external API

```
User → POST /login { email, password }
     ← Set-Cookie: laravel_session, XSRF-TOKEN

Every web request:
  Includes cookies automatically.
  Laravel validates session → applies authorization via Policies
```

---

## Password Policy

| Rule | Requirement |
|------|-------------|
| Minimum length | 10 characters |
| Complexity | ≥1 uppercase, ≥1 number, ≥1 special character |
| Reuse | Cannot reuse last 5 passwords |
| Expiry | Force reset every 180 days (configurable via `app_config`) |
| Lockout | 5 failed attempts → 15-minute lockout |
| Reset method | Email OTP link only (no SMS — reduces SIM-swap risk) |

---

## Role-Based Access Control (RBAC)

### Roles

| Code | Name | Description |
|------|------|-------------|
| `marketing` | Marketing | Manages PO intake, monitors re-inspection leads |
| `admin` | Admin Dokumen & RU | Document verification, scheduling, logistics |
| `inspektur` | Tim Ahli / Inspektur | Field execution, LHPP preparation |
| `manager` | Kadiv RU / Manager | QC, Suket processing, override authority |
| `finance` | Admin Keuangan | Invoicing, AR tracking, exclusive Stage 7 finalization |

### Stage Permission Matrix

| Stage | Action | MKT | ADM | INS | MGR | FIN |
|-------|--------|-----|-----|-----|-----|-----|
| 1 | Create job | ✅ | ❌ | ❌ | ✅ | ❌ |
| 1 | Edit job (nilai) | ✅ | ❌ | ❌ | ✅¹ | ❌ |
| 2 | Verify checklist | ❌ | ✅ | ❌ | ✅ | ❌ |
| 3 | Schedule + assign | ❌ | ✅ | ❌ | ✅ | ❌ |
| 3 | Generate Surat Tugas | ❌ | ✅ | ❌ | ✅ | ❌ |
| 4 | Upload photos / checklist | ❌ | ❌ | ✅ | ✅ | ❌ |
| 5 | Submit LHPP | ❌ | ❌ | ✅ | ✅ | ❌ |
| 5 | Peer review (approve/reject) | ❌ | ❌ | ❌ | ✅ | ❌ |
| 6 | Submit/track Suket | ❌ | ✅² | ❌ | ✅ | ❌ |
| 7 | Create invoice | ❌ | ❌ | ❌ | ❌ | ✅ |
| 7 | Mark paid / close | ❌ | ❌ | ❌ | ❌ | ✅ |
| Any | View job (read) | Own³ | All | Assigned⁴ | All | All |
| Any | View nilai kontrak | Own | ❌ | ❌ | ✅ | ✅ |

¹ Manager editing `nilai` triggers a mandatory audit log entry and confirmation prompt.
² Admin can log follow-up activity at Stage 6 but cannot submit or close Suket.
³ Marketing sees only jobs they own (`owner_marketing = auth.uid()`).
⁴ Inspektur sees only jobs where their ID is in `assigned_inspektur`.

---

## Laravel Authorization Policies

Authorization is **enforced on all models via Laravel Policies**. Representative policies (e.g. in `app/Policies/JobPolicy.php`):

```php
// jobs: Marketing sees only own jobs
public function viewAny(User $user)
{
    if ($user->role === 'marketing') {
        return Job::where('owner_marketing', $user->id);
    }
    return Job::query();
}

// jobs: Only finance can update stage 7 close
public function updateStage7(User $user, Job $job)
{
    return $user->role === 'finance' && $job->stage === 7;
}

// audit_log: no DELETE or UPDATE for any role
public function update(User $user, AuditLog $auditLog)
{
    return false; // Nobody can update
}

public function delete(User $user, AuditLog $auditLog)
{
    return false; // Nobody can delete
}
```

---

## Field-Level Restrictions

| Field | Restriction | Enforcement |
|-------|-------------|-------------|
| `jobs.nilai` | Hidden from `inspektur` | API Resource mapping |
| `jobs.nilai` | `marketing` cannot edit after Stage 2 | Stage-check trigger |
| `jobs.nilai` | `manager` edit requires audit prompt | UI + trigger alert |
| `job_evaluation.*` | Locked after `lhpp_locked = true` | `CHECK` constraint + trigger |
| `audit_log.*` | No UPDATE/DELETE ever | Policy class return false |

```sql
-- Trigger: prevent editing locked evaluation
CREATE OR REPLACE FUNCTION fn_prevent_locked_eval_edit() RETURNS TRIGGER AS $$
BEGIN
  IF OLD.locked = true THEN
    RAISE EXCEPTION 'Cannot modify locked evaluation. Contact Manager.';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_lock_evaluation
  BEFORE UPDATE ON job_evaluation
  FOR EACH ROW EXECUTE FUNCTION fn_prevent_locked_eval_edit();
```

---

## Security Headers

All responses from Laravel and Nginx must include:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...
```

Configure in Nginx site config or via a Laravel Global Middleware:
```php
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        return $response;
    }
}
```

---

## Input Validation

**Frontend:** Zod schemas for all form inputs before API calls.
**Backend:** Laravel Request Validation (FormRequests) validate all inputs server-side.

```typescript
// Example: Job creation schema
import { z } from 'zod';

const CreateJobSchema = z.object({
  klien:           z.string().min(3).max(200),
  lokasi:          z.string().min(3).max(200),
  pesawat:         z.enum(['LIFT','ESC','PAPA','FIRE','LISTRIK','BOILER','PV','PTP']),
  units:           z.number().int().min(1).max(999),
  nilai:           z.number().int().min(100000).max(10_000_000_000),
  no_po:           z.string().optional(),
  tgl_po:          z.string().date().optional(),
  disnaker_tujuan: z.string().optional(),
});
```

---

## File Upload Security

```
Allowed MIME types: application/pdf, image/jpeg, image/png, image/webp
Max file size: 20 MB per file
Max files per job: 200
Storage bucket: private (no public URL — access via signed URL, 1-hour expiry)
Filename sanitization: UUID-based paths only (no original filename in storage path)
Virus scan: Integrate ClamAV on upload via Laravel job
```

Signed URL generation:
```php
$url = URL::temporarySignedRoute(
    'job-files.download', now()->addHour(), ['path' => $storagePath]
);
```

---

## Compliance and Audit Trail

### Kemnaker Audit Requirements
- Every job must have a complete, immutable history of: who created it, who advanced each stage, who approved the LHPP, who submitted to Disnaker.
- The `audit_log` table satisfies this: write-only, trigger-fed, includes `auth.uid()` on every row.
- Export to PDF/CSV available for FIN and MGR roles via `/functions/v1/audit-export?job_id=uuid`.

### Data Retention Policy
- Job data: permanent (no auto-delete)
- File attachments: permanent while job exists; soft-delete only
- Audit log: permanent, never purged
- Auth logs (Laravel): Configurable via log rotation

### MFA Policy
- Required for `manager` and `finance` roles (enforced via Supabase Auth settings)
- Recommended for `admin` role
- Optional for `marketing` and `inspektur`

---

## Secrets Management

| Secret | Location | Rotation |
|--------|----------|----------|
| `DB_PASSWORD` | VPS `.env` file | Yearly |
| `WHATSAPP_API_KEY` | VPS `.env` file | Quarterly |
| `RESEND_API_KEY` | VPS `.env` file | Quarterly |

**Rule:** No secrets in source code, `.env` never committed.

---

## Vulnerability Management

- Dependabot or `npm audit` in CI — block merge if high/critical CVE
- VPS handles OS/Postgres patching via unattended-upgrades or manual process
- Frontend dependencies: `npm audit fix` before each release
- Penetration test: annually or before major releases
