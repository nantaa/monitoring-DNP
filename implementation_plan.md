# Implementation Plan: DNP Riksa Uji Monitor

This plan outlines the systematic steps to build the self-hosted Laravel + Inertia.js application for PT DNP based on our finalized architectural documentation and the `dnp_monitor (2).html` prototype.

## User Review Required

> [!IMPORTANT]
> **Project Root Setup**: I propose moving the existing documentation markdown files into a `docs/` folder and initializing the new Laravel project directly in `c:\DNP\monitoring`. Does this sound good, or would you prefer the Laravel app to be in a subdirectory like `c:\DNP\monitoring\dnp-app`?

> [!IMPORTANT]
> **Database**: Do you have a local PostgreSQL instance running on your Windows machine that we can connect to for development? If so, I will need the connection credentials (username, password, db name) later. Alternatively, we can use SQLite for local development and swap to PostgreSQL for production. Please let me know your preference.

## Proposed Changes

We will tackle the implementation in focused, logical phases to ensure stability and correctness.

### Phase 1: Project Scaffolding & Configuration
- Create a `docs/` folder and move all `.md` files into it.
- Initialize a fresh Laravel 11 project.
- Install and configure **Inertia.js** with **React (TypeScript/TSX)** and **Tailwind CSS**.
- Install necessary Laravel packages: `spatie/laravel-permission` (RBAC), `spatie/laravel-image-optimizer`.
- Configure the `.env` file for local development.

### Phase 2: Database Schema & Models
- Scaffold migrations for all tables defined in `2_DATABASE_SCHEMA.md` (e.g., `jobs`, `audit_logs`, `inspektur_list`, `alat_inventory`).
- Create Eloquent Models with appropriate relationships and `$casts` (especially for JSONB columns).
- Implement the `JobObserver` to automatically write to `audit_logs` whenever a job is updated.
- Create Seeders to populate master data (Regulasi, Pesawat, initial Admin user, Roles/Permissions).

### Phase 3: Authentication & Authorization
- Set up Laravel session-based authentication via Inertia.
- Implement the login page.
- Configure Laravel Policies to restrict access based on the user's role (Manager, Finance, Inspektur, Marketing, Admin).
- Pass user permissions to the React frontend via Inertia's shared data.

### Phase 4: Core Workflows & Backend Logic
- Create Controllers and `FormRequest` classes for handling Job creation and the 7-stage advancement logic.
- Implement the Inspector Recommendation Algorithm (from `8_CODE_STRUCTURE_PATTERNS.md`) as a dedicated PHP Service.
- Set up File Upload logic for photos/documents with Spatie Image Optimizer.

### Phase 5: Frontend Interface (React/Inertia)
- Build the core Authenticated Layout with the sidebar and header.
- Implement the Kanban Board and Job List views with server-side pagination.
- Port the structure and logic of the `JobDetailModal` and its stage-specific panels from the prototype to React/Inertia components.
- Implement the per-role dashboard panels.

## Verification Plan

### Automated Tests
- Write Pest PHP tests for the core business logic, including:
  - The Recommender Algorithm.
  - Stage transition gate logic (e.g., cannot advance from Stage 1 without `nilai_kontrak`).
  - Permission checks (e.g., Inspektur cannot view financials).

### Manual Verification
- Start the local development server (`php artisan serve` and `npm run dev`).
- Manually test logging in as different roles to verify RBAC UI changes.
- Walk through the entire 7-stage lifecycle of a Job using the UI to ensure the prototype's logic was ported correctly.
