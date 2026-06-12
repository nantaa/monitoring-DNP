# 6. Testing Strategy — DNP Riksa Uji Monitor

## Testing Philosophy

The DNP Monitor's highest-risk areas are:
1. **Stage gate logic** — wrong gate = jobs advance without meeting compliance requirements
2. **RBAC enforcement** — wrong permission = unauthorized data access or modification
3. **Inspector recommendation algorithm** — wrong output = unqualified inspector assigned
4. **Suket validity calculation** — wrong date = non-compliant Suket sent to Disnaker
5. **Audit log integrity** — missing log = Kemnaker audit failure

Tests must cover these five areas exhaustively before any production deployment.

---

## Test Stack

| Layer | Tool |
|-------|------|
| Unit tests (logic) | Vitest |
| Component tests | React Testing Library + Vitest |
| API / Integration tests | Pest PHP + Laravel Testing database |
| E2E tests | Playwright |
| Load testing | k6 |
| Coverage reporting | Istanbul (via Vitest) |

---

## Coverage Targets

| Area | Target |
|------|--------|
| Stage gate functions | 100% |
| RBAC/RLS policies | 100% |
| Recommendation algorithm | 95% |
| Suket validity calculation | 100% |
| Invoice/AR calculations | 95% |
| UI components (critical) | 80% |
| Overall codebase | 75% |

---

## Unit Tests

### 1. Stage Gate Logic

```typescript
// tests/unit/stageGate.test.ts
import { describe, it, expect } from 'vitest';
import { canAdvanceStage } from '@/lib/stageGate';

describe('Stage 2 → 3 gate', () => {
  it('blocks advance when mandatory checklist items are incomplete', () => {
    const job = {
      stage: 2,
      pesawat: 'LIFT',
      disnaker_tujuan: 'Disnaker Jabar',
      verify_checklist: { po: true, permohonan: true, kuasa: false }
    };
    const result = canAdvanceStage(job, 'admin');
    expect(result.allowed).toBe(false);
    expect(result.unmetConditions).toContain('Surat Kuasa belum dicentang');
  });

  it('allows advance when all required items complete', () => {
    const job = buildCompleteStage2Job('LIFT', 'Disnaker Jabar');
    expect(canAdvanceStage(job, 'admin').allowed).toBe(true);
  });

  it('requires Pengesahan Gambar for Disnaker Jateng', () => {
    const job = buildCompleteStage2Job('LIFT', 'Disnaker Jateng');
    job.verify_checklist.pengesahan = false;
    const result = canAdvanceStage(job, 'admin');
    expect(result.allowed).toBe(false);
    expect(result.unmetConditions).toContain('Pengesahan Gambar Kemnaker wajib untuk Disnaker Jateng');
  });
});

describe('Stage 5 → 6 gate', () => {
  it('requires lhpp_locked = true before advancing', () => {
    const job = { stage: 5, lhpp_locked: false };
    expect(canAdvanceStage(job, 'manager').allowed).toBe(false);
  });

  it('allows advance once LHPP is locked by manager', () => {
    const job = { stage: 5, lhpp_locked: true };
    expect(canAdvanceStage(job, 'manager').allowed).toBe(true);
  });
});
```

---

### 2. Inspector Recommendation Algorithm

```typescript
// tests/unit/recommendation.test.ts
import { recommendInspektur } from '@/lib/recommendation';

describe('Hard filter phase', () => {
  it('eliminates inspektur with expired SKP', () => {
    const inspektur = buildInspektur({ skp_expired: '2025-01-01' }); // past
    const result = recommendInspektur([inspektur], buildJob('PV'), [], '2026-07-01', '2026-07-02');
    expect(result.qualified).toHaveLength(0);
    expect(result.disqualified[0].reason).toMatch(/SKP.*expired/);
  });

  it('eliminates inspektur with mismatched spesialisasi', () => {
    const inspektur = buildInspektur({ spesialisasi: ['Kebakaran'] });
    const job = buildJob('PV'); // requires PUBT or Umum
    const result = recommendInspektur([inspektur], job, [], '2026-07-01', '2026-07-02');
    expect(result.disqualified[0].reason).toMatch(/spesialisasi/i);
  });

  it('eliminates inspektur with schedule conflict', () => {
    const activeJob = buildJob('LIFT', { tgl_pelaksanaan: '2026-07-01' });
    const insp = buildInspektur({ id: 'I001' });
    activeJob.assigned_inspektur = ['I001'];
    const result = recommendInspektur([insp], buildJob('PV'), [activeJob], '2026-07-01', '2026-07-02');
    expect(result.disqualified[0].reason).toMatch(/konflik jadwal/i);
  });
});

describe('Soft scoring', () => {
  it('awards full spesialisasi points for primary match', () => {
    const insp = buildInspektur({ spesialisasi: ['PUBT'] });
    const result = recommendInspektur([insp], buildJob('PV'), [], '2026-07-10', '2026-07-11');
    expect(result.qualified[0].breakdown.spesialisasi_match).toBe(30);
  });

  it('penalizes overloaded inspektur', () => {
    const insp = buildInspektur({ id: 'I001' });
    const activeJobs = Array(4).fill(null).map(() => buildJobWithInspektur('I001'));
    const result = recommendInspektur([insp], buildJob('PV'), activeJobs, '2026-07-10', '2026-07-11');
    const scored = result.qualified[0];
    expect(scored.bonuses).not.toContain(expect.stringMatching(/overload/));
    // penalty -10 for ≥4 jobs
    expect(scored.score).toBeLessThan(50);
  });
});
```

---

### 3. Suket Validity Calculation

```typescript
// tests/unit/suketValidity.test.ts
import { calculateSuketExpiry } from '@/lib/suket';

const cases = [
  { pesawat: 'LIFT',    jenis: null,                 terbit: '2026-06-01', expected: '2027-06-01' },
  { pesawat: 'PV',      jenis: 'Pemeriksaan Luar',   terbit: '2026-06-01', expected: '2028-06-01' },
  { pesawat: 'PV',      jenis: 'Hydrotest',          terbit: '2026-06-01', expected: '2031-06-01' },
  { pesawat: 'PTP',     jenis: null,                 terbit: '2026-06-01', expected: '2028-06-01' },
  { pesawat: 'BOILER',  jenis: null,                 terbit: '2026-06-01', expected: '2027-06-01' },
];

cases.forEach(({ pesawat, jenis, terbit, expected }) => {
  it(`${pesawat} ${jenis ?? ''}: terbit ${terbit} → expires ${expected}`, () => {
    expect(calculateSuketExpiry(pesawat, jenis, terbit)).toBe(expected);
  });
});
```

---

## Integration Tests

Integration tests run against a local PostgreSQL database configured for testing in `phpunit.xml`.

```php
// tests/Feature/JobWorkflowTest.php
use App\Models\Job;
use App\Models\User;

test('Marketing creates job at Stage 1', function () {
    $mktUser = User::factory()->create(['role' => 'marketing']);
    
    $response = $this->actingAs($mktUser)->postJson('/api/v1/jobs', [
        'klien' => 'PT Test',
        'pesawat' => 'LIFT',
        'units' => 2,
        'nilai' => 50000000,
        'lokasi' => 'Jakarta'
    ]);
    
    $response->assertStatus(201);
    expect($response->json('data.stage'))->toBe(1);
});

test('Inspektur cannot see nilai kontrak', function () {
    $inspektur = User::factory()->create(['role' => 'inspektur']);
    $job = Job::factory()->create();
    
    $this->actingAs($inspektur)->getJson('/api/v1/jobs/' . $job->id)
        ->assertJsonMissing(['nilai']);
});

test('Finance-only can close Stage 7', function () {
    $adminUser = User::factory()->create(['role' => 'admin']);
    $job = Job::factory()->stage(7)->create();
    
    $this->actingAs($adminUser)->postJson("/api/v1/jobs/{$job->id}/complete")
        ->assertForbidden();
});

test('Audit log records stage advance', function () {
    $adminUser = User::factory()->create(['role' => 'admin']);
    $job = Job::factory()->stage(2)->create();
    
    $this->actingAs($adminUser)->postJson("/api/v1/jobs/{$job->id}/advance");
    
    $this->assertDatabaseHas('audit_logs', [
        'table_name' => 'jobs',
        'record_id' => $job->id,
        'operation' => 'UPDATE'
    ]);
});
```

---

## E2E Tests (Playwright)

```typescript
// tests/e2e/kanban.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Kanban Board', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('[data-testid=email]', 'admin@dnp.co.id');
    await page.fill('[data-testid=password]', process.env.TEST_ADMIN_PASS!);
    await page.click('[data-testid=login-btn]');
    await expect(page).toHaveURL('/dashboard');
  });

  test('Job card shows H-5 badge when unreported', async ({ page }) => {
    await page.goto('/kanban');
    const card = page.locator('[data-job-id="demo-3"]');
    await expect(card.locator('[data-testid=h5-badge]')).toBeVisible();
  });

  test('Cannot advance Stage 2 job without complete checklist', async ({ page }) => {
    await page.click('[data-job-id="demo-stage2-incomplete"]');
    await page.click('[data-testid=advance-btn]');
    await expect(page.locator('[data-testid=gate-error]')).toContainText('Surat Kuasa');
  });
});
```

---

## Test Data Factories

```typescript
// tests/factories/job.ts
export function buildJob(pesawat: string, overrides = {}) {
  return {
    id: crypto.randomUUID(),
    kode: 'TEST/2026/9999',
    stage: 1,
    klien: 'PT Test Client',
    lokasi: 'Jakarta',
    pesawat,
    units: 2,
    nilai: 50_000_000,
    disnaker_tujuan: 'Disnaker DKI Jakarta',
    created_at: new Date().toISOString(),
    ...overrides,
  };
}

export function buildInspektur(overrides = {}) {
  return {
    id: 'I-TEST',
    nama: 'Test Inspektur',
    skp_expired: '2028-01-01',
    spesialisasi: ['PUBT', 'Umum'],
    senior_level: 3,
    domisili: 'Bekasi',
    active: true,
    joined_year: 2019,
    ...overrides,
  };
}
```

---

## CI Test Pipeline

```yaml
# .github/workflows/test.yml
name: Test
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: test
          POSTGRES_DB: testing
        ports:
          - 5432:5432
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
      - run: npm ci
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan migrate --env=testing
      - run: npm run test:unit -- --coverage
      - run: php artisan test
      - run: npx playwright install --with-deps
      - run: npm run test:e2e
      - name: Check coverage thresholds
        run: npx vitest run --coverage --coverage.thresholds.lines=75
```

---

## Test Environments

| Env | DB | Auth | Purpose |
|-----|----|----|---------|
| Local | Local PostgreSQL | Local users | Developer unit + integration tests |
| Staging | Staging PostgreSQL | Laravel Auth | E2E, UAT, pre-release validation |
| Production | Production PostgreSQL | Real | No tests run here — deploy only after staging passes |

**Rule:** Staging DB is a copy of production schema with anonymized demo data. Never use real PO numbers, client names, or nilai in test data.
