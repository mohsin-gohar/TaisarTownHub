# Hostinger VPS — Production Deployment Guide

**Applies to:** TaisarTownHub (Laravel 12 API + React 19 SPA)
**Target:** Hostinger VPS (Ubuntu 22.04/24.04), Docker Engine + Compose plugin
**Status of this guide:** written against the repository's `docker-compose.yml`,
`backend/Dockerfile.prod`, `frontend/Dockerfile.prod` and `nginx/nginx.conf`.
The stack was **not** executed here because this workspace has no Docker daemon
and no root access. Treat the first VPS run as a controlled smoke test.

---

## 1. What runs in production

| Service | Runtime | Public? | Purpose |
|---|---|---|---|
| `nginx` | nginx:alpine | **yes** (80/443) | TLS, security headers, reverse proxy, `/storage/` |
| `frontend` | nginx:alpine (static Vite build) | no (internal :80) | SPA + prerendered pages |
| `backend` | **PHP-FPM 8.3** (`php-fpm -F`) | no (internal :9000) | Laravel API |
| `worker` | PHP CLI | no | `queue:work` |
| `scheduler` | PHP CLI | no | `schedule:work` (alerts, expiry, purge) |
| `mysql` | MySQL 8.4 | no | Primary database |
| `redis` | Redis 7 | no | Cache, sessions, queues, rate limits |
| `clamav` | clamav/clamav | no | Malware scanning (fail-closed) |
| `migrate` | PHP CLI one-shot | no | Runs schema migrations once |

Persistent named volumes: `mysql_data`, `redis_data`, `clamav_data`,
`private_vault` (documents), `public_storage` (map images / public media).

> `php artisan serve` is **not** used. The API is PHP-FPM behind Nginx.

---

## 2. VPS sizing and OS preparation

Recommended minimum: **4 vCPU / 8 GB RAM / 80 GB SSD** (ClamAV and MySQL are
memory-hungry). A 2 GB VPS will struggle; 4 GB is the practical floor.

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install ca-certificates curl git ufw fail2ban certbot

# Docker Engine + Compose plugin (official convenience script; review before use)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # log out/in afterwards
docker --version && docker compose version
```

Firewall (only SSH, HTTP, HTTPS exposed; MySQL/Redis stay private):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status
```

Optional hardening: disable SSH password login, use keys, set
`PermitRootLogin no`, and keep `fail2ban` enabled.

---

## 3. DNS

Create these records at your DNS provider **before** issuing certificates:

| Type | Name | Value |
|---|---|---|
| A | `@` | VPS IPv4 |
| A | `www` | VPS IPv4 |
| A | `api` | VPS IPv4 |
| AAAA | `@`, `www`, `api` | VPS IPv6 (only if the VPS has one) |

Wait for propagation: `dig +short taisartownhub.com api.taisartownhub.com`.

---

## 4. Get the code

```bash
sudo mkdir -p /opt/taisartownhub
sudo chown "$USER":"$USER" /opt/taisartownhub
git clone https://github.com/mohsin-gohar/TaisarTownHub.git /opt/taisartownhub
cd /opt/taisartownhub
git checkout main
git log -1 --oneline
```

Never develop directly on the VPS. Deploy reviewed commits from `main`.

---

## 5. Create the production environment file

Compose reads **`/opt/taisartownhub/.env`** (repository root). It is gitignored.
Create it with strict permissions:

```bash
cd /opt/taisartownhub
umask 077
cat > .env <<'EOF'
# ---- Database ----
DB_ROOT_PASSWORD=REPLACE_WITH_LONG_RANDOM
DB_DATABASE=taisartownhub
DB_USERNAME=taisar
DB_PASSWORD=REPLACE_WITH_LONG_RANDOM

# ---- Redis ----
REDIS_PASSWORD=REPLACE_WITH_LONG_RANDOM

# ---- Laravel ----
# Generate: openssl rand -base64 32   (then prefix with base64:)
APP_KEY=base64:REPLACE_WITH_32_BYTE_BASE64

# ---- Payments (leave blank to disable; never invent values) ----
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
FLUTTERWAVE_PUBLIC_KEY=
FLUTTERWAVE_SECRET_KEY=
FLUTTERWAVE_ENCRYPTION_KEY=
FLUTTERWAVE_WEBHOOK_SECRET=

# ---- Mail ----
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@taisartownhub.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@taisartownhub.com

# ---- Observability (optional) ----
SENTRY_DSN=
LOGROCKET_APP_ID=
GA_MEASUREMENT_ID=
EOF
chmod 600 .env
```

Generate a key without a local PHP install:

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

> The Compose file injects `APP_ENV=production`, `APP_DEBUG=false`, MySQL/Redis
> hosts and the ClamAV socket. Do **not** add development values there.

---

## 6. TLS certificates

`nginx/nginx.conf` expects:

```
nginx/ssl/fullchain.pem
nginx/ssl/privkey.pem
```

`nginx/ssl/` is gitignored. First issuance (port 80 must be free, so do this
**before** `docker compose up`):

```bash
cd /opt/taisartownhub
sudo certbot certonly --standalone \
  -d taisartownhub.com -d www.taisartownhub.com -d api.taisartownhub.com \
  --agree-tos -m admin@taisartownhub.com --no-eff-email

mkdir -p nginx/ssl
sudo cp /etc/letsencrypt/live/taisartownhub.com/fullchain.pem nginx/ssl/
sudo cp /etc/letsencrypt/live/taisartownhub.com/privkey.pem   nginx/ssl/
sudo chown "$USER":"$USER" nginx/ssl/*
chmod 600 nginx/ssl/privkey.pem
```

Automatic renewal (certbot's timer plus a deploy hook that reloads Nginx):

```bash
sudo tee /etc/letsencrypt/renewal-hooks/deploy/taisartownhub.sh >/dev/null <<'EOF'
#!/bin/sh
set -e
cd /opt/taisartownhub
cp /etc/letsencrypt/live/taisartownhub.com/fullchain.pem nginx/ssl/
cp /etc/letsencrypt/live/taisartownhub.com/privkey.pem   nginx/ssl/
chmod 600 nginx/ssl/privkey.pem
docker compose exec -T nginx nginx -s reload
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/taisartownhub.sh
sudo certbot renew --dry-run
```

---

## 7. Build and start

```bash
cd /opt/taisartownhub
docker compose build
docker compose up -d
docker compose ps
```

Expected: `migrate` exits `0`, everything else `running`/`healthy`.
ClamAV needs up to ~2 minutes on first start (signature download); `backend`
waits for it.

```bash
docker compose logs -f migrate      # must show "migrations complete"
docker compose logs -f backend
docker compose logs -f nginx
```

If the build fails, fix it on a branch and redeploy — do not patch source files
directly on the server.

---

## 8. First-run platform setup

Run only the idempotent enterprise catalog (roles/permissions/plans/settings).
The default `DatabaseSeeder` intentionally seeds demo data only outside
production.

```bash
docker compose exec backend php artisan db:seed --class=EnterpriseSeeder --force
docker compose exec backend php artisan storage:link --force
```

Create the first Super Admin (replace the email/password; the password is not
echoed by the app but will appear in your shell history, so prefer a one-off
password manager value and change it after first login):

```bash
docker compose exec backend php artisan tinker --execute="
\App\Models\User::updateOrCreate(
  ['email' => 'admin@taisartownhub.com'],
  [
    'name' => 'Super Admin',
    'password' => \Illuminate\Support\Facades\Hash::make('CHANGE_ME_STRONG'),
    'role' => 'super_admin',
    'status' => 'active',
    'email_verified_at' => now(),
  ]
);"
```

Then sign in, enable MFA, and delete/rotate the bootstrap password.

Preflight gate:

```bash
docker compose exec backend php artisan app:production-check
```

It must report **0 hard failures**. Investigate every failure before go-live.

---

## 9. Verification (do not skip)

```bash
# API + DB health
curl -fsS https://api.taisartownhub.com/api/health

# Frontend
curl -fsSI https://taisartownhub.com/ | head

# TLS + redirect
curl -fsSI http://taisartownhub.com/ | grep -i location

# Containers
docker compose ps
```

Manual checks in a real browser:

1. Homepage, properties, agencies, maps, gallery load with no console errors.
2. Register → verify email → log in.
3. Agency onboarding → create property → upload image → appears publicly.
4. Upload a document (private) → download → verify another agency gets 404.
5. Maps: upload an image in Super Admin → Map Management → public `/maps` shows it.
6. Check `https://api.taisartownhub.com/storage/...` returns the uploaded image
   (this is the shared `public_storage` volume served by Nginx).
7. Mobile widths 360/390 px: navigation, filters, forms, map lightbox.

Queue and scheduler:

```bash
docker compose exec backend php artisan queue:monitor default --max=100
docker compose logs --tail=50 scheduler
```

---

## 10. Map asset import (currently blocked externally)

`MapSeeder` expects 11 files at:

```
storage/app/public/maps/taiser-town-map-page-01.jpg
...
storage/app/public/maps/taiser-town-map-page-11.jpg
```

They are **not** in the repository (no PDF, no images were provided). Do not
substitute unrelated images. When the source PDF/images are available:

```bash
# Convert the PDF pages to JPEGs (one image per page, high quality)
pdftoppm -jpeg -r 200 taiser-town.pdf /tmp/taiser-town-map-page
# rename to page-01..page-11, then copy into the shared public volume:
docker compose cp /tmp/maps/. backend:/var/www/html/storage/app/public/maps/
docker compose exec backend php artisan db:seed --class=MapSeeder --force
```

Verify in Super Admin → Map Management, then on `/maps`.

---

## 11. Backups and restore

Back up **both** the database and the two file volumes.

```bash
cd /opt/taisartownhub
mkdir -p /var/backups/taisartownhub

# Database
docker compose exec -T mysql \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --routines \
  "$DB_DATABASE" | gzip > "/var/backups/taisartownhub/db-$(date +%F-%H%M).sql.gz"

# Document vault + public storage
docker run --rm \
  -v taisartownhub_private_vault:/data \
  -v /var/backups/taisartownhub:/backup alpine \
  tar czf "/backup/private-vault-$(date +%F-%H%M).tar.gz" -C /data .

docker run --rm \
  -v taisartownhub_public_storage:/data \
  -v /var/backups/taisartownhub:/backup alpine \
  tar czf "/backup/public-storage-$(date +%F-%H%M).tar.gz" -C /data .
```

(`docker volume ls` shows the exact project-prefixed volume names.)

Restore (test this on a staging VPS first):

```bash
gunzip -c db-YYYY-MM-DD-HHMM.sql.gz | \
  docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"

docker run --rm -v taisartownhub_private_vault:/data \
  -v /var/backups/taisartownhub:/backup alpine \
  sh -c 'rm -rf /data/* && tar xzf /backup/private-vault-YYYY-MM-DD-HHMM.tar.gz -C /data'
```

Add a daily cron on the host and copy backups off-server (object storage or a
second machine). A backup that has never been restored is not a backup.

---

## 12. Updates and rollback

```bash
cd /opt/taisartownhub
git fetch origin
git checkout main
git pull --ff-only
docker compose build
docker compose up -d
docker compose ps
docker compose exec backend php artisan app:production-check
```

Rollback:

```bash
git log --oneline -10
git checkout <previous-known-good-commit>
docker compose build
docker compose up -d
# Restore the matching database backup if the bad release migrated the schema.
```

Never `git reset --hard` on the server and never force-push.

---

## 13. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `502 Bad Gateway` | PHP-FPM down / not ready | `docker compose logs backend`; check `APP_KEY` set |
| `413 Request Entity Too Large` | upload over limits | Nginx `client_max_body_size` (50m) and `php-prod.ini` are set; confirm the image was rebuilt |
| `500` on first request | permissions / config cache | `docker compose exec backend php artisan config:clear`; check `storage` ownership |
| Map images 404 | `public_storage` not mounted / Nginx alias | `docker compose exec nginx ls /var/www/storage/public`; verify `location /storage/` |
| Uploads rejected | ClamAV unavailable (fail-closed) | `docker compose logs clamav`; wait for signatures; check `CLAMD_SOCKET=clamav:3310` |
| `419` / auth oddities | proxy headers / session | Ensure Nginx passes `X-Forwarded-*`; `SESSION_DRIVER=redis` |
| CORS errors | origin mismatch | Update `CORS_ALLOWED_ORIGINS` in `docker-compose.yml` and rebuild |
| Scheduler jobs not running | `scheduler` service down | `docker compose ps scheduler`; `logs scheduler` |
| Migration loop | `migrate` service failing | `docker compose logs migrate`; fix the migration, never edit `migrations` table manually |
| Out of memory | ClamAV/MySQL on small VPS | resize VPS; do not disable malware scanning |

Useful commands:

```bash
docker compose ps
docker compose logs --tail=200 <service>
docker compose exec backend php artisan about
docker compose exec backend php artisan route:list
docker compose exec mysql mysql -uroot -p -e 'SHOW PROCESSLIST;'
```

---

## 14. Go-live checklist

- [ ] DNS resolves for root, `www`, `api`; TLS valid and auto-renewing
- [ ] `.env` is `chmod 600`, no secrets in git, `APP_DEBUG=false`
- [ ] `app:production-check` reports 0 hard failures
- [ ] MySQL, Redis, ClamAV healthy; `worker` and `scheduler` running
- [ ] Migrations applied; `EnterpriseSeeder` run; Super Admin created + MFA
- [ ] Payments configured with **live** keys and webhook endpoints registered
- [ ] Mail delivers (password reset, verification, alerts)
- [ ] Backups run and a restore has been verified
- [ ] Browser smoke test of all critical journeys passes
- [ ] Monitoring/alerts configured (uptime, disk, queue depth, error logs)
- [ ] Map assets imported (currently blocked — see §10)
- [ ] Rollback commit and backup identified

---

## 15. Known external blockers (not code)

1. **11 map images / source PDF are absent.** `MapSeeder` skips missing files;
   the public maps page shows an honest empty state. Provide the assets.
2. **No Docker daemon / root in this workspace**, so the image build, PHP-FPM
   gateway, volumes and ClamAV were not executed here. Run §7–§9 on the VPS.
3. **No payment or mail credentials** were provided. Live checkout, webhooks and
   email delivery remain unverified until real (or test-mode) credentials are set.
4. **No VPS access** was provided, so domain/TLS/firewall checks are pending.
