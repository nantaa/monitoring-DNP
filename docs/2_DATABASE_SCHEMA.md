# 2. Database Schema — DNP Riksa Uji Monitor

## Database: PostgreSQL 15

All tables live in the `public` schema. Authorization is enforced at the application level via **Laravel Policies**. The `users` table is managed by Laravel Auth.

---

## Entity Relationship Overview

```
users (Laravel Auth)
    │
    ▼
user_profiles ──────────────────────────────────────┐
    │                                                │
    ├──▶ jobs ──────────────────────────────────────┤
    │       │                                        │
    │       ├──▶ job_documents                      │
    │       ├──▶ job_stage_history                  │
    │       ├──▶ job_photos                         │
    │       ├──▶ job_evaluation (per unit)          │
    │       ├──▶ job_suket_units (per unit Stage 6) │
    │       └──▶ job_invoices                       │
    │                                                │
    ├──▶ inspektur_master                           │
    ├──▶ alat_master                                │
    ├──▶ pjk3_certificates                          │
    └──▶ audit_log (write-only, trigger-fed)        │
                                                     │
app_config ──────────────────────────────────────────┘
```

---

## Tables

### `user_profiles`

Maps `auth.users` to application roles and metadata.

```sql
CREATE TABLE user_profiles (
  id            UUID PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  full_name     TEXT NOT NULL,
  role          TEXT NOT NULL CHECK (role IN ('marketing','admin','inspektur','manager','finance')),
  phone         TEXT,
  domisili      TEXT,
  active        BOOLEAN NOT NULL DEFAULT true,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_user_profiles_role ON user_profiles(role);
```

---

### `jobs`

Core job record. One row per PO/SPK from a client.

```sql
CREATE TABLE jobs (
  id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  kode                TEXT UNIQUE NOT NULL,            -- e.g. DNP/2026/0001
  stage               SMALLINT NOT NULL DEFAULT 1 CHECK (stage BETWEEN 1 AND 7),
  stage_started_at    DATE,

  -- Stage 1: Marketing & PO
  klien               TEXT NOT NULL,
  lokasi              TEXT NOT NULL,
  owner_marketing     UUID NOT NULL REFERENCES user_profiles(id),
  pic_klien           TEXT,
  pic_klien_phone     TEXT,
  pesawat             TEXT NOT NULL,                   -- LIFT|ESC|PAPA|FIRE|LISTRIK|BOILER|PV|PTP
  units               SMALLINT NOT NULL DEFAULT 1,
  nilai               BIGINT NOT NULL,                 -- Rupiah, no decimal
  no_po               TEXT,
  tgl_po              DATE,
  disnaker_tujuan     TEXT,

  -- Stage 2: Dokumen
  verify_checklist    JSONB,                           -- {po:true, permohonan:true, ...}
  verify_notes        TEXT,
  verified_by         UUID REFERENCES user_profiles(id),
  verified_at         TIMESTAMPTZ,

  -- Stage 3: Penjadwalan
  tgl_pelaksanaan     DATE,
  tgl_h5              DATE GENERATED ALWAYS AS (tgl_pelaksanaan - INTERVAL '5 days') STORED,
  h5_reported         BOOLEAN DEFAULT false,
  h5_reported_at      TIMESTAMPTZ,
  assigned_inspektur  UUID[] DEFAULT '{}',             -- array of user_profile IDs
  assigned_alat       TEXT[] DEFAULT '{}',             -- array of alat_master IDs
  surat_tugas_no      TEXT,
  surat_tugas_at      TIMESTAMPTZ,
  scheduled_by        UUID REFERENCES user_profiles(id),

  -- Stage 4: Pelaksanaan
  field_checklist     JSONB,                           -- {nameplate:true, visual:true, ...}
  field_completed_at  TIMESTAMPTZ,

  -- Stage 5: LHPP & Peer Review
  lhpp_submitted_at   TIMESTAMPTZ,
  lhpp_submitted_by   UUID REFERENCES user_profiles(id),
  review_status       TEXT CHECK (review_status IN ('pending','rejected','approved')),
  review_notes        TEXT,
  reviewed_by         UUID REFERENCES user_profiles(id),
  reviewed_at         TIMESTAMPTZ,
  lhpp_locked         BOOLEAN DEFAULT false,
  lhpp_locked_at      TIMESTAMPTZ,

  -- Stage 6: Suket (aggregate; per-unit in job_suket_units)
  suket_batch_submitted_at  TIMESTAMPTZ,
  suket_all_terbit          BOOLEAN DEFAULT false,

  -- Stage 7: Penagihan
  completed_at        TIMESTAMPTZ,

  -- Metadata
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_jobs_stage         ON jobs(stage);
CREATE INDEX idx_jobs_pesawat        ON jobs(pesawat);
CREATE INDEX idx_jobs_klien          ON jobs(klien);
CREATE INDEX idx_jobs_owner_mkt      ON jobs(owner_marketing);
CREATE INDEX idx_jobs_tgl_pelaksanaan ON jobs(tgl_pelaksanaan);
CREATE INDEX idx_jobs_disnaker       ON jobs(disnaker_tujuan);
```

---

### `job_documents`

File attachments for a job at any stage.

```sql
CREATE TABLE job_documents (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id      UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  stage       SMALLINT NOT NULL,
  doc_type    TEXT NOT NULL,      -- 'PO/SPK', 'Surat Permohonan', 'LHPP', etc.
  file_name   TEXT NOT NULL,
  storage_path TEXT NOT NULL,     -- Supabase Storage bucket path
  file_size   INTEGER,            -- bytes
  uploaded_by UUID REFERENCES user_profiles(id),
  uploaded_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_job_documents_job ON job_documents(job_id);
CREATE INDEX idx_job_documents_stage ON job_documents(job_id, stage);
```

---

### `job_photos`

Field photos uploaded by INS during Stage 4. Stored in Supabase Storage, metadata here.

```sql
CREATE TABLE job_photos (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id       UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  category     TEXT NOT NULL CHECK (category IN (
                  'Nameplate','Kondisi Fisik','Hasil Pengukuran',
                  'Alat Pengaman','APD','BAP Scan','Instalasi','Lainnya'
               )),
  caption      TEXT,
  storage_path TEXT NOT NULL,
  uploaded_by  UUID REFERENCES user_profiles(id),
  uploaded_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_job_photos_job ON job_photos(job_id);
```

---

### `job_evaluation`

Per-unit technical evaluation filled by INS at Stage 5.

```sql
CREATE TABLE job_evaluation (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id       UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  unit_no      SMALLINT NOT NULL,                 -- 1-based index
  unit_label   TEXT,                              -- e.g. "Unit 1 - SN: BLR-001"
  status       TEXT CHECK (status IN ('LAIK','LAIK_BERSYARAT','TIDAK_LAIK')),
  catatan      TEXT,
  temuan       JSONB DEFAULT '[]',                -- array of finding objects
  locked       BOOLEAN DEFAULT false,             -- set true on MGR approve
  locked_at    TIMESTAMPTZ,
  UNIQUE (job_id, unit_no)
);

CREATE INDEX idx_job_eval_job ON job_evaluation(job_id);
```

---

### `job_suket_units`

Per-unit Suket tracking at Stage 6. Decoupled from job_evaluation to allow independent status.

```sql
CREATE TABLE job_suket_units (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  unit_no         SMALLINT NOT NULL,
  unit_label      TEXT,
  status          TEXT NOT NULL DEFAULT 'Pending'
                  CHECK (status IN ('Pending','Di Disnaker','Terbit','Ditolak')),
  jenis_pemeriksaan TEXT,           -- 'Pemeriksaan Luar' | 'Hydrotest' (PV only)
  submitted_at    DATE,
  terbit_at       DATE,
  no_suket        TEXT,
  masa_berlaku_bulan SMALLINT,      -- system-calculated per Permenaker
  suket_expired   DATE,             -- terbit_at + masa_berlaku_bulan
  reject_reason   TEXT,
  resubmit_count  SMALLINT DEFAULT 0,
  updated_by      UUID REFERENCES user_profiles(id),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (job_id, unit_no)
);

CREATE INDEX idx_job_suket_job    ON job_suket_units(job_id);
CREATE INDEX idx_job_suket_status ON job_suket_units(status);
CREATE INDEX idx_job_suket_expiry ON job_suket_units(suket_expired);  -- for expiry alerts
```

---

### `job_invoices`

Invoice and AR tracking at Stage 7.

```sql
CREATE TABLE job_invoices (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  no_invoice      TEXT UNIQUE,
  tgl_invoice     DATE,
  top_days        SMALLINT DEFAULT 30,
  jatuh_tempo     DATE GENERATED ALWAYS AS (tgl_invoice + top_days * INTERVAL '1 day') STORED,
  nilai_invoice   BIGINT NOT NULL,
  status          TEXT NOT NULL DEFAULT 'draft'
                  CHECK (status IN ('draft','sent','partial','paid','overdue')),
  tgl_lunas       DATE,
  nilai_lunas     BIGINT DEFAULT 0,
  kurir_resi      TEXT,
  kurir_sent_at   DATE,
  tanda_terima_kembali BOOLEAN DEFAULT false,
  tanda_terima_at DATE,
  notes           TEXT,
  created_by      UUID REFERENCES user_profiles(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_invoices_job    ON job_invoices(job_id);
CREATE INDEX idx_invoices_status ON job_invoices(status);
CREATE INDEX idx_invoices_jatuh  ON job_invoices(jatuh_tempo);
```

---

### `job_stage_history`

Immutable log of stage transitions.

```sql
CREATE TABLE job_stage_history (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id      UUID NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
  from_stage  SMALLINT,
  to_stage    SMALLINT NOT NULL,
  moved_by    UUID REFERENCES user_profiles(id),
  moved_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  notes       TEXT
);

CREATE INDEX idx_stage_history_job ON job_stage_history(job_id);
```

---

### `inspektur_master`

Ahli K3 personnel with SKP data. Linked to `user_profiles` if they have system login.

```sql
CREATE TABLE inspektur_master (
  id              TEXT PRIMARY KEY,               -- e.g. 'I001'
  user_id         UUID REFERENCES user_profiles(id),
  nama            TEXT NOT NULL,
  no_skp          TEXT NOT NULL,
  skp_expired     DATE NOT NULL,
  spesialisasi    TEXT[] NOT NULL,                -- ['Umum','PUBT'] etc.
  phone           TEXT,
  domisili        TEXT,
  senior_level    SMALLINT DEFAULT 1,             -- years of experience tier
  joined_year     SMALLINT,
  active          BOOLEAN DEFAULT true
);

CREATE INDEX idx_inspektur_skp_expiry ON inspektur_master(skp_expired);
CREATE INDEX idx_inspektur_spesialisasi ON inspektur_master USING GIN(spesialisasi);
```

---

### `alat_master`

Calibrated test equipment inventory.

```sql
CREATE TABLE alat_master (
  id                  TEXT PRIMARY KEY,           -- e.g. 'A001'
  nama                TEXT NOT NULL,
  merk                TEXT,
  serial              TEXT UNIQUE,
  kategori            TEXT[] NOT NULL,            -- ['PUBT','PV','BOILER']
  kalibrasi_terakhir  DATE,
  kalibrasi_expired   DATE NOT NULL,
  lab_kalibrasi       TEXT,                       -- KAN-LK number
  status              TEXT DEFAULT 'tersedia'
                      CHECK (status IN ('tersedia','sedang dipakai','rusak','dikalibrasi'))
);

CREATE INDEX idx_alat_kalibrasi_expiry ON alat_master(kalibrasi_expired);
CREATE INDEX idx_alat_kategori ON alat_master USING GIN(kategori);
```

---

### `pjk3_certificates`

Company-level PJK3 certificates from Kemnaker.

```sql
CREATE TABLE pjk3_certificates (
  id        TEXT PRIMARY KEY,
  nama      TEXT NOT NULL,
  no_sk     TEXT UNIQUE NOT NULL,
  terbit    DATE NOT NULL,
  expired   DATE NOT NULL,
  kategori  TEXT NOT NULL,                        -- 'umum','PAA','Listrik','Kebakaran'
  file_path TEXT
);

CREATE INDEX idx_pjk3_expiry ON pjk3_certificates(expired);
```

---

### `app_config`

Runtime configuration editable by Manager via Settings UI. Avoids hardcoded constants.

```sql
CREATE TABLE app_config (
  key         TEXT PRIMARY KEY,
  value       JSONB NOT NULL,
  description TEXT,
  updated_by  UUID REFERENCES user_profiles(id),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed entries:
-- 'marketing_targets'   → {"Terzha R. Perdanawan": 150000000, ...}
-- 'default_top_days'    → 30
-- 'sla_per_stage'       → {2: 1, 3: 1, 5: 3, 6: 60, 7: 1}
-- 'recommendation_weights' → {spesialisasi: 30, workload: 25, ...}
```

---

### `audit_log`

Write-only. Populated exclusively by Laravel Observers. No application logic provides UPDATE or DELETE routes.

```sql
CREATE TABLE audit_log (
  id          BIGSERIAL PRIMARY KEY,
  table_name  TEXT NOT NULL,
  record_id   TEXT NOT NULL,
  operation   TEXT NOT NULL CHECK (operation IN ('INSERT','UPDATE','DELETE')),
  old_data    JSONB,
  new_data    JSONB,
  changed_by  UUID,                               -- auth.uid() at time of change
  changed_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_audit_table_record ON audit_log(table_name, record_id);
CREATE INDEX idx_audit_changed_at   ON audit_log(changed_at DESC);
```

**Implementation example via Laravel Observers (e.g. `JobObserver`):**

```php
class JobObserver
{
    public function updated(Job $job)
    {
        AuditLog::create([
            'table_name' => 'jobs',
            'record_id'  => $job->id,
            'operation'  => 'UPDATE',
            'old_data'   => json_encode($job->getOriginal()),
            'new_data'   => json_encode($job->getAttributes()),
            'changed_by' => auth()->id(),
        ]);
    }
}
```

---

## Suket Validity Reference (Permenaker)

Used by system to auto-calculate `suket_expired`:

| Pesawat Code | Months | Regulation |
|---|---|---|
| LIFT | 12 | Permenaker No. 6/2017 |
| ESC | 12 | Permenaker No. 6/2017 |
| PAPA | 12 | Permenaker No. 8/2020 |
| FIRE | 12 | Kepmenaker No. 186/1999 |
| LISTRIK | 12 | Permenaker No. 12/2015 |
| BOILER | 12 | Permenaker No. 1/1988 |
| PV (pemeriksaan luar) | 24 | Permenaker No. 37/2016 |
| PV (hydrotest) | 60 | Permenaker No. 37/2016 |
| PTP | 24 | Permenaker No. 37/2016 |

---

## Migration Strategy (Prototype → Production)

1. Export prototype localStorage jobs (JSON) to CSV/JSON file.
2. Write one-time Node.js migration script: map prototype fields to production schema.
3. Validate against constraint checks before insert.
4. Seed `inspektur_master`, `alat_master`, `pjk3_certificates` from constants in prototype.
5. Run migration in staging Supabase project, validate with sample queries.
6. Promote to production after UAT sign-off.
