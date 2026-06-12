# 7. Deployment & DevOps — DNP Riksa Uji Monitor

## Infrastructure Overview

| Component | Service | Tier | Cost (est.) |
|-----------|---------|------|-------------|
| Hosting | Bare-metal VPS (Ubuntu 22.04/24.04) | 4 vCPU, 8GB RAM | ~Rp 350-500k/mo |
| Database | PostgreSQL 15 (Local on VPS) | - | Included |
| Domain + SSL| Nginx + Certbot Let's Encrypt | - | Rp 150k/yr (domain only) |
| WhatsApp | Self-hosted Baileys/WA-Web.js | Node.js pm2 | Included |
| Email | Local Postfix SMTP | - | Included |
| **Total ongoing** | | | **~Rp 350k–500k/mo** |

---

## Repository Structure

```
dnp-monitor/
├── app/
│   ├── Http/
│   │   ├── Controllers/   # Laravel API & Inertia Controllers
│   │   ├── Middleware/
│   │   └── Requests/      # Form validation logic
│   ├── Models/            # Eloquent Models (Job, User, etc.)
│   ├── Policies/          # RBAC logic
│   ├── Observers/         # Audit log triggers
│   └── Services/          # Reusable business logic (Stage gates, Recommender)
├── config/
├── database/
│   ├── migrations/        # SQL structure definition
│   └── seeders/           # Demo data
├── resources/
│   ├── js/
│   │   ├── Pages/         # React Components (Inertia views)
│   │   ├── Components/    # Reusable UI
│   │   ├── lib/           # Utils
│   │   └── app.tsx        # React root
│   └── css/
├── routes/
│   ├── web.php            # Inertia Routes
│   └── api.php            # External API Routes
├── tests/                 # Pest / PHPUnit tests
├── .github/
│   └── workflows/
│       └── deploy.yml     # CI/CD to VPS via SSH
├── package.json
└── composer.json
```

---

## Environment Variables

```bash
# .env.example — production secrets are kept strictly on the VPS

APP_NAME="DNP Riksa Uji Monitor"
APP_ENV=production
APP_KEY=base64:xxx
APP_DEBUG=false
APP_URL=https://monitor.deltanusantara.co.id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dnp_monitor
DB_USERNAME=dnp_user
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=25

WHATSAPP_SERVER_URL=http://127.0.0.1:3000
```

---

## CI/CD Pipeline

### Branch Strategy

```
main           ← production-only, protected (requires PR + review)
staging        ← auto-deploys to staging VPS environment
feature/*      ← developer branches, PR → staging
hotfix/*       ← emergency fixes, PR → main with expedited review
```

### Deploy Workflow (SSH to VPS)

```yaml
# .github/workflows/deploy.yml
name: Deploy to VPS
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup SSH key
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/id_rsa
          chmod 600 ~/.ssh/id_rsa
          ssh-keyscan -H ${{ secrets.VPS_IP }} >> ~/.ssh/known_hosts
          
      - name: Deploy Script
        run: |
          ssh deployer@${{ secrets.VPS_IP }} << 'EOF'
            cd /var/www/dnp-monitor
            git pull origin main
            composer install --no-interaction --prefer-dist --optimize-autoloader
            npm ci
            npm run build
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            sudo systemctl restart php8.2-fpm
            sudo supervisorctl restart all
          EOF
```

---

## Database Migrations

All schema changes go through Laravel migration files.

```bash
# Create a new migration
php artisan make:migration add_job_notes_field_to_jobs_table

# Apply to local
php artisan migrate

# Apply to staging/production (happens in CI script)
php artisan migrate --force
```

Migration file naming:
```
database/migrations/
  2026_06_01_000001_create_jobs_table.php
  2026_06_05_000001_add_job_notes.php
```

**Rule:** Migrations are **additive only** in production. Dropping columns requires: (1) deprecation period, (2) data migration, (3) separate drop migration. Never drop a column in the same migration as a schema change.

---

## Health Checks

```php
// routes/web.php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'db' => 'connected']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 503);
    }
});
```

Configure external monitor (e.g., BetterUptime) to alert on non-200 response.

---

## Backup Strategy

| Type | Frequency | Retention | Method |
|------|-----------|-----------|--------|
| VPS Snapshot | Daily 02:00 WIB | 7 days | Cloud provider auto-snapshot |
| Database Dump | Daily 03:00 WIB | 30 days | Spatie Laravel Backup (Cron) → S3 or separate disk |
| Before-migration snapshot | Before major release | Until next release | Manual DB dump via `pg_dump` |

```php
// app/Console/Kernel.php
$schedule->command('backup:run')->dailyAt('03:00');
```

---

## Rollback Plan

### Frontend & Application Rollback
1. SSH into the VPS
2. Revert the repository to the last stable commit: `git checkout <stable_commit_hash>`
3. Run `composer install` and `npm run build`
4. Clear cache and restart FPM: `php artisan optimize:clear && systemctl restart php8.2-fpm`

### Database rollback
1. Identify last clean backup timestamp (`storage/app/backups/`)
2. Run `pg_restore` to replace corrupted data
3. Announce maintenance window to team via WhatsApp
4. Verify data integrity with `SELECT COUNT(*)` sanity checks

### Rollback Decision Tree
```
Production incident reported
         │
    Data affected?
    ├── YES → Immediate DB restore + rollback frontend
    └── NO → Feature-only? → Rollback frontend only
                              Re-deploy hotfix branch within SLA
```

---

## Progressive Web App (PWA) for INS Role

To support inspektur in locations with weak signal:

```json
// public/manifest.json
{
  "name": "DNP Monitor",
  "short_name": "DNP",
  "start_url": "/dashboard",
  "display": "standalone",
  "background_color": "#F2EFE8",
  "theme_color": "#1e3a2f",
  "icons": [
    { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

Service Worker caches:
- Kanban board (read-only) for offline viewing
- Field checklist form (submit when online)
- Surat Tugas PDF (prefetched when assigned)

---

## Custom Domain Setup

1. Purchase `deltanusantara.co.id` (or confirm already owned)
2. Add subdomain `monitor.deltanusantara.co.id` in DNS pointing to VPS IP Address (A Record)
3. Configure Nginx Server Block:
   ```nginx
   server {
       listen 80;
       server_name monitor.deltanusantara.co.id;
       root /var/www/dnp-monitor/public;
       
       add_header X-Frame-Options "SAMEORIGIN";
       add_header X-Content-Type-Options "nosniff";
       
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
       }
   }
   ```
4. Run Certbot to provision SSL: `sudo certbot --nginx -d monitor.deltanusantara.co.id`

---

## Release Checklist

Before every production deploy:

- [ ] All unit tests pass (`php artisan test`)
- [ ] Integration tests pass
- [ ] Migration reviewed by developer (no accidental drops)
- [ ] `CHANGELOG.md` updated
- [ ] Staging smoke test: create job → advance to Stage 3 → verify
- [ ] Team notified via WhatsApp 30 min before deploy
