# 11. Scalability & Growth — DNP Riksa Uji Monitor

> **Current scale**: PT DNP operates ~30–100 jobs/month, 5 concurrent users, single branch. This document plans for 2–5× growth and optional multi-branch / multi-tenant scenarios.

---

## 11.1 Current Capacity Assessment

| Resource | Prototype Limit | Production v1.0 Capacity | Notes |
|----------|----------------|--------------------------|-------|
| Concurrent users | 1 (localStorage only) | 100+ | Typical 4vCPU/8GB VPS capacity |
| Jobs per month | Unlimited (in theory) | Thousands | PostgreSQL handles this trivially |
| File storage | ~5–10 MB total | VPS Disk Size | Expandable via Block Storage |
| Real-time | N/A | 500+ | Handled by Laravel Reverb/WebSockets |
| Database size | Browser only | VPS Disk Size | - |

PT DNP is **not an infrastructure scaling problem** at v1.0. A standard $20/month VPS can comfortably handle 10× PT DNP's current volume. This document focuses on **operational scaling** (more users, more branches) rather than pure infrastructure tuning.

---

## 11.2 User Growth Path

### Phase 1 (current): 5 users, 1 branch

The v1.0 architecture (Laravel + VPS) handles this trivially. No scaling work needed.

### Phase 2: 10–20 users, 1 branch (e.g. subcontracting inspectors)

- Add more users in the Admin Dashboard.
- The RBAC system (Laravel Policies) scales automatically.
- No infrastructure changes required.

### Phase 3: 20–50 users, 2–3 branches (regional expansion)

See §11.4 for multi-branch considerations. Infrastructure-wise, still within standard VPS capacity.

---

## 11.3 Job Volume Scaling

At 10× current volume (~300–1000 jobs/month), the only architectural change needed is:

### Archive old jobs

Jobs in Stage 7 with `tgl_selesai > 2 years ago` move to `jobs_archive` table. The primary `jobs` table stays lean (< 5,000 rows), keeping query times fast without needing read replicas.

```php
// Run monthly via Laravel Scheduler
$schedule->call(function () {
    $cutoff = now()->subYears(2);
    
    // Archive Logic
    DB::insert('INSERT INTO jobs_archive SELECT * FROM jobs WHERE stage = 7 AND updated_at < ?', [$cutoff]);
    DB::delete('DELETE FROM jobs WHERE id IN (SELECT id FROM jobs_archive WHERE updated_at < ?)', [$cutoff]);
})->monthlyOn(1, '02:00');
```

### Search performance at scale

When active jobs exceed ~500, add full-text search on `klien_nama` and `kode_job`:

```sql
ALTER TABLE jobs ADD COLUMN search_vector tsvector
  GENERATED ALWAYS AS (
    to_tsvector('indonesian', coalesce(klien_nama, '') || ' ' || coalesce(kode_job, ''))
  ) STORED;

CREATE INDEX idx_jobs_search ON jobs USING GIN(search_vector);
```

Query with:
```sql
SELECT * FROM jobs WHERE search_vector @@ plainto_tsquery('indonesian', 'hotel indonesia');
```

---

## 11.4 Multi-Branch Architecture

If PT DNP opens a second branch (e.g. Bandung) or acquires another PJK3 company, options are:

### Option A: Single tenant, branch filter (recommended for 2–3 branches)

Add a `branch_id` column to the `jobs` table and `inspektur_list`. Row Level Security filters by the user's branch. Users with `manager` role can see all branches (helicopter view).

```sql
ALTER TABLE jobs ADD COLUMN branch_id TEXT NOT NULL DEFAULT 'bekasi';
ALTER TABLE profiles ADD COLUMN branch_id TEXT NOT NULL DEFAULT 'bekasi';

-- RLS: users see only their branch (except manager)
CREATE POLICY "branch_isolation" ON jobs
  FOR ALL USING (
    branch_id = (SELECT branch_id FROM profiles WHERE id = auth.uid())
    OR (SELECT role FROM profiles WHERE id = auth.uid()) = 'manager'
  );
```

This approach reuses all existing infrastructure and code. Zero additional cost.

### Option B: Multi-tenant (separate databases on same VPS)

Use if PT DNP wants to white-label the system for other PJK3 companies as SaaS. Each tenant gets their own database, but shares the same Laravel application instance via multi-tenancy packages (e.g., `spatie/laravel-multitenancy` or `stancl/tenancy`).

This is significantly more complex. **Do not implement before Option A is proven insufficient.**

---

## 11.5 Inspector Capacity Planning

The `recommendInspektur()` algorithm's workload scoring is the system's built-in capacity planning tool. At scale, the Manager dashboard's "Beban Kerja per Inspektur" panel shows distribution — use it to decide when to hire the next inspector.

Trigger to hire when:
- 2+ inspectors consistently hit workload penalty (≥4 active jobs) for 2 consecutive months.
- Stage 3 → 4 average time exceeds 5 business days (bottleneck at scheduling).

Add new inspectors to `inspektur_list` master data table — no code changes required.

---

## 11.6 Suket Expiry Growth

As the job count grows, the number of Suket approaching expiry (90-day reminder window) that Marketing tracks also grows. The `SuketReminder` component already queries jobs where any unit in `units_tracking` has `suket_expired` within 90 days.

If this list grows beyond ~50 entries, convert it to a paginated view with priority sorting (soonest expiry first). The query:

```sql
SELECT j.*, u.suket_expired, u.unit_kode
FROM jobs j, jsonb_to_recordset(j.units_tracking) AS u(unit_kode text, suket_expired date, status text)
WHERE u.suket_expired BETWEEN now() AND now() + interval '90 days'
  AND u.status = 'terbit'
ORDER BY u.suket_expired ASC;
```

---

## 11.7 Notification Volume

At 10× scale, WhatsApp notification volume increases. Since we are using a local Baileys Node.js instance, there are no per-message costs. However, we must monitor the RAM usage of the Node.js process:

- Estimate: ~1,500–3,000 messages/month
- Action: Allocate at least 1GB of RAM for the WhatsApp microservice. Restart the PM2 process daily to prevent memory leaks from the Web Socket connection.

To reduce notification volume, implement **digest notifications** for the Manager role: one daily email summary instead of individual event notifications.

---

## 11.8 Stateless Design Principles

The production system is stateless at the application layer:

- **Session storage** — Laravel sessions are stored in Redis or the Database, not on disk, allowing load balancing if needed.
- **File Storage** — If horizontal scaling occurs, migrate local disk storage to S3-compatible object storage (e.g., MinIO or AWS S3).
- **Load Balancing** — Nginx can be placed in front of multiple PHP-FPM instances.

---

## 11.9 Future Background Processing Consideration

If in the future specific functions become bottlenecks, extract them into dedicated Laravel Queue workers (Supervisor):

| Candidate | Why | When to extract |
|-----------|-----|----------------|
| `recommendInspektur` | CPU-bound scoring | When client lag is noticeable |
| Document generation (ST, LHPP draft) | PDF rendering is heavy | When field team is > 10 inspectors |
| Suket expiry calculations | Complex date logic | If Permenaker changes frequently |

Keep the HTTP request cycle thin — push logic to background jobs, not into synchronous PHP scripts.
