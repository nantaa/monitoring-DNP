# 9. Performance Optimization — DNP Riksa Uji Monitor

> **Context**: PT DNP operates at ~30–100 active jobs per month. The system is not high-traffic by internet standards, but response time matters for field use on mobile (often 3G/4G at customer sites) and for Admin/Manager dashboards that aggregate across many jobs.

---

## 9.1 Query Optimization

### Primary indexes (defined in Laravel Migrations)

```php
// database/migrations/xxxx_xx_xx_add_indexes_to_jobs_table.php

// Most common filter: jobs by stage (Kanban Board, per-role dashboards)
$table->index('stage');

-- Finance dashboard: invoice status + due date
CREATE INDEX idx_jobs_billing ON jobs(status_pelunasan, tgl_jatuh_tempo)
  WHERE stage = 7;

-- Inspector dashboard: jobs assigned to specific inspector
CREATE INDEX idx_jobs_inspektur ON jobs USING GIN(inspektur_ids);

-- Marketing filter: jobs by owner
CREATE INDEX idx_jobs_marketing_owner ON jobs(created_by);

-- Stage 6: Suket status per unit — queried as JSONB
CREATE INDEX idx_jobs_units_tracking ON jobs USING GIN(units_tracking);

-- Audit log lookups by job
CREATE INDEX idx_audit_record ON audit_logs(record_id, changed_at DESC);

// SKP expiry check (master data)
$table->index('skp_expired');

// Kalibrasi expiry check (master data)
$table->index('kalibrasi_expired');
```

### Query patterns

**Kanban Board** — fetch all active jobs, no joins needed (denormalised design):
```sql
SELECT * FROM jobs
WHERE stage BETWEEN 1 AND 6
ORDER BY updated_at DESC;
```

**Inspector dashboard** — jobs assigned to current user:
```sql
SELECT * FROM jobs
WHERE inspektur_ids @> '["I001"]'   -- JSONB contains
  AND stage BETWEEN 3 AND 5
ORDER BY tgl_pelaksanaan ASC;
```

**Finance AR aging** — group by bucket in SQL, not JavaScript:
```sql
SELECT
  CASE
    WHEN now() - tgl_invoice <= interval '30 days' THEN '0-30'
    WHEN now() - tgl_invoice <= interval '60 days' THEN '31-60'
    WHEN now() - tgl_invoice <= interval '90 days' THEN '61-90'
    ELSE '>90'
  END AS bucket,
  COUNT(*) AS count,
  SUM(nilai_kontrak) AS total_nilai
FROM jobs
WHERE stage = 7
  AND status_pelunasan != 'paid'
GROUP BY bucket;
```

**Never do**: loading all 500+ jobs and filtering in JavaScript. Add a `WHERE` clause.

---

## 9.2 Caching Strategy

PT DNP's data is low-frequency write (a job might be updated a few times a day). Aggressive caching is safe.

### Laravel Cache (Redis or File)

For heavy dashboard aggregation queries, cache the results for 5-10 minutes.

```php
$dashboardStats = Cache::remember("dashboard_stats_{$role}", 300, function () use ($role) {
    return computeDashboardStats($role);
});
```

### Master data (inspektur, alat, regulasi) — near-static, cache aggressively

These tables change at most once a month. Cache them forever, and clear the cache when a record is updated.

```php
$inspekturList = Cache::rememberForever('inspektur_list', function () {
    return User::where('role', 'inspektur')->get();
});

// Clear cache on update
// Cache::forget('inspektur_list');
```

---

## 9.3 Frontend Performance

### Virtualized list for Job List view

When job count exceeds ~150, the flat list will lag on mobile. Use `@tanstack/react-virtual`:

```jsx
import { useVirtualizer } from '@tanstack/react-virtual';

const rowVirtualizer = useVirtualizer({
  count: filteredJobs.length,
  getScrollElement: () => parentRef.current,
  estimateSize: () => 80,   // row height px
  overscan: 5,
});
```

### useMemo for computed dashboard stats

All dashboard KPIs (overdue count, bottleneck detection, AR aging) should be wrapped in `useMemo` keyed on the `jobs` array. The prototype already does this for some stats — extend the pattern to all role dashboards.

```js
const stats = useMemo(() => computeDashboardStats(jobs, role), [jobs, role]);
```

### Image compression & thumbnails

Stage 4 field photos can be large. Use `Spatie\ImageOptimizer` in Laravel to compress uploaded images before saving them to disk.

```php
use Spatie\ImageOptimizer\OptimizerChainFactory;

$optimizerChain = OptimizerChainFactory::create();
$optimizerChain->optimize(storage_path("app/public/{$path}"));
```

For thumbnail display in the UI, use Laravel Intervention Image to generate smaller versions during upload, saving bandwidth on the list views.

---

## 9.4 Pagination

Server-side pagination is handled elegantly by Laravel's Eloquent:

```php
$jobs = Job::with('inspektur')
           ->when($stage, fn($q) => $q->where('stage', $stage))
           ->when($search, fn($q) => $q->where('klien_nama', 'like', "%{$search}%"))
           ->orderByDesc('updated_at')
           ->paginate(25);

// Passed to Inertia, React uses $jobs->links() to render pagination UI.
```

The Kanban Board can skip pagination — it only shows active jobs (stage 1–6), which at PT DNP's scale is unlikely to exceed 50 simultaneously. If it does, cap each column at 20 visible cards with a "Lihat semua" link.

---

## 9.5 Recommender Engine Performance

The `recommendInspektur()` function iterates over all jobs to check schedule conflicts. At 500+ historical jobs this can lag. Two optimisations:

**Option A (v1.0)**: Pre-filter jobs before calling recommender in PHP — only pass jobs with `stage` 3–5 (active assignments) rather than the full history.

**Option B (v1.1)**: Dispatch the scoring algorithm to a Laravel Queue (`RecommenderJob`). The client checks for the result via polling or WebSockets. This prevents blocking the HTTP request thread.

---

## 9.6 Mobile Performance (INS Role)

Inspectors access the system on mobile in the field, sometimes with poor connectivity. Priority optimisations:

### Progressive Web App (PWA) setup

```js
// vite.config.js — using vite-plugin-pwa
import { VitePWA } from 'vite-plugin-pwa';

VitePWA({
  registerType: 'autoUpdate',
  workbox: {
    globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
    runtimeCaching: [
      {
        urlPattern: /^https:\/\/monitor\.deltanusantara\.co\.id\/storage/,
        handler: 'CacheFirst',
        options: { cacheName: 'job-photos', expiration: { maxEntries: 100 } },
      },
    ],
  },
})
```

### Offline-first for Inspector dashboard

Cache the inspector's assigned jobs on first load. If connectivity drops mid-field, they can still:
- View their Surat Tugas
- Read checklist items
- Complete the checklist offline (sync on reconnect)

Photos queue in IndexedDB when offline, upload when back online. Implement using `idb-keyval` for the queue.

### Camera capture — avoid loading full gallery

The `capture="environment"` attribute on file inputs opens the camera directly, avoiding the need to browse a large photo library:

```jsx
<input
  type="file"
  accept="image/*"
  capture="environment"
  onChange={handlePhotoCapture}
/>
```

---

## 9.7 Bundle Size

Target: initial JS bundle < 200 KB gzipped for acceptable first-load on mobile.

- Vite tree-shakes lucide-react icons automatically — import only what's used
- Avoid `import * as` patterns
- Lazy-load heavy panels with `React.lazy`:

```jsx
const InventoryPage  = React.lazy(() => import('./components/inventory/InventoryPage'));
const JobDetailModal = React.lazy(() => import('./components/jobs/JobDetailModal'));

// Wrap in Suspense:
<Suspense fallback={<LoadingSpinner />}>
  <InventoryPage />
</Suspense>
```

---

## 9.8 Performance Monitoring

For v1.0, a lightweight approach:

- **Laravel Pulse** — Monitor slow endpoints, queue delays, and memory usage.
- **Nginx & PHP-FPM Logs** — Monitor request times and resource limits.
- **Console timing** in dev mode for recommender algorithm:

```js
if (import.meta.env.DEV) {
  console.time('recommendInspektur');
  const result = recommendInspektur(...);
  console.timeEnd('recommendInspektur');
}
```

Target benchmarks:

| Metric | Target |
|--------|--------|
| Kanban Board initial load | < 1.5 s on 4G |
| Job detail modal open | < 300 ms |
| Recommender scoring | < 200 ms |
| Photo upload (5 MB) | < 10 s on 4G |
| Dashboard stats computation | < 100 ms |

---

## 9.9 Database Maintenance

Run these periodically (monthly cron via Laravel Scheduler `routes/console.php`):

```php
use App\Models\Job;
use Illuminate\Support\Facades\Schedule;

// Archive completed jobs older than 2 years
Schedule::call(function () {
    $oldJobs = Job::where('stage', 7)->where('updated_at', '<', now()->subYears(2))->get();
    foreach ($oldJobs as $job) {
        // Move to jobs_archive table...
        $job->delete();
    }
})->monthly();
```
