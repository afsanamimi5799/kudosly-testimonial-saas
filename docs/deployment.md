# Kudosly — Deployment Runbook

Operational guide for deploying the Kudosly Laravel application to a production server.

**Status:** Draft for review. No deployment has been performed.
**Applies to:** The existing Laravel application at the repository root. This is **not** a
rebuild from scratch — the application, its migrations, and its Blade/Livewire views already
exist and are covered by an automated test suite.

> **Before you use this document**, read [Section 8 — Outstanding Prerequisites](#8-outstanding-prerequisites-requires-your-action).
> Several prerequisites (hosting, domain, email provider, Stripe account) must be in place
> before any step in Section 3 can succeed.

---

## Table of Contents

1. [Production Server Requirements](#1-production-server-requirements)
2. [Pre-Deployment Preparation](#2-pre-deployment-preparation)
3. [Deployment Sequence](#3-deployment-sequence)
4. [Production Environment Variable Checklist](#4-production-environment-variable-checklist)
5. [Database Backup, Restore, and Rollback](#5-database-backup-restore-and-rollback)
6. [Post-Deployment Smoke Tests](#6-post-deployment-smoke-tests)
7. [Scheduled Tasks and Ongoing Maintenance](#7-scheduled-tasks-and-ongoing-maintenance)
8. [Outstanding Prerequisites (Requires Your Action)](#8-outstanding-prerequisites-requires-your-action)
9. [Appendix — Notes and Verified Facts](#9-appendix--notes-and-verified-facts)

---

## 1. Production Server Requirements

### 1.1 Minimum Software

| Component | Requirement | Notes |
|---|---|---|
| OS | Linux (Ubuntu 22.04 / 24.04 LTS recommended) | Any Linux with a supported PHP build works |
| PHP | **8.3 or newer** (`composer.json` requires `^8.3`) | The project is developed and tested against PHP 8.5 |
| Composer | 2.x | Required for `composer install` |
| Node.js | **20 LTS or newer** | Needed only to build assets. Node 26.13.1 is used in development |
| npm | 10 or newer | Ships with Node 20+ |
| Database | **MySQL 8.0+** or **MariaDB 10.6+** | PRD §2 specifies MySQL. `config/database.php` supports both `mysql` and `mariadb` drivers |
| Web server | Nginx (recommended) or Apache | See [Section 1.3](#13-web-server-configuration) |
| Process manager | systemd **or** Supervisor | Only if a queue worker is used (see [Section 7](#7-scheduled-tasks-and-ongoing-maintenance)) |
| TLS | A valid certificate (Let's Encrypt or commercial) | Required — see [Section 4.2](#42-https-and-secure-session-cookies) |

### 1.2 Required PHP Extensions

All of the following must be enabled in the production `php.ini`:

```
pdo_mysql      # database driver for MySQL/MariaDB
mbstring       # Laravel + string handling
openssl        # encryption, HTTPS, Stripe TLS
tokenizer      # required by Laravel / Blade compilation
xml            # required by Laravel
ctype          # required by Laravel
fileinfo       # required by Laravel
json           # required by Laravel
curl           # Stripe API calls
gd             # image handling (profile photo uploads)
zip            # Composer package extraction
intl           # internationalisation
```

Verify with `php -m` on the server before deploying.

### 1.3 Web Server Configuration

The document root **must** point at the `public/` directory. The `public/.htaccess` file in the
repository contains the correct Apache rewrite rules. For Nginx, use:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$host$request_uri;   # force HTTPS
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/kudosly/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # Uploaded profile photos are capped at 2 MB by validation;
    # allow headroom for the request body.
    client_max_body_size 8M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;
    }

    # Never serve dotfiles or the storage directory directly.
    location ~ /\. { deny all; }
}
```

**If the application sits behind a reverse proxy or load balancer** (Cloudflare, nginx in
front of nginx, a CDN), the proxy must forward the original scheme and client IP:

```
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
proxy_set_header Host              $host;
```

This matters for two things: the rate limiter on public submissions keys on the client IP
(`AppServiceProvider` → `->by($request->ip())`), and Laravel must know it is serving HTTPS so
that generated URLs (password-reset links, Stripe return URLs) use `https://`.

> **Action required in the application.** `bootstrap/app.php` does **not** currently call
> `trustProxies()`. Without it, Laravel behind a proxy will see the connection as plain HTTP
> and will emit `http://` URLs even though the browser is on HTTPS. This is a small application
> change and is **not** part of this document's scope — it is listed in
> [Section 8](#8-outstanding-prerequisites-requires-your-action) as a decision for you.

### 1.4 Filesystem Permissions

The web server user (commonly `www-data`) needs write access to `storage/` and `bootstrap/cache/`:

```bash
sudo chown -R www-data:www-data /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache
sudo chmod -R 775 /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache
```

| Path | Purpose |
|---|---|
| `storage/app/private/` | Default filesystem disk (`local`) |
| `storage/app/public/profile-photos/` | Uploaded customer profile photos (served via `public/storage`) |
| `storage/framework/{cache,sessions,views}/` | Compiled views, file cache, file sessions |
| `storage/logs/` | Application logs |
| `bootstrap/cache/` | Compiled config, routes, views, service manifest |

**Do not grant write access to the application root, `config/`, `app/`, or `resources/`.**

### 1.5 PHP Settings

In the production `php.ini` (or a pool-specific override):

```ini
memory_limit = 512M
post_max_size = 8M
upload_max_filesize = 2M
max_execution_time = 60
opcache.enable = 1
opcache.validate_timestamps = 0   ; only correct with a deploy-time opcache reset
opcache.revalidate_freq = 0
```

`upload_max_filesize` should match the application limit: the validation rule in
`SubmitTestimonialRequest` is `max:2048` (2 MB).

### 1.6 Directory Layout

```
/var/www/kudosly/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/            <-- web server document root
├── resources/
├── routes/
├── storage/
├── vendor/            <-- created by composer install
├── .env               <-- created by you; never committed
└── node_modules/      <-- temporary; may be deleted after the build
```

---

## 2. Pre-Deployment Preparation

### 2.1 Which Steps Apply to Your Situation

This runbook covers two situations. **Identify which one applies before you start** — the
steps differ in important ways, particularly around `APP_KEY`.

| | **NEW production environment** | **EXISTING installation** (already running somewhere) |
|---|---|---|
| Description | First launch on a fresh server | Moving an existing install, or re-deploying over a live one |
| `APP_KEY` | **Generate a new one** — see [2.2](#22-app_key) | **Keep the existing one** — see [2.2](#22-app_key) |
| Database | Create an empty database + user, then run all migrations | Existing database with real data — **back it up first** ([Section 5](#5-database-backup-restore-and-rollback)) |
| `PlanSeeder` | **Required** — the `plans` table starts empty | **Required** — idempotent; safe to re-run ([2.5](#25-planseeder)) |
| Migrations | All 18 run fresh | Only pending ones run ([3.4](#34-database-migrations)) |
| Backups | Not applicable before first launch | **Mandatory** before deploying ([5.1](#51-backup-procedure)) |
| Smoke tests | Full checklist ([Section 6](#6-post-deployment-smoke-tests)) | Full checklist, **plus** a data-integrity check ([6.3](#63-data-integrity-check)) |
| Rollback plan | Re-deploy from git | Restore from backup ([5.3](#53-rollback-procedure)) |

### 2.2 `APP_KEY`

**On a NEW environment**, generate a fresh key:

```bash
php artisan key:generate
```

**On an EXISTING installation, do NOT run `key:generate`.**

> **⚠️ Consequences of regenerating `APP_KEY` on an existing installation**
>
> `APP_KEY` is the master key for Laravel's encryption (`AES-256-CBC`). Regenerating it
> **permanently invalidates everything encrypted with the old key**:
>
> - **Every active user session is invalidated.** All users are logged out and must sign in again.
> - **Any data encrypted with the old key becomes permanently unreadable.** In this application
>   that includes the `sessions` table rows (irrelevant once invalidated) and, critically,
>   **any encrypted column added in future or third-party packages**.
> - **Cookie and CSRF tokens signed with the old key are rejected**, producing
>   "invalid token" or "token mismatch" errors for in-flight forms.
> - **It cannot be undone.** There is no recovery path unless `APP_PREVIOUS_KEYS` was configured
>   *beforehand* (`config/app.php:102-106` supports a comma-separated list of previous keys for
>   exactly this purpose).
>
> **Rule:** on an existing installation, `APP_KEY` must be **copied verbatim** from the current
> production `.env`. Never regenerate it as part of a deployment.

On a new environment, the key is created once and then treated as a permanent secret. Losing it
means losing access to encrypted data and invalidating all sessions.

### 2.3 Clone the Repository

```bash
cd /var/www
git clone <your-repository-url> kudosly
cd kudosly
```

The repository does **not** contain the built assets (`public/build` is gitignored) or the
dependencies (`vendor/` and `node_modules/` are gitignored). Both are produced by the
deployment steps in Section 3.

### 2.4 Create the Production `.env`

```bash
cp .env.example .env
php artisan key:generate     # NEW environment only — see Section 2.2
```

Then edit `.env` and work through the checklist in
[Section 4](#4-production-environment-variable-checklist). **The application will not function
correctly with the default `.env.example` values** — `APP_ENV=local`, `APP_DEBUG=true`,
`MAIL_MAILER=log`, and no Stripe credentials.

> **Never commit the production `.env`.** It is already excluded by `.gitignore` (`.env`,
> `.env.production`, `.env.*.local`). Verify with `git status --short` before committing
> anything — `.env` must not appear.

### 2.5 `PlanSeeder` — Required on Both New and Existing Installations

The `plans` table is the source of truth for every plan limit (Free: 3 spaces / 100
testimonials per space; Pro: 25 / 1,000). `App/Billing/PlanCatalog.php` raises a
`ModelNotFoundException` when a plan row is missing — the application does **not** fall back to
hardcoded limits, so an empty `plans` table breaks space creation and billing outright.

The seeder is **idempotent**: it uses `updateOrCreate()` keyed on `slug`, so re-running it
updates the existing rows rather than inserting duplicates. It also **deliberately never writes
`stripe_price_id`**, so re-running it will not erase a Stripe price ID you have already
configured.

```bash
# Correct — seeds only the plans:
php artisan db:seed --class=PlanSeeder

# DO NOT RUN on production:
php artisan db:seed
```

> **⚠️ Never run the bare `php artisan db:seed` on production.**
> `DatabaseSeeder` calls `PlanSeeder` **and then creates a user**:
>
> ```php
> $this->call(PlanSeeder::class);
>
> User::factory()->create([
>     'name' => 'Test User',
>     'email' => 'test@example.com',
> ]);
> ```
>
> Running it would insert a `test@example.com` account into the live database. Use
> `--class=PlanSeeder` explicitly.

---

## 3. Deployment Sequence

> **These steps have not been executed.** They are instructions for a future deployment run.
> Run them from the application root, `/var/www/kudosly`.

### 3.0 Before You Start

```bash
cd /var/www/kudosly
git fetch --all
git checkout <the-tag-or-branch-you-are-deploying>
```

On an **existing installation**, take a backup **before** proceeding —
[Section 5.1](#51-backup-procedure).

### 3.1 Install PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` excludes Pest, Pint, Mockery, Faker, and Boost — none of which are needed in
production and all of which would expose test helpers on a live server.

`--optimize-autoloader` builds a classmap so PHP resolves classes without scanning the
filesystem on every request.

> If `composer.lock` is present (it is), `composer install` installs the **exact** versions that
> were tested. Do **not** use `composer update` on the server — that would deploy untested
> dependency versions.

### 3.2 Build Production Assets

```bash
npm ci
npm run build
```

`npm ci` installs the exact versions in `package-lock.json` (unlike `npm install`, it will not
silently update the lockfile). `npm run build` runs `vite build`, which compiles Tailwind CSS
and the JS entry point into `public/build/`.

### 3.3 Verify the Asset Build — **Critical Step**

```bash
test -f public/build/manifest.json && echo "manifest OK" || echo "MANIFEST MISSING"
test -d public/build/assets && echo "assets OK" || echo "ASSETS MISSING"
ls -la public/build/
```

You must see:

- `public/build/manifest.json` — present and non-empty
- `public/build/assets/` — present and containing `app-*.css` and `app-*.js`
- `public/build/fonts-manifest.json` — present (the Instrument Sans webfonts)

> **⚠️ This is the single most likely cause of a total deployment failure.**
>
> `public/build` is **gitignored** and is therefore **not in the repository**. Every view in the
> application uses `@vite(['resources/css/app.css','resources/js/app.js'])` — the dashboard
> layout, the guest layout, the public collection page, the public wall of love, the landing
> page, and the password-reset pages.
>
> If `npm run build` is skipped or fails, Laravel throws `ViteManifestNotFoundException` and
> **every page returns HTTP 500** — including the public collection form, the public widgets
> (which would break every customer who has embedded them), and the Stripe return URLs.
>
> If this check fails, **do not continue the deployment.** Re-run `npm ci && npm run build`
> and check the output for errors.
>
> Note: `app-*.js` is legitimately **0 bytes** — `resources/js/app.js` is a 3-byte placeholder
> (`//`) and all interactivity is provided by Livewire. An empty `app-*.js` is expected and
> correct. An empty `app-*.css`, however, is a failure.

### 3.4 Database Migrations

```bash
php artisan migrate --force
```

`--force` is required in production because Laravel otherwise refuses to run migrations when
`APP_ENV=production`.

Review what is pending first:

```bash
php artisan migrate:status
```

All 18 migrations in this application should show **Ran**. The list is:

| Migration | Purpose |
|---|---|
| `0001_01_01_000000_create_users_table` | users, password reset tokens, sessions |
| `0001_01_01_000001_create_cache_table` | database cache store |
| `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs |
| `2026_11_18_000001_add_plan_fields_to_users_table` | stripe_id, billing columns, plan_slug |
| `2026_11_18_000002_create_plans_table` | plans |
| `2026_11_18_000003_add_plan_foreign_key_to_users_table` | users → plans FK |
| `2026_11_18_000004_create_spaces_table` | spaces |
| `2026_11_18_000005_create_space_fields_table` | space_fields |
| `2026_11_18_000006_create_testimonials_table` | testimonials |
| `2026_11_18_000007_create_widgets_table` | widgets |
| `2026_11_18_000008_add_soft_deletes_to_spaces_and_testimonials_table` | soft deletes |
| `2026_11_18_000009_add_soft_deletes_to_widgets_table` | soft deletes |
| `2026_11_20_155202_create_customer_columns` | Stripe customer columns |
| `2026_11_20_155203_create_subscriptions_table` | Cashier subscriptions |
| `2026_11_20_155204_create_subscription_items_table` | Cashier subscription items |
| `2026_11_20_155205_add_meter_id_to_subscription_items_table` | Cashier meter support |
| `2026_11_20_155206_add_meter_event_name_to_subscription_items_table` | Cashier meter support |
| `2026_11_21_090000_create_stripe_webhook_events_table` | webhook idempotency |

> **⚠️ Take a backup before running migrations on an existing installation.** Migrations are
> not automatically reversible in the general case. See [Section 5](#5-database-backup-restore-and-rollback).
>
> **Never run `migrate:fresh`, `migrate:reset`, or `migrate:refresh` on production.** Those
> drop tables and destroy all data.

### 3.5 Seed the Plans

```bash
php artisan db:seed --class=PlanSeeder
```

Required on **both** new and existing installations — see [Section 2.5](#25-planseeder) for why
this step is idempotent and safe to re-run, and for why the bare `db:seed` must never be used.

Verify:

```bash
php artisan tinker --execute="foreach(\App\Models\Plan::all() as \$p) { echo \$p->slug.' | spaces='.\$p->max_spaces.' | testimonials='.\$p->max_testimonials_per_space.' | price='.\$p->price.PHP_EOL; }"
```

Expected:

```
free | spaces=3 | testimonials=100 | price=0
pro  | spaces=25 | testimonials=1000 | price=99900
```

`price=99900` is 99900 cents = **$999.00 USD/year**, matching PRD §11.

### 3.6 Create the Storage Symlink

```bash
php artisan storage:link
```

This creates `public/storage` → `storage/app/public`.

> **Required.** `public/storage` is gitignored, so it will **not** exist on a fresh server.
> Without it, every uploaded customer profile photo is written to disk but is **unreachable
> over HTTP** — the result is broken images in the inbox, on the wall of love, and in embedded
> widgets.
>
> The upload path is `profile-photos` on the `public` disk
> (`SpaceCollectionController` → `->store('profile-photos', 'public')`).

Verify:

```bash
test -L public/storage && echo "symlink OK" || echo "SYMLINK MISSING"
```

If the symlink already exists, `storage:link` reports that and does nothing — that is fine.

### 3.7 Cache Configuration and Views — Safe Caching

```bash
php artisan config:cache
php artisan view:cache
```

These are the **safe** caching commands. They cannot fail because of a route closure.

| Command | What it does | Notes |
|---|---|---|
| `config:cache` | Compiles `config/*.php` into `bootstrap/cache/config.php` | Safe. No `env()` calls exist outside `config/` in this application — verified — so no environment value is baked in incorrectly |
| `view:cache` | Compiles every Blade template to `storage/framework/views` | Safe. Also fails loudly if a Blade template has a syntax error, which is useful |
| `route:cache` | Compiles routes into `bootstrap/cache/routes-v7.php` | **See the note below** |

### 3.8 Route Caching — Verify Before Enabling

`php artisan optimize` runs `config:cache`, `event:cache`, `route:cache`, and `view:cache` in
one go. Because `route:cache` is the only one of these that can fail, and because a failure
inside `optimize` aborts the remaining steps, the runbook deliberately **does not** use
`optimize`.

**Current status of route caching in this application: it has been tested and it works.**

A `route:cache` run completed successfully and produced a working `routes-v7.php`; the cached
route table resolved correctly, including `GET /up` and `POST stripe/webhook`. The welcome-page
closure in `routes/web.php:21` (`Route::get('/', function () { return view('welcome'); })`)
does **not** prevent route caching — Laravel serialises route closures with `Opis\Closure`, so
the closure is stored in the compiled route file rather than being rejected.

> **However — this was verified against the route table as it exists today.** Route caching
> depends on the *shape* of the route table, and it can break later if a future change
> introduces a route that cannot be serialised (a closure that captures an unserialisable
> object, or a route parameter with a runtime-only constraint).
>
> **Therefore, treat `route:cache` as optional and verify it every time, on every deploy.**
> The safe pattern is:
>
> ```bash
> php artisan route:cache
> php artisan route:list --json > /dev/null && echo "route cache OK" || php artisan route:clear
> ```
>
> If the check fails, `route:clear` removes the broken cache and the application falls back to
> loading routes from `routes/web.php` — which is slower but fully functional. A missing route
> cache is a performance concern, not an outage.
>
> **Decision for you:** whether to include `route:cache` in the deploy script, or to leave it
> out and accept the small per-request overhead. Leaving it out is the lower-risk option for an
> MVP and costs very little.

**Optional — if you have verified route caching on the target server:**

```bash
php artisan route:cache
php artisan route:list    # confirm the table still lists correctly
```

### 3.9 Complete Deployment Sequence (Copy/Paste)

**NEW production environment:**

```bash
cd /var/www/kudosly
git clone <your-repository-url> . && git checkout <branch-or-tag>
cp .env.example .env
php artisan key:generate                    # NEW environment only
# >>> Edit .env per Section 4 <<<
composer install --no-dev --optimize-autoloader
npm ci && npm run build
test -f public/build/manifest.json || { echo "BUILD FAILED"; exit 1; }
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=PlanSeeder
php artisan config:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

**EXISTING installation:**

```bash
cd /var/www/kudosly
# >>> BACK UP FIRST — Section 5.1 <<<
git fetch --all && git checkout <branch-or-tag>
# .env is preserved. Do NOT regenerate APP_KEY. Section 2.2.
composer install --no-dev --optimize-autoloader
npm ci && npm run build
test -f public/build/manifest.json || { echo "BUILD FAILED"; exit 1; }
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=PlanSeeder
php artisan config:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 3.10 Rolling Back a Deployment

If a deploy fails **after** the code was checked out but **before** migrations ran:

```bash
git checkout <the-previous-branch-or-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan view:cache
```

If migrations already ran, see [Section 5.3](#53-rollback-procedure).

---

## 4. Production Environment Variable Checklist

Work through this list. Every value must be set in the production `.env` on the server.

### 4.1 Core Application

| Variable | Required value | Why |
|---|---|---|
| `APP_NAME` | `Kudosly` | Used in emails, the browser tab, and the Vite asset manifest |
| `APP_ENV` | `production` | Disables debug tooling, enables `migrate --force`, affects error rendering |
| `APP_DEBUG` | `false` | **Critical.** `true` in production leaks stack traces, file paths, and `.env` contents on any exception |
| `APP_URL` | `https://your-domain.com` | Used to generate URLs: password-reset links, Stripe return URLs, `route()` helpers. Also used by `config/filesystems.php` to build the `/storage` URL |
| `APP_KEY` | `base64:...` | **New environment:** generate with `php artisan key:generate`. **Existing:** copy verbatim, never regenerate — see [Section 2.2](#22-app_key) |
| `APP_MAINTENANCE_DRIVER` | `file` (default) or `cache` | Fine at the default. Use `cache` with a shared store if you run multiple app servers |
| `APP_LOCALE` | `en` | Matches the shipped translations |
| `BCRYPT_ROUNDS` | `12` (default) | Reasonable password hashing cost. Do not lower |

### 4.2 HTTPS and Secure Session Cookies

| Variable | Required value | Why |
|---|---|---|
| `APP_URL` | `https://your-domain.com` | Must be `https`, not `http` |
| `SESSION_SECURE_COOKIE` | `true` | **Critical.** Without it the session cookie is sent over plain HTTP. Note there is **no default** in `config/session.php` — the key must be present in `.env` |
| `SESSION_DRIVER` | `database` (default) | The `sessions` table exists. Use `redis`/`memcached` for multi-server |
| `SESSION_LIFETIME` | `120` (default) | Minutes of idle time |
| `SESSION_SAME_SITE` | `lax` (default) | Already correct. Leave as-is |
| `SESSION_ENCRYPT` | `false` (default) | Acceptable — the session payload is not sensitive. `true` is safer at a small CPU cost |
| `SESSION_DOMAIN` | unset / `null` | Leave unset so the cookie is limited to the exact host. Set it only if the app and embeds share a parent domain |

Additionally, the web server must **redirect HTTP to HTTPS** (see [Section 1.3](#13-web-server-configuration)),
and if the app is behind a proxy, `bootstrap/app.php` must call `trustProxies()` so Laravel knows
the request was HTTPS — **this application change is still outstanding**, see
[Section 8](#8-outstanding-prerequisites-requires-your-action).

### 4.3 Email (SMTP)

> **⚠️ Without a working mailer, the password-reset feature does not work.**
> With `MAIL_MAILER=log` (the current default), Laravel writes the reset URL into
> `storage/logs/laravel.log` and never emails it to anyone.

| Variable | Required value | Notes |
|---|---|---|
| `MAIL_MAILER` | `smtp` | Or `ses`, `postmark`, `resend` if you prefer a hosted API. **Not** `log` |
| `MAIL_HOST` | your provider's SMTP host | e.g. `smtp.your-provider.com` |
| `MAIL_PORT` | `587` | `465` if the provider requires implicit TLS |
| `MAIL_USERNAME` | your SMTP username | |
| `MAIL_PASSWORD` | your SMTP password | Real secret — never commit |
| `MAIL_ENCRYPTION` | `tls` | If your provider needs it |
| `MAIL_FROM_ADDRESS` | a real, sendable address on your domain | e.g. `noreply@your-domain.com` |
| `MAIL_FROM_NAME` | `Kudosly` | |

The application sends exactly one kind of email: the **password reset link**. Nothing else
currently uses the mailer.

**Verify before launch:**

```bash
php artisan tinker --execute="Mail::raw('Kudosly mail test', fn(\$m) => \$m->to('you@yourdomain.com')->subject('Kudosly mail test'));"
```

Check that the message arrives, and check the sending domain's SPF, DKIM, and DMARC records.

### 4.4 Stripe and the $999/year Pro Price ID

| Variable | Required value | Notes |
|---|---|---|
| `STRIPE_KEY` | `pk_live_...` | Publishable key |
| `STRIPE_SECRET` | `sk_live_...` | Secret key — real secret, never commit |
| `STRIPE_WEBHOOK_SECRET` | `whsec_...` | **Critical for billing.** See below |
| `CASHIER_CURRENCY` | `usd` | Already the default |
| `CASHIER_CURRENCY_LOCALE` | `en` | Already the default |

**The Pro plan's Stripe price ID lives in the database, not in `.env`.**

Create a **recurring yearly** price of **$999.00 USD** in your Stripe account, then set the
price ID directly:

```sql
UPDATE plans SET stripe_price_id = 'price_XXXXXXXXXXXXXX' WHERE slug = 'pro';
```

Verify:

```bash
php artisan tinker --execute="echo \App\Models\Plan::where('slug','pro')->first()->stripe_price_id;"
```

> **⚠️ Do not put the price ID in `.env`.** `App/Billing/PlanCatalog.php::stripePriceId()`
> reads it from the `plans` table and throws a `RuntimeException` if it is missing — the
> application refuses to start a broken checkout rather than charging the wrong amount.
>
> **Do not rely on `db:seed` to set it.** `PlanSeeder` deliberately omits `stripe_price_id`
> from its update values, precisely so that re-running the seeder cannot erase a price ID
> you have already configured. Set it with the SQL above.
>
> The Free plan needs **no** price ID — `stripe_price_id` stays `NULL` for `slug = 'free'`.

**Webhook configuration in the Stripe dashboard.** Point a webhook endpoint at:

```
https://your-domain.com/stripe/webhook
```

Subscribe it to these events (they are the ones `app/Billing/WebhookHandler.php` handles):

```
checkout.session.completed
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
invoice.paid
invoice.payment_failed
```

Copy the signing secret into `STRIPE_WEBHOOK_SECRET`.

> **Why the webhook secret is critical.** `StripeWebhookController` returns **HTTP 503** when
> `STRIPE_WEBHOOK_SECRET` is empty — it refuses to process anything it cannot verify, rather
> than trusting an unverified payload. Stripe will retry a 503 and mark the endpoint as
> failing after repeated attempts. With no secret, **Pro is never granted**, even when a
> customer pays successfully.
>
> Plan entitlement is written **only** by a verified `customer.subscription.*` event — never by
> the Checkout success redirect — so a broken webhook means paying customers stay on Free.
>
> You can verify webhook delivery in production with:
> ```bash
> php artisan tinker --execute="echo \App\Models\StripeWebhookEvent::count();"
> ```
> and in the Stripe dashboard under **Developers → Webhooks → Recent events**.

### 4.5 Logging

| Variable | Required value | Why |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Keep the stack so channels can be swapped without editing config |
| `LOG_STACK` | `daily` | **Important.** The `single` channel writes to one unbounded file with no rotation. The `daily` channel rotates once per day and retains `LOG_DAILY_DAYS` files |
| `LOG_DAILY_DAYS` | `14` (default) | Days of rotated logs to keep |
| `LOG_LEVEL` | `info` | `debug` in production fills the disk with noise and can leak internals into log files |
| `LOG_DEPRECATIONS_CHANNEL` | `null` (default) | Fine to leave |

> **Why this matters.** The `single` channel has **no size cap and no rotation**. The
> development log file reached **38 MB** from test traffic alone. In production, an
> unrotated log will eventually fill the disk and take the site down.
>
> `LOG_STACK=daily` is the single-line fix. For real monitoring, ship logs off the server
> (Laravel Cloud, Papertrail, or a `stderr` channel into systemd/journald) — see
> [Section 7](#7-scheduled-tasks-and-ongoing-maintenance).

### 4.6 Queue

| Variable | Required value | Why |
|---|---|---|
| `QUEUE_CONNECTION` | `database` (default) **or** `sync` | See the decision note below |
| `DB_QUEUE` | `default` | Queue name |
| `DB_QUEUE_RETRY_AFTER` | `90` (default) | Seconds before a job is retried. Must exceed the longest job's runtime |

> **Decision required — `database` vs `sync`.**
>
> This application currently **dispatches no queued jobs**: there is no `app/Jobs` directory,
> no `ShouldQueue` job, and no `dispatch()` call anywhere in `app/`. The Stripe webhook is
> handled **synchronously** inside the HTTP request.
>
> - **`database`** — the default, and already the value in `.env`. Requires a running
>   `queue:work` worker so that any job dispatched in future is actually processed. Without a
>   worker, jobs accumulate in the `jobs` table forever and `failed_jobs` is never drained.
> - **`sync`** — runs any job immediately inside the request. Simpler, no worker to supervise,
>   and the right choice for an MVP with no queued work. The cost is that a slow job blocks
>   the user's request.
>
> **Recommendation:** stay with `database` and run a supervised worker (see
> [Section 7](#7-scheduled-tasks-and-ongoing-maintenance)), because it is the configuration the
> project is already set up for and it is what Cashier expects for invoice rendering. If you
> would rather not supervise a worker, set `QUEUE_CONNECTION=sync` — there is no queued work
> that would be lost.

### 4.7 Cache, Filesystem, and Database

| Variable | Required value | Why |
|---|---|---|
| `CACHE_STORE` | `database` (default) | The `cache` table exists. Use `redis` for multi-server |
| `FILESYSTEM_DISK` | `local` (default) | The `local` disk is `storage/app/private` and is **not** web-accessible. Keep it |
| `DB_CONNECTION` | `mariadb` or `mysql` | Both drivers are configured in `config/database.php` |
| `DB_HOST` | your database host | `127.0.0.1` if MySQL is on the same server |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | your database name | |
| `DB_USERNAME` | a **dedicated** database user | **Do not use `root`** in production |
| `DB_PASSWORD` | a strong random password | Real secret — never commit |
| `DB_CHARSET` | `utf8mb4` (default) | |
| `DB_COLLATION` | `utf8mb4_unicode_ci` (default) | |

> **Use a dedicated MySQL user with only the privileges this application needs:**
> `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES` on the one database.
> Migrations need `CREATE`/`ALTER`/`DROP`; a runtime-only user needs far less.

### 4.8 Quick Verification of the Production `.env`

```bash
php artisan about --only=environment
```

Check that `Environment` reports **production** and that debug is off. Then confirm no secrets
are staged for commit:

```bash
git status --short
```

`.env` must **not** appear in the output.

---

## 5. Database Backup, Restore, and Rollback

### 5.1 Backup Procedure

> **On an EXISTING installation, this is mandatory before every deployment.** An untested
> backup is not a backup — verify a restore ([5.2](#52-restore-procedure)) at least once before
> you trust it.

**Before a deployment:**

```bash
mkdir -p /var/backups/kudosly
STAMP=$(date +%Y%m%d-%H%M%S)
mysqldump --single-transaction --quick --routines --triggers \
    --default-character-set=utf8mb4 \
    -u kudosly -p'YOUR_DB_PASSWORD' \
    kudosly | gzip > /var/backups/kudosly/pre-deploy-${STAMP}.sql.gz
```

`--single-transaction` gives a consistent snapshot without locking the tables, so the site keeps
serving traffic while the dump runs.

**Daily automated backup** (cron, `0 3 * * *`):

```bash
0 3 * * * mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -u kudosly -p'YOUR_DB_PASSWORD' kudosly | gzip > /var/backups/kudosly/daily-$(date +\%F).sql.gz && find /var/backups/kudosly -name 'daily-*.sql.gz' -mtime +14 -delete
```

**Retention:** keep at least 7 daily and 4 weekly copies. Store copies **off the server** (object
storage or a different machine) — a backup on the same disk as the database does not survive a
disk failure.

> The repository's `.gitignore` already excludes `*.sql` and `*.sqlite`, so database dumps must
> never be placed inside the project directory.

**Verify a backup is non-empty:**

```bash
ls -lh /var/backups/kudosly/
gzip -t /var/backups/kudosly/daily-2026-01-01.sql.gz && echo "archive OK"
```

### 5.2 Restore Procedure

> **Practice this on a scratch database before you need it.** Restoring into production is the
> scenario in which you least want to be reading these instructions for the first time.

**Restoring into a scratch database (safe, recommended rehearsal):**

```bash
mysql -u root -p -e "CREATE DATABASE kudosly_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c /var/backups/kudosly/daily-2026-01-01.sql.gz \
    | mysql -u root -p kudosly_restore_test

mysql -u root -p -e "USE kudosly_restore_test; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM testimonials; SELECT slug, max_spaces FROM plans;"
```

**Restoring the production database (destructive — requires an explicit decision):**

```bash
# 1. Put the application into maintenance mode first.
php artisan down --render="errors::503" --retry=60

# 2. Drop and recreate the schema.
mysql -u root -p -e "DROP DATABASE kudosly; CREATE DATABASE kudosly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Load the backup.
gunzip -c /var/backups/kudosly/daily-2026-01-01.sql.gz | mysql -u root -p kudosly

# 4. Re-apply anything that ran after the backup was taken.
php artisan migrate --force

# 5. Bring the site back.
php artisan up
```

> **⚠️ A restore replaces everything written since the backup.** Testimonials submitted by
> customers, spaces, widgets, and subscription state are all lost back to the backup point.
> Take a fresh backup immediately before restoring, so the lost window is as small as possible.

### 5.3 Rollback Procedure

**Case A — the code is broken, but the database schema is unchanged:**

```bash
cd /var/www/kudosly
git checkout <the-previous-known-good-tag-or-branch>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan view:cache
```

No database action is needed. This is the common case and it is safe.

**Case B — a migration ran and must be undone:**

```bash
# Preview what would be rolled back.
php artisan migrate:status

# Roll back the last batch (or a specific step).
php artisan migrate:rollback --step=1 --force
```

> **⚠️ `migrate:rollback` is not always a true inverse.** A `rollback` that drops a column
> **destroys the data in that column**. For this application specifically, rolling back
> `add_plan_fields_to_users_table` or `create_customer_columns` would destroy the billing and
> plan data for every user.
>
> **For any deployment that touches an existing installation with real customers, prefer
> restoring from a backup** ([Section 5.2](#52-restore-procedure)) over rolling back. A
> migration rollback is a schema operation, not a data recovery operation.

**Case C — the deployment partially succeeded (migrations ran, new code broken):**

1. Restore the previous code (Case A).
2. Leave the schema as-is if the new migrations are backward compatible — they are additive
   column/table creations, so the previous code will run against the newer schema without issue.
3. Only roll back the schema if the new code genuinely depends on a *removed* column. No
   migration in this application removes a column.

---

## 6. Post-Deployment Smoke Tests

Run every check. A deploy is not complete until all of them pass.

### 6.1 Application Health

```bash
# 1. The health endpoint returns 200.
curl -sS -o /dev/null -w "%{http_code}\n" https://your-domain.com/up
#    Expected: 200

# 2. The landing page renders (this is the page that breaks if the Vite build is missing).
curl -sS -o /dev/null -w "%{http_code}\n" https://your-domain.com/
#    Expected: 200

# 3. The application is in production mode.
php artisan about --only=environment
#    Expected: environment = production, debug = off

# 4. There are no cached-config or route-cache problems.
php artisan about
#    Expected: no error in the "Cache" section
```

**If `/` returns 500**, check `storage/logs/laravel.log` first — a `ViteManifestNotFoundException`
means [Section 3.3](#33-verify-the-asset-build--critical-step) did not complete.

### 6.2 Assets and Styling

Open the landing page in a browser with the developer console open.

- [ ] The page is **styled** — Tailwind CSS applied, not raw unstyled HTML
- [ ] The browser console shows **no errors** (no 404s for `assets/app-*.css`)
- [ ] The web fonts render (Instrument Sans) and the Network tab shows `200` for the font files
- [ ] `curl -sSI https://your-domain.com/build/assets/app-XXXX.css` returns `200` — the CSS is
      served directly by the web server from `public/build/assets/`

### 6.3 Authentication and Core Flow

Run these in a browser against the production URL.

- [ ] **Register** a new account → you are redirected to the dashboard
- [ ] The new account is on the **Free** plan (the upgrade banner is visible)
- [ ] **Log out**, then **log in** again → the dashboard loads
- [ ] **Create a Space** (name, slug, title, ask) → the success screen shows the public URL
- [ ] The public URL `/s/{slug}` loads **without** logging in
- [ ] **Submit a testimonial** on the public page → the "You're officially awesome" screen appears
- [ ] The testimonial appears in the **Inbox** as *pending* — it is not yet public
- [ ] **Approve** it, then open the Space's widget preview → the testimonial appears
- [ ] **Password reset**: request a reset for the account's email → **the email arrives**
- [ ] Follow the link, set a new password, and log in with it
- [ ] The public wall of love `/wall/{slug}` renders

### 6.4 Widget Embeds

The widget embed is used by paying customers on **their own** websites, so a broken embed is a
customer-facing outage that no internal health check would catch.

- [ ] On the Widgets page, copy the **iframe** snippet and paste it into a scratch HTML page
- [ ] The widget renders with testimonials
- [ ] The iframe **resizes** to fit its content (the height changes as testimonials are added
- [ ] Repeat with the **JavaScript** snippet on a page served from a **different domain** — the
      widget must still load. This is the cross-origin case that was verified in Chromium
      during development
- [ ] The host page's own styles are **not** affected by the embed
- [ ] On a mobile viewport (375 px wide) the widget is responsive and the page does not scroll
      sideways

### 6.5 Billing and Stripe

> **Requires live Stripe credentials.** Skip only if Stripe is not yet configured — in that case
> the Pro upgrade path is **not** production-ready and must not be advertised.

- [ ] The Billing page shows **Free** with the correct usage counters
- [ ] Click **Upgrade to Pro** → you land on a Stripe Checkout page showing **$999.00 USD / year**
- [ ] Complete the payment with a Stripe test card
- [ ] You are redirected back to the success page
- [ ] **The account is still on Free immediately after the redirect** — entitlement must come
      from the webhook, not the redirect
- [ ] The Stripe dashboard shows a **successful** webhook delivery to
      `https://your-domain.com/stripe/webhook`
- [ ] Within a few seconds the account becomes **Pro** and the upgrade banner disappears
- [ ] The Billing page shows the Pro subscription and a **Manage subscription** link that opens
      the Stripe customer portal
- [ ] `php artisan tinker --execute="echo \App\Models\StripeWebhookEvent::count();"` increases

### 6.6 Data Integrity Check

> **Run this on an EXISTING installation only.** On a new environment there is no prior data to
> compare against.

```bash
php artisan tinker --execute="
echo 'users: '.\App\Models\User::count().PHP_EOL;
echo 'spaces: '.\App\Models\Space::count().PHP_EOL;
echo 'testimonials: '.\App\Models\Testimonial::count().PHP_EOL;
echo 'widgets: '.\App\Models\Widget::count().PHP_EOL;
echo 'subscriptions: '.\Laravel\Cashier\Subscription::count().PHP_EOL;
"
```

Compare the counts against a dump taken **before** the deployment. Nothing should have decreased.
A drop means a migration or a seeder touched production data — investigate immediately.

Also confirm the plans are correct:

```bash
php artisan tinker --execute="foreach(\App\Models\Plan::all() as \$p) { echo \$p->slug.' | '.\$p->max_spaces.' | '.\$p->max_testimonials_per_space.' | '.\$p->price.' | '.(\$p->stripe_price_id ?? 'no-price-id').PHP_EOL; }"
```

Expected:

```
free | 3 | 100 | 0 | no-price-id
pro  | 25 | 1000 | 99900 | price_XXXXXXXXXXXXXX
```

### 6.7 Logs and Health

```bash
# No errors written since the deploy.
tail -n 100 storage/logs/laravel-$(date +%Y-%m-%d).log

# Disk space is healthy.
df -h /var/www/kudosly

# Log rotation is working — old files exist and are dated.
ls -la storage/logs/
```

- [ ] No `ERROR` or `CRITICAL` entries since the deploy
- [ ] Disk usage is comfortable
- [ ] Rotated log files exist (confirming `LOG_STACK=daily` took effect)

### 6.8 Backup Verification

```bash
ls -lh /var/backups/kudosly/
gzip -t /var/backups/kudosly/<latest-file>.sql.gz && echo "archive OK"
```

- [ ] A backup was taken **before** the deployment
- [ ] The archive is non-empty and passes the integrity check
- [ ] At least one **off-site** copy exists

---

## 7. Scheduled Tasks and Ongoing Maintenance

### 7.1 Cron Entries

The application currently defines **no scheduled tasks** (`routes/console.php` contains only the
stock `inspire` command). The cron entries below are therefore **housekeeping**, not features.

```cron
# Laravel scheduler — required if you add scheduled work later.
* * * * * cd /var/www/kudosly && php artisan schedule:run >> /dev/null 2>&1

# Database backup — daily at 03:00.
0 3 * * * cd /var/www/kudosly && mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -u kudosly -p'YOUR_DB_PASSWORD' kudosly | gzip > /var/backups/kudosly/daily-$(date +\%F).sql.gz && find /var/backups/kudosly -name 'daily-*.sql.gz' -mtime +14 -delete
```

Note the escaped `\%` — cron treats `%` as a newline, so it must be escaped inside `$( )`.

### 7.2 Queue Worker

> **Required only if `QUEUE_CONNECTION=database`.** See the decision in
> [Section 4.6](#46-queue). The application currently dispatches no jobs, so this is
> forward-looking.

**systemd unit** — `/etc/systemd/system/kudosly-worker.service`:

```ini
[Unit]
Description=Kudosly queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/kudosly
ExecStart=/usr/bin/php artisan queue:work --queue=default --tries=3 --max-time=3600 --sleep=3 --verbose

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now kudosly-worker
```

**Restart the worker after every deploy** so it picks up new code:

```bash
sudo systemctl restart kudosly-worker
# or, for a Supervisor-managed worker:
php artisan queue:restart
```

### 7.3 Database Table Maintenance

The `failed_jobs` and `job_batches` tables are created by migration but nothing prunes them. On
an existing installation they can grow indefinitely.

Add to `routes/console.php` (**requires a code change — not part of this document's scope**):

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('queue:prune-batches --hours=168')->daily();
```

Until that is added, prune manually:

```bash
php artisan queue:prune-failed --hours=168
php artisan queue:prune-batches --hours=168
```

### 7.4 Ongoing Checklist

| Frequency | Task |
|---|---|
| Every deploy | Backup → deploy → smoke test ([Section 6](#6-post-deployment-smoke-tests)) |
| Every deploy | `php artisan queue:restart` (if a worker is running) |
| Daily | Automated database backup |
| Weekly | Verify a backup restores into a scratch database |
| Weekly | Review `storage/logs/` for errors and confirm rotation |
| Monthly | Review `queue:failed` (`php artisan queue:failed`) |
| Monthly | Confirm the TLS certificate is valid and auto-renewing |
| Monthly | Review Stripe webhook delivery logs in the Stripe dashboard |
| Quarterly | Rotate database credentials and any API keys |

---

## 8. Outstanding Prerequisites (Requires Your Action)

The following must be resolved by you before the deployment steps in
[Section 3](#3-deployment-sequence) can succeed. **None of them can be automated from the
repository**, and this document does not create any of them.

### 8.1 Hosting

- [ ] **Choose a hosting provider.** A VPS (DigitalOcean, Linode, Vultr, Hetzner, AWS EC2) is
      the most straightforward for this application — it gives you root access, cron, and a
      supervisor/systemd. Shared hosting (cPanel) works but makes the cron, worker, and
      `storage:link` steps more awkward.
- [ ] **Server sized for an MVP:** 1 vCPU, 1–2 GB RAM, 25 GB+ SSD. PHP 8.3+, MySQL 8.0+, Nginx.
- [ ] **Point the domain's DNS A record** at the server's IP address.
- [ ] **Install and configure Nginx** with the document root at `public/` and the PHP-FPM
      socket — see [Section 1.3](#13-web-server-configuration).
- [ ] **Obtain a TLS certificate** (Let's Encrypt via `certbot` is free and automatic).
- [ ] **Configure the HTTP → HTTPS redirect.**
- [ ] **Create a dedicated MySQL database and user** with only the privileges listed in
      [Section 4.7](#47-cache-filesystem-and-database). Do not use `root`.

### 8.2 Domain and Application Identity

- [ ] **Choose the production domain** (e.g. `your-domain.com`).
- [ ] **Decide the `APP_NAME`** — `Kudosly` is recommended, since it appears in emails and the
      browser tab.
- [ ] **Register the sending domain** for email (SPF, DKIM, DMARC) — see
      [Section 8.3](#83-email-provider).
- [ ] **Confirm the widget embed origins.** The JavaScript embed builds its iframe URL from the
      loader's own origin, so it works on any customer website — but the dashboard's embed-code
      snippets must show your production domain, which they will once `APP_URL` is set to
      `https://your-domain.com`.

### 8.3 Email Provider

- [ ] **Choose an SMTP provider** — any of: Mailgun, Postmark, SendGrid, Amazon SES, or a
      plain SMTP relay. Fastmail/SMTP2GO also work.
- [ ] **Create the mailbox** that will send, e.g. `noreply@your-domain.com`.
- [ ] **Obtain the SMTP host, port, username, and password.**
- [ ] **Configure SPF, DKIM, and DMARC** for the sending domain.
- [ ] **Fill in the `MAIL_*` variables** — [Section 4.3](#43-email-smtp).
- [ ] **Send a test email** and confirm it arrives and is not in spam.

> Without this, the password-reset feature is non-functional for real users.

### 8.4 Stripe Account

- [ ] **Create a Stripe account** and complete business verification if required.
- [ ] **Switch to live mode** once test mode is working.
- [ ] **Create a Product** named "Kudosly Pro".
- [ ] **Create a recurring price of $999.00 USD per year** (billing period: **yearly**). PRD §11
      and §19.12 fix this price — do not change it without approval.
- [ ] **Copy the price ID** (`price_...`).
- [ ] **Set the price ID in the database:**
      `UPDATE plans SET stripe_price_id = 'price_...' WHERE slug = 'pro';`
- [ ] **Obtain the live API keys** (`pk_live_...`, `sk_live_...`).
- [ ] **Create a webhook endpoint** at `https://your-domain.com/stripe/webhook` and subscribe it
      to the six events listed in [Section 4.4](#44-stripe-and-the-999year-pro-price-id).
- [ ] **Copy the webhook signing secret** (`whsec_...`) into `STRIPE_WEBHOOK_SECRET`.
- [ ] **Verify** that `App\Models\Plan::where('slug','pro')->first()->stripe_price_id` is not null.

### 8.5 Application Changes That Are Still Outstanding

These are **not** environment configuration — they require changes to the application. They are
outside the scope of this document, but they will affect the production behaviour.

| # | Change | Why it matters | Priority |
|---|---|---|---|
| 1 | **`trustProxies()` in `bootstrap/app.php`** | If the app sits behind a reverse proxy or CDN, Laravel will not know it is serving HTTPS. Password-reset links and Stripe return URLs would be generated as `http://`, and the rate limiter would key on the proxy's IP instead of the client's. **Required unless the app is reached directly over HTTPS with no proxy.** | High if a proxy/CDN is used; otherwise optional |
| 2 | **Scheduler entries** in `routes/console.php` for `queue:prune-failed` and `queue:prune-batches` | Without them, `failed_jobs` and `job_batches` grow indefinitely | Medium |
| 3 | **A `TrustHosts` / `AllowedHosts` allow-list** | Laravel 11+ does not validate the `Host` header by default. Adding `->withMiddleware(fn($m) => $m->trustHosts(at: [...]))` prevents host-header poisoning in generated URLs | Medium |

### 8.6 Decisions Needed From You

| # | Decision | Recommendation |
|---|---|---|
| 1 | **Route caching** — include `route:cache` in the deploy script? | It has been verified to work today. But it must be re-verified on every deploy because a future route change could break it. **Lowest risk: leave it out.** The performance difference is small for an MVP. |
| 2 | **Queue** — `database` + a supervised worker, or `sync`? | **Stay with `database`** and run the systemd worker. It matches the current configuration, Cashier expects it, and it is ready for future queued work. |
| 3 | **`APP_ENV` / `APP_DEBUG`** | `production` / `false`. No exceptions. |
| 4 | **Backup retention and off-site storage** | 7 daily + 4 weekly, with at least one copy off the server. Needs an object-storage bucket. |
| 5 | **Monitoring** — logs only, or logs + uptime checks? | At minimum: uptime monitoring against `https://your-domain.com/up`, plus daily log shipping. Consider Sentry or Laravel Cloud for exception tracking. |
| 6 | **Whether to run the app behind a CDN/proxy** | If yes, change #1 in [8.5](#85-application-changes-that-are-still-outstanding) becomes mandatory, and you must forward `X-Forwarded-Proto` and `X-Forwarded-For`. |

---

## 9. Appendix — Notes and Verified Facts

These are facts about the current codebase that were checked while writing this document. They
are recorded here so a future reader does not have to re-derive them.

### 9.1 Facts that were verified by inspection

- **No `env()` calls outside `config/`.** `app/`, `bootstrap/`, `routes/`, and
  `resources/views/` were all searched. This means `php artisan config:cache` is safe — no
  environment value is baked into the cached config incorrectly.
- **Route caching works.** `php artisan route:cache` completed successfully and the resulting
  cached route table resolved correctly (`GET /up` and `POST stripe/webhook` both present). The
  `Route::get('/', function () { ... })` closure in `routes/web.php:21` does **not** prevent it.
  The cache was cleared afterwards, leaving the repository unchanged.
- **`GET /up` is registered** (via `bootstrap/app.php` → `health: '/up'`) and is usable as an
  uptime probe.
- **All 18 migrations are present** and cover every table in PRD §13, plus Cashier's
  `subscriptions`/`subscription_items` and Laravel's `sessions`/`cache`/`jobs`/`failed_jobs`.
- **`public/build` is gitignored** and is not in the repository — hence
  [Section 3.3](#33-verify-the-asset-build--critical-step).
- **`public/storage` is gitignored** — hence [Section 3.6](#36-create-the-storage-symlink).
- **`DatabaseSeeder` creates a `Test User`.** Only `PlanSeeder` is safe to run against
  production — hence [Section 2.5](#25-planseeder--required-on-both-new-and-existing-installations).
- **`PlanSeeder` never writes `stripe_price_id`.** The price ID must be set by hand — hence
  [Section 4.4](#44-stripe-and-the-999year-pro-price-id).
- **`bootstrap/cache/` contains only `packages.php` and `services.php`.** There is no stale
  `config.php` or `routes-v7.php` in the repository.
- **`.env` is gitignored and untracked**, and `.env.example` contains no secrets.
- **The application dispatches no queued jobs** — there is no `app/Jobs` directory, no
  `ShouldQueue` job, and no `dispatch()` call in `app/`.
- **The Stripe webhook is handled synchronously**, inside the HTTP request.
- **`storage/logs/laravel.log` had reached 38 MB** in development under `LOG_STACK=single`.
- **`resources/js/app.js` is a 3-byte placeholder.** All interactivity is Livewire, so the
  compiled `app-*.js` is legitimately 0 bytes.

### 9.2 Things this document deliberately does not do

- It does not contain any real secret. Every credential is a placeholder.
- It does not create a Stripe account, a price, a webhook endpoint, or an SMTP mailbox.
- It does not execute any deployment step, migration, seeder, or database write.
- It does not modify `.env`, `phpunit.xml`, `PRD.md`, or any application code.
- It does not run the test suite. The last full run was **610 passed, 0 failed**.

### 9.3 Source of truth

When this document and the code disagree, the code wins. Re-verify the facts in
[Section 9.1](#91-facts-that-were-verified-by-inspection) after any change to the route table,
the seeders, or the Vite configuration.
