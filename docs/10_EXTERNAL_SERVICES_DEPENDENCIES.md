# 10. External Services & Dependencies — DNP Riksa Uji Monitor

> **Policy:** Per PT DNP's security and cost-efficiency policy, this system is designed to run **100% self-hosted on a bare-metal VPS** without relying on external SaaS providers, APIs, or Docker.

---

## 10.1 Service Overview

| Component | Technology | Role |
|-----------|------------|------|
| **Web Server** | Nginx | Reverse proxy, static asset delivery, SSL termination |
| **App Server** | PHP 8.2+ (PHP-FPM) | Laravel execution |
| **Database** | PostgreSQL 15 | Relational data storage |
| **In-Memory Cache** | Redis | Session state, Cache, Queue driver |
| **Process Monitor** | Supervisor | Running Laravel Queue workers and WhatsApp API |
| **Node.js Environment** | Node.js 20+ | Vite build step & WhatsApp microservice |

---

## 10.2 Self-Hosted WhatsApp API (Baileys)

Instead of using expensive third-party APIs (Wati/Qontak), notifications are dispatched via a local Node.js microservice running the [Baileys](https://github.com/WhiskeySockets/Baileys) library.

### Setup

1. A standalone Node.js script connects to WhatsApp via Web Sockets.
2. The script exposes a local HTTP API (e.g., `http://127.0.0.1:3000/send`).
3. Laravel queues send HTTP requests to this local API.
4. Run the script using `pm2` or `supervisor` to ensure it restarts on crash.

### Authentication

The DNP WhatsApp number must be authenticated by scanning a QR code on the VPS (via terminal or a local admin dashboard) upon initial setup. The session is then saved locally.

### Retry Strategy

- WhatsApp delivery is best-effort.
- If the local Node API is down, Laravel's queue will retry the job up to 3 times with exponential backoff.
- If the message fails after 3 tries, it is marked as failed in the `failed_jobs` table for manual review.

---

## 10.3 Local Email Delivery (Postfix)

For transactional emails (password resets, LHPP digests), the VPS runs a local Postfix SMTP server.

### Configuration

- Laravel is configured to send via `smtp` on `127.0.0.1:25`.
- Postfix is configured for "Send-Only" mode.
- To ensure deliverability and avoid spam folders, the domain `monitor.deltanusantara.co.id` must have valid:
  - **SPF** (Sender Policy Framework)
  - **DKIM** (DomainKeys Identified Mail)
  - **DMARC** records.

---

## 10.4 Composer Dependencies (Backend)

| Package | Purpose |
|---------|---------|
| `laravel/framework` | Core backend framework |
| `inertiajs/inertia-laravel` | Inertia.js adapter for Laravel |
| `spatie/laravel-permission` | Role and permission management |
| `spatie/laravel-image-optimizer` | Local image compression before saving to disk |
| `sentry/sentry-laravel` | (Optional) Exception tracking |
| `pestphp/pest` | Testing framework |

---

## 10.5 NPM Dependencies (Frontend)

| Package | Purpose |
|---------|---------|
| `react` / `react-dom` | UI framework |
| `@inertiajs/react` | Inertia.js adapter for React |
| `lucide-react` | Icons |
| `axios` | HTTP client |
| `tailwindcss` | CSS styling |
| `vite` | Frontend build tool |

---

## 10.6 Compression & Optimization

Since all files are stored locally, disk space is conserved using the following strategies:

1. **Images:** `spatie/laravel-image-optimizer` automatically optimizes JPEGs/PNGs uploaded from the field (Stage 4) using `jpegoptim` and `optipng` installed on the Ubuntu server.
2. **Text Assets:** Nginx is configured to serve JS, CSS, and HTML with `gzip` compression enabled.
3. **Database:** PostgreSQL handles row compression internally. Old records are archived (see file 9) but not deleted.

---

## 10.7 Server Maintenance & Updates

Because the VPS is self-managed, security patches must be applied manually or automatically.

- Enable `unattended-upgrades` on Ubuntu for automatic security patches.
- Set a calendar reminder quarterly to run `composer update` and `npm update` in a staging environment to catch minor version updates.
- Monitor disk space usage using basic Linux tools (`df -h`). Set an alert if disk usage exceeds 80%.
