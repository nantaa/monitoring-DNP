# 3. API Specification — DNP Riksa Uji Monitor

## Base URL

```
Production: https://monitor.deltanusantara.co.id/api/v1
Staging:    https://staging-monitor.deltanusantara.co.id/api/v1
```

The frontend uses **Inertia.js** for most page loads and form submissions, but standard JSON API endpoints are available for mobile/PWA integrations and background jobs. This spec covers the Laravel JSON API endpoints.

---

## Authentication

All requests from the React frontend use Laravel Sanctum's cookie-based session authentication with CSRF protection. External API requests require a Bearer token:
```
Authorization: Bearer <sanctum_token>
```
Tokens are validated by Laravel Sanctum middleware. Authorization is handled by Laravel Policies.

---

## Standard Response Envelope

**Success:**
```json
{
  "data": { ... },
  "meta": { "count": 42, "page": 1, "per_page": 20 }
}
```

**Error:**
```json
{
  "error": {
    "code": "DNP_ERR_401",
    "message": "Unauthorized: insufficient role",
    "details": null
  }
}
```

---

## Rate Limits

| Tier | Limit |
|------|-------|
| Standard (all roles) | 100 req/min per user |
| File upload | 20 req/min per user |
| Complex Endpoints | 50 req/min per user |

---

## Jobs

### List Jobs
`GET /jobs`

Returns jobs filtered by Laravel Policies (INS sees only assigned jobs, MKT sees own jobs, MGR/ADM/FIN see all).

**Query parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `stage` | `int` | Filter by stage (1-7) |
| `pesawat` | `string` | Filter by pesawat code |
| `disnaker_tujuan` | `string` | Filter by Disnaker |
| `klien` | `string` | ILIKE search on klien name |
| `page` | `int` | Page number (default: 1) |
| `per_page` | `int` | Results per page (default: 20, max: 100) |
| `sort` | `string` | `created_at.desc` (default), `tgl_pelaksanaan.asc`, etc. |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "kode": "DNP/2026/0001",
      "stage": 3,
      "klien": "PT Equinix Indonesia",
      "pesawat": "FIRE",
      "units": 4,
      "nilai": 85000000,
      "tgl_pelaksanaan": "2026-06-20",
      "h5_reported": false,
      "assigned_inspektur": ["uuid1"],
      "created_at": "2026-06-01T08:00:00Z"
    }
  ],
  "meta": { "count": 8, "page": 1, "per_page": 20 }
}
```

---

### Get Job Detail
`GET /jobs/:id`

Returns full job record including nested documents, photos, evaluation, suket units, and invoice.

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "kode": "DNP/2026/0001",
    "stage": 6,
    ...all job fields...,
    "documents": [...],
    "photos": [...],
    "evaluation": [...],
    "suket_units": [...],
    "invoice": {...},
    "stage_history": [...]
  }
}
```

---

### Create Job
`POST /jobs`
**Roles allowed:** `marketing`, `manager`

**Request body:**
```json
{
  "klien": "PT Astra International",
  "lokasi": "Karawang, Jawa Barat",
  "pic_klien": "Pak Budi (HSE)",
  "pic_klien_phone": "0812-3456-7890",
  "pesawat": "LIFT",
  "units": 6,
  "nilai": 120000000,
  "no_po": "PO/AST/2026/0234",
  "tgl_po": "2026-06-10",
  "disnaker_tujuan": "Disnaker Jabar"
}
```

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "kode": "DNP/2026/0009",
    "stage": 1,
    ...
  }
}
```

**Errors:**
- `422` — Missing required fields
- `403` — Caller is not `marketing` or `manager`

---

### Update Job
`PATCH /jobs/:id`
**Roles allowed:** Depends on current stage and field (enforced by Laravel Policies).

```json
{
  "verify_checklist": { "po": true, "permohonan": true, "kuasa": true, ... },
  "verify_notes": "Semua dokumen lengkap dan valid"
}
```

**Response 200:** Updated job object.

**Errors:**
- `403` — Role not allowed to edit this field or this stage
- `409` — Conflict (e.g., trying to edit locked LHPP field)

---

### Advance Stage
`POST /jobs/:id/advance`
**Roles allowed:** Varies by stage (see Stage Permissions matrix in `4_AUTHENTICATION_SECURITY.md`)

**Request body:**
```json
{
  "notes": "Semua dokumen verified, lanjut penjadwalan"
}
```

**Gate logic checked server-side before advancing:**
- Stage 1→2: no gate (MKT creates complete job)
- Stage 2→3: all required checklist items `true`
- Stage 3→4: `tgl_pelaksanaan` set, at least 1 inspektur assigned, `surat_tugas_no` generated
- Stage 4→5: `field_checklist` all critical items checked, ≥1 photo per category uploaded
- Stage 5→6: `lhpp_locked = true` (MGR approved)
- Stage 6→7: all `job_suket_units.status = 'Terbit'`
- Stage 7 close: called via `/jobs/:id/complete`

**Response 200:** Updated job with new stage.
**Response 422:** Gate validation failed, returns unmet conditions.

---

### Complete Job (Stage 7)
`POST /jobs/:id/complete`
**Roles allowed:** `finance` only

**Response 200:** Job with `completed_at` set.

---

## Inspector Recommendation

### Get Recommendations
`POST /api/v1/recommend-inspektur`
**Roles allowed:** `admin`, `manager`

Runs the two-phase algorithm (hard filter → soft scoring).

**Request body:**
```json
{
  "job_id": "uuid",
  "tgl_pelaksanaan": "2026-06-25",
  "tgl_selesai": "2026-06-26",
  "pesawat": "PV",
  "disnaker_tujuan": "Disnaker DKI Jakarta",
  "klien": "PT Hotel Indonesia Kempinski"
}
```

**Response 200:**
```json
{
  "data": {
    "qualified": [
      {
        "inspektur_id": "I001",
        "nama": "Terzha R. Perdanawan",
        "score": 87,
        "breakdown": {
          "spesialisasi_match": 30,
          "workload": 20,
          "pengalaman_klien": 10,
          "pengalaman_pesawat": 12,
          "availability": 15
        },
        "bonuses": ["SKP valid ≥1 tahun: +5"],
        "issues": [],
        "workload_count": 1,
        "skp_expired": "2027-04-15"
      }
    ],
    "disqualified": [
      {
        "inspektur_id": "I006",
        "nama": "Dewi Anggraini",
        "reason": "Spesialisasi tidak match: PUBT diperlukan, dimiliki: Kebakaran, Umum"
      }
    ]
  }
}
```

---

## Documents

### Upload Document
`POST /jobs/:id/documents`
**Roles allowed:** All roles (scoped to their stage)
**Content-Type:** `multipart/form-data`

```
stage=3
doc_type=Surat Tugas
file=<binary>
```

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "doc_type": "Surat Tugas",
    "file_name": "ST-DNP-2026-0001.pdf",
    "storage_path": "jobs/uuid/stage3/ST-DNP-2026-0001.pdf",
    "uploaded_at": "2026-06-12T10:00:00Z"
  }
}
```

**Constraints:**
- Max file size: 20 MB per file
- Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`, `image/webp`

---

### Upload Field Photo
`POST /jobs/:id/photos`
**Roles allowed:** `inspektur`, `manager`

```
category=Nameplate
caption=Nameplate Boiler SN BLR-001
file=<binary>
```

**Response 201:** Photo record with `storage_path`.

---

### Generate Surat Tugas
`POST /api/v1/generate-surat-tugas`
**Roles allowed:** `admin`, `manager`

Generates printable Surat Tugas PDF and saves to Storage.

**Request body:**
```json
{ "job_id": "uuid" }
```

**Response 200:**
```json
{
  "data": {
    "surat_tugas_no": "ST/DNP/2026/0047",
    "file_url": "https://storage.supabase.co/.../ST-DNP-2026-0047.pdf",
    "generated_at": "2026-06-12T09:30:00Z"
  }
}
```

---

### Generate Field Document Package
`POST /api/v1/generate-field-package`
**Roles allowed:** `admin`, `manager`

Generates 5-page PDF bundle (Cover + Surat Tugas + Checklist + BAP + Dokumen Legal).

**Request body:**
```json
{ "job_id": "uuid" }
```

**Response 200:**
```json
{
  "data": {
    "file_url": "https://storage.supabase.co/.../FieldPack-DNP-2026-0001.pdf",
    "pages": 5,
    "generated_at": "2026-06-12T09:35:00Z"
  }
}
```

---

## Suket Units

### Update Suket Unit Status
`PATCH /job-suket-units/:id`
**Roles allowed:** `manager`, `admin`

```json
{
  "status": "Terbit",
  "no_suket": "KEP/DKI/2026/UJI/0123",
  "terbit_at": "2026-06-10",
  "jenis_pemeriksaan": "Pemeriksaan Luar"
}
```

System auto-calculates `masa_berlaku_bulan` and `suket_expired` based on `pesawat` type and `jenis_pemeriksaan`.

**Response 200:** Updated suket unit. If all units for job are now `Terbit`, `jobs.suket_all_terbit` is set to `true` automatically via trigger.

---

### Batch Submit Suket
`POST /api/v1/batch-submit-suket`
**Roles allowed:** `manager`

Marks all `Pending` units for a job as `Di Disnaker` in one operation.

**Request body:**
```json
{
  "job_id": "uuid",
  "submitted_at": "2026-06-12"
}
```

---

## Invoices

### Create Invoice
`POST /jobs/:id/invoices`
**Roles allowed:** `finance` only

```json
{
  "no_invoice": "INV/DNP/2026/0089",
  "tgl_invoice": "2026-06-12",
  "top_days": 30,
  "nilai_invoice": 85000000
}
```

**Response 201:** Invoice record. `jatuh_tempo` is auto-calculated.

---

### Update Invoice
`PATCH /invoices/:id`
**Roles allowed:** `finance` only

```json
{
  "status": "paid",
  "tgl_lunas": "2026-07-05",
  "nilai_lunas": 85000000
}
```

---

## Master Data

### Alat Uji
`GET /alat-master` — list all (filtered by `kalibrasi_expired > now()` for assignment)
`PATCH /alat-master/:id` — update status (`admin`, `manager`)

### Inspektur
`GET /inspektur-master` — list all
`PATCH /inspektur-master/:id` — update SKP or active status (`manager`)

### App Config
`GET /app-config` — read all config values (`manager`, `admin`)
`PATCH /app-config/:key` — update config (`manager` only)

---

## Notifications

### Send WhatsApp Notification
`POST /api/v1/notify`
**Roles allowed:** Server-side only (called by triggers/cron)

```json
{
  "type": "H5_REMINDER",
  "job_id": "uuid",
  "recipients": ["admin_phone", "klien_phone"]
}
```

**Notification types:**
| Type | Trigger | Recipients |
|------|---------|-----------|
| `H5_REMINDER` | Cron: 5 days before `tgl_pelaksanaan` | ADM, INS assigned |
| `SUKET_TERBIT` | Suket unit status → Terbit | MKT owner, FIN |
| `INVOICE_SENT` | Invoice status → sent | FIN, MGR |
| `INVOICE_OVERDUE` | Cron: daily, `jatuh_tempo < today` | FIN, MGR |
| `LHPP_REVIEW_NEEDED` | INS submits LHPP | MGR |
| `PEER_REVIEW_DONE` | MGR approves/rejects | INS assigned |

---

## Dashboard Analytics

### KPI Summary
`GET /api/v1/dashboard-kpi?role=manager`

Returns role-specific KPI data (total pipeline, overdue count, etc.).

### AR Aging
`GET /api/v1/ar-aging`
**Roles allowed:** `finance`, `manager`

Returns bucket counts and values: `0-30`, `31-60`, `61-90`, `>90` days.

### Bottleneck Analysis
`GET /api/v1/bottleneck`
**Roles allowed:** `manager`

Returns job count per stage with SLA breach flags.

---

## HTTP Status Codes Used

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | Deleted / No content |
| 400 | Bad request (malformed body) |
| 401 | Missing or invalid JWT |
| 403 | Authenticated but insufficient role/permission |
| 404 | Record not found |
| 409 | Conflict (e.g., duplicate kode, locked record edit) |
| 422 | Validation error / gate logic unmet |
| 429 | Rate limit exceeded |
| 500 | Internal server error |
