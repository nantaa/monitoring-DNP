# 12. Compliance & Standards — DNP Riksa Uji Monitor

> PT DNP operates under Kemnaker RI regulatory oversight. This document covers what the system must demonstrate during a Kemnaker audit, internal code standards, and incident response procedures.

---

## 12.1 Regulatory Compliance Requirements

PT DNP holds SK Kemnaker No. KEP.001/PPK-PJK3/V/2023. To maintain this certification and pass periodic Kemnaker audits, the system must be able to demonstrate:

### Audit trail integrity

Every change to a job record — including who changed it, what was changed, and when — must be logged in an immutable `audit_logs` table. "Immutable" means no UPDATE or DELETE is permitted on audit rows, enforced by database-level RLS (not just application policy).

```sql
-- Verification query for auditors: show full history of a job
SELECT
  al.changed_at AT TIME ZONE 'Asia/Jakarta' AS waktu_wib,
  p.nama_lengkap AS diubah_oleh,
  p.role AS role,
  al.action,
  al.old_data -> 'stage' AS stage_lama,
  al.new_data -> 'stage' AS stage_baru,
  al.new_data -> 'status_laik' AS status_laik,
  al.new_data -> 'peer_review_status' AS peer_review
FROM audit_logs al
JOIN profiles p ON p.id = al.changed_by
WHERE al.record_id = '<job_id>'
ORDER BY al.changed_at ASC;
```

### Inspector credential validity

The system hard-locks inspectors with expired SKP from being assigned to jobs. Before any job advances from Stage 3, the system verifies:

- `skp_expired >= tgl_pelaksanaan` for all assigned inspectors
- `kalibrasi_expired >= tgl_pelaksanaan` for all assigned alat uji
- At least one valid PJK3 certificate exists for the `pesawat` type of the job

These checks run at both UI level (disable button) and service layer (validate before saving stage transition).

### Document completeness gate

Stage 2 → 3 advance requires all mandatory items in `VERIFY_CHECKLIST_STAGE2` to be checked. This checklist directly maps to SOP-04 Rev 01 and FM-PJK3-RIKU-009. The system cannot skip a mandatory item without manager override (which is logged).

### LAIK/TIDAK LAIK lock

Once a Kadiv/Manager approves LHPP (peer review `approved`), the `status_laik` field and all evaluation fields are **locked permanently** — no user including manager can modify them. This ensures the technical conclusion submitted to Disnaker matches what's in the system.

```js
// Enforce in service layer — not just UI
if (job.peer_review_status === 'approved') {
  const LOCKED_FIELDS = ['status_laik', 'catatan_evaluasi', 'lhpp_content'];
  for (const field of LOCKED_FIELDS) {
    if (updates[field] !== undefined && updates[field] !== job[field]) {
      throw new Error(`Field ${field} terkunci setelah LHPP disetujui.`);
    }
  }
}
```

---

## 12.2 Data Retention Policy

| Data Category | Retention | Reason |
|--------------|-----------|--------|
| Job records (active) | Indefinite | Operational |
| Job records (completed) | 10 years | Kemnaker audit requirement |
| Audit logs | 10 years | Kemnaker audit requirement |
| Field photos (Stage 4) | 10 years | Evidence for liability |
| LHPP & BAP documents | 10 years | Legal documents |
| Invoices | 10 years | Accounting / tax obligation |
| SKP and sertifikat scans | Duration of validity + 2 years | Compliance backup |
| User session logs | 1 year | Security review |

Archive jobs older than 2 years to `jobs_archive` to keep primary table lean, but **never delete** — see §11.3 for archival SQL.

---

## 12.3 Role Separation (Segregation of Duties)

The following segregation is enforced by system design and must be preserved in all future changes:

| Separation | Rationale |
|-----------|-----------|
| Inspektur cannot see `nilai_kontrak` | Prevents conflict of interest in evaluation |
| Only Finance (`FIN`) can issue invoice and mark paid | Accounting segregation |
| Manager can override any stage, but every override is logged | Emergency access with accountability |
| LHPP evaluation locked after Manager approval | Prevents post-submission tampering |
| Marketing cannot modify `nilai_kontrak` after Stage 2 | Prevents retroactive contract manipulation |

Any future feature request that would violate one of these separations must be reviewed and explicitly approved by the Operations Manager before implementation.

---

## 12.4 Data Privacy

DNP Monitor stores personal data of:
- PT DNP employees (nama, no HP, domisili, SKP)
- Client contacts (PIC nama, no HP)
- Inspector credentials (SKP numbers, specialisations)

UU ITE and general Indonesian data protection principles apply.

### Minimum required controls

- All data transmission over HTTPS (enforced by Nginx/Certbot — no HTTP).
- Database at rest is protected by server security policies and strict firewall rules.
- Employee phone numbers are visible only to their own role and Manager — not exposed to cross-role views.
- Client PIC phone numbers visible to Admin, Inspector (for tap-to-call), and Manager.
- No data is shared with third parties. WhatsApp notifications are dispatched securely via a local Node.js service (Baileys), keeping data entirely within the VPS.

### Access deprovisioning

When an employee leaves PT DNP:
1. Admin sets `active = false` in `inspektur_list` (locks from new job assignments).
2. Admin sets `active = false` on their user account in the system (prevents login).
3. Do **not** delete the user record — historical audit logs reference their `user_id`.

---

## 12.5 Code Quality Standards

### Linting and formatting

Enforced via pre-commit hooks (`husky` + `lint-staged`):

```json
// package.json
"lint-staged": {
  "*.{js,jsx,ts,tsx}": ["eslint --fix", "prettier --write"],
  "*.{css,md,json}": ["prettier --write"]
}
```

ESLint rules that are non-negotiable:

```json
// .eslintrc.json
{
  "rules": {
    "react-hooks/exhaustive-deps": "error",
    "no-console": ["warn", { "allow": ["error", "warn"] }],
    "no-unused-vars": "error"
  }
}
```

### Code review checklist

Before merging any PR that touches job data or permissions:

- [ ] Does this change respect the permission matrix?
- [ ] Is every write action going through standard Controllers and FormRequests?
- [ ] Is the audit log Observer still intact?
- [ ] Is financial data (`nilai_kontrak`, invoice amounts) hidden from `inspektur` role?
- [ ] Are expired master data items still hard-locked?
- [ ] Are stage gate conditions still enforced in the service layer (not just UI)?

---

## 12.6 Security Standards

See File 4 (Authentication & Security) for full detail. Key points relevant to compliance:

- Passwords minimum 10 characters, enforced by Laravel validation rules.
- MFA recommended for `manager` and `finance` roles via Laravel Fortify / Two-Factor Authentication.
- All backend authorization is guarded by Laravel Policies.
- No secrets in frontend code or repository.
- Keys are stored securely in `.env` only on the VPS.

---

## 12.7 Incident Response

### Severity levels

| Level | Definition | Example | Response Time |
|-------|-----------|---------|---------------|
| P1 — Critical | System down or data loss | Database unreachable, accidental table drop | 1 hour |
| P2 — High | Major function broken | Stage advance blocked for all users, WhatsApp not sending | 4 hours |
| P3 — Medium | Partial degradation | One role's dashboard broken, slow query | 1 business day |
| P4 — Low | Minor issue / cosmetic | Wrong label, UI alignment off | Next sprint |

### Incident response steps (P1/P2)

1. **Detect** — Server monitoring alerts (e.g., Zabbix, Netdata), or user reports via WhatsApp to Operations Manager.
2. **Assess** — is this a server outage (check VPS provider dashboard) or application bug?
3. **Communicate** — Operations Manager notifies all active users via WhatsApp: "Sistem sedang dalam perbaikan. Gunakan sistem manual sementara."
4. **Mitigate** — if application bug: rollback to previous Git commit (< 1 minute). If VPS issue: contact hosting provider support.
5. **Restore** — verify system functional with a test login for each role.
6. **Post-mortem** — write a brief note (what happened, why, fix, prevention) within 48 hours.

### Backup verification

Monthly: restore latest backup to a test local PostgreSQL database and verify:

```bash
# Retrieve latest backup and restore
pg_restore -d test_dnp_monitor latest-backup.dump

# Verify row counts
sudo -u postgres psql -d test_dnp_monitor -c "SELECT COUNT(*) FROM jobs; SELECT COUNT(*) FROM audit_logs;"
```

### What NOT to do during an incident

- Do not roll back database migrations without testing on a copy first.
- Do not disable RLS policies to "fix" a permission issue quickly — this exposes all data.
- Do not share credentials in WhatsApp or email to resolve quickly.

---

## 12.8 SOP Versioning Alignment

The system implements SOP-04 Rev 01. When Kemnaker issues new Permenaker or PT DNP revises its SOP:

1. Operations Manager identifies which constants or checklist items need to change (e.g. new Permenaker changes Suket validity period for a pesawat type).
2. Developer updates the relevant constant in `src/constants/` and runs migration if database-stored.
3. Old validity calculations still apply to jobs that were created before the revision (grandfathered). Only new jobs from the effective date use the new values.
4. Document the change in this file under a new revision entry.

### Revision history

| Date | SOP Ref | Change | Updated By |
|------|---------|--------|-----------|
| 12 Jun 2026 | SOP-04 Rev 01 | Initial system implementation | Terzha R. Perdanawan |

---

## 12.9 Kemnaker Audit Export

When a Kemnaker audit is scheduled, the system must be able to produce:

1. **Complete job history** for any date range — export via Finance/Manager dashboard as Excel (SheetJS).
2. **Audit log for specific jobs** — query `audit_logs` table, export as PDF.
3. **SKP validity log** — export `inspektur_list` with historical SKP expiry dates.
4. **Alat kalibrasi log** — export `alat_inventory` with kalibrasi history.
5. **All LHPP and BAP documents** — downloadable from VPS Local Storage (`storage/app/public`) per job.

For v1.0, these exports are manual. For v1.1, add an "Audit Package" generator that compiles all of the above for a given job or date range into a ZIP file.
