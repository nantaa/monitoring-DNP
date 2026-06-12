# 1. Architecture — DNP Riksa Uji Monitor

## Overview

DNP Riksa Uji Monitor is an internal web application for **PT Delta Nusantara Persada (PT DNP)**, a PJK3 (Perusahaan Jasa K3) company registered at Kemnaker RI (SK No. KEP.001/PPK-PJK3/V/2023). The system manages the end-to-end workflow of industrial inspection (*riksa uji*) jobs — from initial purchase order through field execution, regulatory permit (*Suket*) processing, and final invoicing.

---

## System Context

```
┌─────────────────────────────────────────────────────────────┐
│                     PT DNP Internal Users                   │
│  Marketing │ Admin Dok │ Admin RU │ Inspektur │ FIN │ MGR   │
└────────────────────────────┬────────────────────────────────┘
                             │ HTTPS
                    ┌────────▼────────┐
                    │  Nginx Web Server│  (VPS - Ubuntu)
                    └────────┬────────┘
                             │ Reverse Proxy
              ┌──────────────▼──────────────┐
              │      Laravel Monolith       │
              │  ┌────────────────────────┐ │
              │  │  Inertia + React (TSX) │ │  ← Frontend UI
              │  └────────────────────────┘ │
              │  ┌────────────────────────┐ │
              │  │   PostgreSQL 15        │ │  ← Primary DB
              │  │   Laravel Policies     │ │  ← RBAC enforcement
              │  └────────────────────────┘ │
              │  ┌────────────────────────┐ │
              │  │   Sanctum / Session    │ │  ← Auth Identity
              │  └────────────────────────┘ │
              │  ┌────────────────────────┐ │
              │  │   Local Storage (Disk) │ │  ← Files & photos
              │  └────────────────────────┘ │
              │  ┌────────────────────────┐ │
              │  │   Queues & Jobs        │ │  ← Complex logic
              │  └────────────────────────┘ │
              └──────────────┬──────────────┘
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                   │
   ┌──────▼──────┐  ┌────────▼──────┐  ┌────────▼──────┐
   │  WhatsApp   │  │ Email Service │  │  Disnaker     │
   │  Business   │  │ (Resend /     │  │  (Teman K3)   │
   │  API (Wati/ │  │  Postmark)    │  │  Manual until │
   │  Qontak)    │  └───────────────┘  │  API available│
   └─────────────┘                     └───────────────┘
```

---

## Core Workflow: 7-Stage Pipeline

Every *riksa uji* job progresses through exactly 7 stages, enforced by gate logic at each transition:

```
Stage 1          Stage 2          Stage 3          Stage 4
┌───────────┐    ┌───────────┐    ┌───────────┐    ┌───────────┐
│ Marketing │───▶│ Verifikasi│───▶│Penjadwalan│───▶│Pelaksanaan│
│  & PO     │    │  Dokumen  │    │ & Surat   │    │  Lapangan │
│  (MKT)    │    │  (ADM)    │    │  Tugas    │    │  (INS)    │
└───────────┘    └───────────┘    │  (ADM)    │    └───────────┘
                 Gate: all 8      └───────────┘    Gate: field
                 checklist        Gate: sched +    checklist +
                 items OK         inspektur +      photos
                                  tools assigned   uploaded

Stage 5          Stage 6          Stage 7
┌───────────┐    ┌───────────┐    ┌───────────┐
│ LHPP &    │───▶│ Pengurusan│───▶│ Penagihan │
│ Peer      │    │  Suket    │    │ & Selesai │
│ Review    │    │(per-unit) │    │  (FIN)    │
│(INS+MGR)  │    │  (MGR)    │    └───────────┘
└───────────┘    └───────────┘    Gate: FIN
Gate: MGR        Gate: ALL        marks paid
approves LHPP    units Suket
                 terbit
```

---

## Role Architecture

| Role Code | Full Name | Primary Stages | Key Restriction |
|-----------|-----------|---------------|-----------------|
| `MKT` | Marketing | 1 | Cannot edit nilai kontrak after Stage 2 |
| `ADM` | Admin Dokumen & RU | 2, 3 | Cannot set LAIK/TIDAK LAIK, cannot touch invoices |
| `INS` | Tim Ahli / Inspektur | 4, 5 | Cannot see nilai kontrak, cannot edit other inspektur jobs |
| `MGR` | Kadiv RU / Manager | 5 (review), 6 | Cannot finalize invoices |
| `FIN` | Admin Keuangan | 7 | Exclusive invoice authority |

---

## Current vs Target Architecture

### Current (Prototype)
- Single HTML file (~370 KB), React 18 compiled in-browser via Babel Standalone
- Dependencies from ESM CDN (esm.sh)
- State: React hooks only
- Persistence: `localStorage` browser shim via `window.storage` API
- Auth: Role selector only (no password)
- Suitable for: demo, internal validation, single-device use

### Target (Production)
- **Frontend:** React 18 + Vite (Inertia.js + TSX)
- **Backend:** Laravel 11 (PHP 8.2+ Monolith)
- **Database:** PostgreSQL 15 (managed via Laravel Eloquent ORM)
- **RBAC:** Enforced at application level via Laravel Policies / Gates
- **Audit:** Immutable event-based `audit_log` table
- **Notifications:** Self-hosted WhatsApp (Baileys/WA-Web.js) + Local SMTP (Postfix) via Laravel Queues (No external API services)
- **Hosting:** Self-hosted Bare-metal VPS (Ubuntu + Nginx + PHP-FPM, No Docker)
- **Domain:** `monitor.deltanusantara.co.id` with auto SSL (Let's Encrypt via Certbot)

---

## Key Architectural Decisions

### 1. Per-Unit Suket Tracking (Stage 6 Hybrid Model)
Traditional model holds entire PO until all units receive *Suket*. DNP system implements **Opsi C hybrid**: Stages 1-5 operate as a single job package; Stage 6 splits to per-unit independent tracking. This enables partial invoicing when only some units are approved, eliminating cash-flow holds from a single rejected unit.

### 2. Transparent Inspector Recommendation Engine
Inspector assignment uses a two-phase algorithm (hard filter → soft scoring), not a black box. Scores are decomposed to five components visible to the user. This ensures accountability and allows the admin to override with a clear audit record. See `4_AUTHENTICATION_SECURITY.md` for permission details and `3_API_SPECIFICATION.md` for the recommendation endpoint.

### 3. Compliance-First Data Model
Expired items (SKP Ahli K3, alat kalibrasi, sertifikat PJK3) are hard-locked at the data layer, not just the UI. Gate logic prevents stage advancement unless compliance is satisfied. This design supports Kemnaker audit requirements out of the box.

### 4. Immutable Audit Trail
All `INSERT`/`UPDATE`/`DELETE` on job-related tables are mirrored to an `audit_log` table via PostgreSQL triggers. The `audit_log` table has no `UPDATE` or `DELETE` permissions for any role, including `service_role`. This provides chain-of-custody evidence for Kemnaker audits.

---

## Data Flow: New Job Lifecycle

```
1. MKT creates Job Card (PO, klien, pesawat, units, nilai)
        │
        ▼
2. ADM verifies 8-item document checklist → unlocks Stage 3
        │
        ▼
3. ADM schedules: date + inspektur (recommendation engine) + alat
   → System auto-generates Surat Tugas + H-5 reminder
        │
        ▼
4. INS executes field inspection: photos (8 categories), digital checklist,
   BAP, tap-to-call PIC
        │
        ▼
5. INS submits LHPP → MGR peer reviews → MGR "Approve & Lock"
   (field locked permanently after lock — integrity guarantee)
        │
        ▼
6. MGR tracks per-unit Suket at Disnaker (Pending/Di Disnaker/Terbit/Ditolak)
   System auto-calculates Suket validity per Permenaker
        │
        ▼
7. FIN issues invoice, tracks AR aging (4 buckets), marks pelunasan
```

---

## Non-Functional Requirements

| Concern | Target |
|---------|--------|
| Concurrent users | Up to 20 simultaneous (initial), horizontally scalable |
| Response time | < 300ms for list/read, < 500ms for write operations |
| Uptime | 99.5% (VPS SLA dependent) |
| Data retention | Permanent; no auto-deletion |
| File storage | Local disk storage with Spatie Image Optimizer (compression) to reduce VPS disk usage |
| Backup | Daily automated VPS snapshot + weekly DB export to offsite storage |
| Compliance | Kemnaker RI audit-ready; immutable audit log; SKP/kalibrasi validation |
| Mobile | Progressive Web App (PWA) for INS role (offline checklist cache) |
