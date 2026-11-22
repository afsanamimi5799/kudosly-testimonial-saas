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

### Sub-sections

| Section | Covers |
|---|---|
| [1.4](#14-filesystem-permissions) | Filesystem permissions, the deploy-user vs web-server-user ownership strategy, the `.env` protection, and the directories that must exist before any Artisan command |
| [2.6](#26-rehearsing-the-deployment-in-an-isolated-sandbox) | Rehearsing the deployment in a disposable sandbox — **security warning**, procedure, and coverage limits |
| [3.3](#33-verify-the-asset-build--critical-step) | Verifying the Vite asset build |
| [3.8](#38-route-caching--verify-before-enabling) | Route caching: how it works in Laravel 13, and how to verify it properly |
| [3.9](#39-complete-deployment-sequence-copypaste) | The complete copy/paste deployment sequence |
| [5.2.1](#521-scratch-database-restore-default--safe-non-destructive) | Restoring into a **scratch** database — the default, non-destructive restore and backup-verification procedure |
| [5.2.2](#522-production-restore-disaster-recovery-only) | Restoring the **production** database — exceptional disaster recovery, gated on explicit approval |
| [9.2](#92-what-the-isolated-sandbox-rehearsal-established--and-what-it-did-not) | What the sandbox rehearsal established, and what it did **not** |

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

**Do this before any Artisan command runs.** Two distinct identities need write access to a
small, specific set of runtime directories, and they are not always the same user:

| Identity | Needs write access because |
|---|---|
| **The deployment user** (the one running `git`, `composer`, `npm`, and `php artisan`) | It runs `migrate`, `config:cache`, `route:cache`, `view:cache`, and `storage:link` |
| **The PHP runtime user** (the identity PHP-FPM workers actually run as — commonly `www-data`, but also `apache`, `_www`, or a custom pool user) | It serves every request, writes session/cache files, compiled views, and logs, and stores uploaded photos |

> **Do not assume the PHP runtime user is `www-data`.** It depends on the platform and on the
> pool configuration — `www-data` on Debian/Ubuntu, `apache` on RHEL/CentOS, `_www` on
> Alpine, and often a custom pool user on managed hosts. **Identify it before setting any
> ownership**, using the commands in the `.env` section below. Setting the group to the wrong
> user leaves the application unable to read its own configuration.

If the deployment user and the PHP runtime user are the same user, a single `chown` is enough.
If they differ — which is the normal case, because the deploy user has SSH access and the
runtime user does not — then **both** users need write access to the two runtime trees, and
nothing else.

> **The rule: write access goes to `storage/` and `bootstrap/cache/` only.**
> `app/`, `config/`, `resources/`, `routes/`, `database/`, `vendor/`, `.git/`, and the project
> root stay **read-only to the web server**. If a compromised web-server process could write to
> `config/` or `vendor/`, it could rewrite application code or plant a backdoor that survives
> the next deployment. The web server needs to *read* those, not modify them.
>
> `storage/` and `bootstrap/cache/` are the only trees Laravel writes to at runtime.

**The wrong way** — breaks the deployment user, and over-grants to the web server:

```bash
# ✗ Do NOT do this.
# (a) The deploy user cannot write storage/ or bootstrap/cache/, so
#     `php artisan migrate` / `config:cache` / `view:cache` fail with
#     "Permission denied" on a fresh server.
# (b) -R 2775 marks every FILE setgid as well, which is meaningless on
#     regular files and needlessly grants group-write to data files.
sudo chown -R www-data:www-data /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache
sudo chmod -R 2775 /var/www/kudosly/storage /var/www/kudosly/bootstrap/cache
```

**The right way — shared group ownership** (recommended):

```bash
APP_DIR=/var/www/kudosly

# 0. Identify the ACTUAL PHP runtime identity. Do not assume "www-data" —
#    it varies by platform and pool configuration.
PHP_USER=$(ps -eo user,comm | awk '$2 ~ /^php/ {print $1; exit}')
PHP_GROUP=$(ps -eo user,group,comm | awk '$3 ~ /^php/ {print $2; exit}')
echo "PHP runtime identity: $PHP_USER:$PHP_GROUP"

# 1. Create a group both users belong to.
sudo groupadd -f kudosly
sudo usermod -aG kudosly "$PHP_USER"
sudo usermod -aG kudosly "$DEPLOY_USER"      # e.g. "deploy" or "ubuntu"

# 2. Ownership: the deploy user owns the tree.
#    Do this BEFORE step 3 — a blanket chown afterwards would undo the
#    group ownership on the two runtime trees.
sudo chown -R "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR"

# 3. Give the group ownership of ONLY the two writable trees.
sudo chown -R root:kudosly "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 4. Runtime DIRECTORIES: group rwx + setgid (2775).
#    The setgid bit belongs on DIRECTORIES: it makes every file created
#    inside inherit the `kudosly` group, so new files stay group-writable.
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} +

# 5. Runtime FILES: group-writable (0664).
#    These files must be writable by the runtime group, not merely by their
#    owner. Laravel updates existing files in place — a compiled view, a
#    session file, a log file — and the PHP worker is NOT the owner, so
#    0644 would break it with "Permission denied".
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0664 {} +

# 6. Both users must be in the group for the above to take effect.
newgrp kudosly        # or log out and back in
```

> **Why runtime files are `0664` and not `0644`.** With `root:kudosly` ownership, `0644`
> gives write access to the **owner only** (`root`). The PHP worker runs as `www-data`, which
> is in the `kudosly` group but is not the owner — so it could read runtime files but **not
> update them**. Laravel writes existing files in place (compiled Blade views in
> `storage/framework/views/`, session files, `storage/logs/laravel.log`), so this would fail at
> runtime with a permission error. `0664` grants the `kudosly` group write access, which is
> exactly the intent: the deploy user and the PHP worker can both maintain these files, and
> no other user can.
>
> **Do not set `0644` on the runtime trees.** If your policy requires the runtime files to be
> group-writable but not world-readable, `0664` is correct: the last digit (`4`) governs
> "other" and leaves it read-only.

**Preserve the executable bit on scripts.** Do **not** apply a blanket
`find … -type f -exec chmod 0644` to the application tree. That would strip the executable
bit from every script, including `vendor/bin/*` (Composer's proxy scripts — `pest`, `carbon`,
`patch-type-declarations`) and any future `bin/` console script. The application tree should
keep the permissions the repository already has.

Instead, **narrow the sweep to the two runtime trees only**, and leave everything else alone:

```bash
APP_DIR=/var/www/kudosly

# Only the runtime trees are normalised. The application tree keeps the
# modes it came with from git, so executable scripts stay executable.
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} +
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0664 {} +
```

> **If you must normalise the application tree** — for example when moving an installation
> whose modes are unknown — preserve the executable bit explicitly rather than flattening
> everything to `0644`:
>
> ```bash
> # Files that are ALREADY executable stay executable (0755); all others
> # become 0644. This keeps vendor/bin/* and bin/ scripts runnable.
> sudo find "$APP_DIR" -type f -perm -u+x -not -path "$APP_DIR/storage/*" \
>      -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 0755 {} +
> sudo find "$APP_DIR" -type f ! -perm -u+x -not -path "$APP_DIR/storage/*" \
>      -not -path "$APP_DIR/bootstrap/cache/*" -not -name '.env' \
>      -exec chmod 0644 {} +
> ```
>
> Directories in the application tree should be `0755` (traversable and readable, but not
> group- or world-writable):
>
> ```bash
> sudo find "$APP_DIR" -type d -not -path "$APP_DIR/storage/*" \
>      -not -path "$APP_DIR/bootstrap/cache/*" -exec chmod 0755 {} +
> ```

**Files created in the future.** The permissions above only cover what exists at the moment
you run them. Laravel, Composer, and the deploy user will keep creating new files. Two
mechanisms keep those correct:

- **Setgid on directories** (done in step 4) makes each new file inherit the `kudosly` group,
  so it is never owned by an unrelated group.
- **A `umask` decides the new file's mode.** The default `022` would create files as `0644` —
  group-readable but **not** group-writable — which would break the PHP worker the first time
  it tried to update one. Set a `umask` of `0002` for the identities that write to the runtime
  trees:

```bash
# In /etc/profile.d/kudosly.sh — applies to interactive shells.
umask 0002

# For the deploy user's shell, and for the PHP-FPM pool, set it explicitly:
#   /etc/php/8.3/fpm/pool.d/www.conf
#       php_admin_value[error_log] = ...
#   systemd units:  UMask=0002
```

With `umask 0002`, a new file is created as `0664` (files) or `0775` (directories) — writable by
the `kudosly` group — provided the parent directory carries the setgid bit, which step 4
already sets.

> **If you cannot control the `umask`** — some managed hosts and systemd units set it
> globally — use **default ACLs** instead. These make the group write access inherited by
> every file created in the directory, regardless of the creating process's `umask`:
>
> ```bash
> sudo setfacl -R -d -m g:kudosly:rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
> ```
>
> The `-d` (default) entries are inherited by everything created afterwards. This is the most
> robust option because it does not depend on which identity creates the file.

**Protect the `.env` file and the `APP_KEY` inside it.** The `.env` holds the application key,
the database password, the mail password, and the Stripe secrets. It must be **readable by the
PHP process** but must not be readable by anyone else:

```bash
# --- 1. Identify the ACTUAL user and group the PHP runtime runs as ---
#     Do not assume "www-data". It is "www-data" on Debian/Ubuntu with
#     Apache or Nginx, "apache" on RHEL/CentOS, "_www" on Alpine/macOS,
#     and often a custom pool user (e.g. "kudosly") on managed hosts.
#
#     Ask the running process:
PHP_USER=$(ps -eo user,group,comm | awk '$3 ~ /^php/ {print $1; exit}')
PHP_GROUP=$(ps -eo user,group,comm | awk '$3 ~ /^php/ {print $2; exit}')
echo "PHP runtime identity: $PHP_USER:$PHP_GROUP"

# Cross-check against the FPM pool configuration, which is authoritative:
grep -rE '^\s*(user|group)\s*=' /etc/php/*/fpm/pool.d/*.conf

# Cross-check the user the web server actually runs as:
ps -eo user,comm | grep -E 'nginx|apache2|httpd' | head -5

# If PHP runs as a pool user of its own (not the web-server user), give that
# user the group too:
sudo usermod -aG kudosly "$PHP_USER"
```

```bash
# --- 2. Set the ownership ---
#     Owner: the deploy user, so the deploy user can manage the file.
#     Group: the PHP runtime group, so PHP can READ it.
#     Mode 0640: the owner can read/write, the group can read, and
#     "other" has NO access at all — not world-readable, not world-writable.
sudo chown "$DEPLOY_USER":"$PHP_GROUP" "$APP_DIR/.env"
sudo chmod 0640 "$APP_DIR/.env"

# --- 3. Verify: the PHP runtime MUST be able to read it ---
sudo -u "$PHP_USER" test -r "$APP_DIR/.env" \
  && echo "OK: the PHP runtime can read .env" \
  || { echo "ABORT: the PHP runtime cannot read .env"; exit 1; }

# --- 4. Verify: the deploy user MUST be able to manage it ---
sudo -u "$DEPLOY_USER" test -w "$APP_DIR/.env" \
  && echo "OK: the deploy user can update .env" \
  || { echo "ABORT: the deploy user cannot update .env"; exit 1; }

# --- 5. Verify: nobody else may read it ---
sudo -u nobody test -r "$APP_DIR/.env" \
  && { echo "ABORT: .env is readable by unprivileged users"; exit 1; } \
  || echo "OK: .env is not readable by unprivileged users"

# --- 6. Verify the mode is exactly what you expect ---
ls -l "$APP_DIR/.env"
# Expected: -rw-r----- 1 <deploy-user> <php-group> ... .env
```

> **Do not set `.env` to `0644` or `0666`.** `0644` would make the application key, the
> database password, the mail password, and the Stripe secrets readable by every user on the
> machine. `0666` would additionally make it writable by everyone, so any local process could
> change your database credentials. `0640` with the PHP runtime as the group is the tightest
> mode that still lets the application boot.

> **Note on the group used for `.env`.** Granting the **PHP runtime group** read access is
> the standard arrangement, because the PHP worker must read the configuration to boot. If
> your policy requires a tighter boundary, create a dedicated group containing the deploy user
> and the PHP runtime user, and use that instead:
>
> ```bash
> sudo groupadd -f kudosly-secrets
> sudo usermod -aG kudosly-secrets "$DEPLOY_USER"
> sudo usermod -aG kudosly-secrets "$PHP_USER"
> sudo chown "$DEPLOY_USER":"kudosly-secrets" "$APP_DIR/.env"
> sudo chmod 0640 "$APP_DIR/.env"
> ```
>
> The `.env` is **never** inside `storage/` or `bootstrap/cache/`, so it is never given
> group-*write* access by the runtime-tree rules above. The group-*read* grant is the minimum
> the application needs.

**The right way — ACLs** (when you would rather not change group membership):

```bash
APP_DIR=/var/www/kudosly
PHP_USER=$(ps -eo user,comm | awk '$2 ~ /^php/ {print $1; exit}')

sudo chown -R "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR"

# The runtime trees: full rwx for the PHP runtime identity, plus default
# ACLs so future files inherit it regardless of the creating umask.
sudo setfacl -R -m u:"$PHP_USER":rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo setfacl -R -d -m u:"$PHP_USER":rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# The secrets file: readable by the PHP runtime, writable by the deploy user.
sudo setfacl -m u:"$PHP_USER":r "$APP_DIR/.env"
sudo chown "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR/.env"
sudo chmod 0640 "$APP_DIR/.env"
```

**The required directories must exist before Artisan runs.** Laravel will not create them, and
a missing one produces a confusing failure rather than a clear one — a missing
`storage/framework/views` makes every Artisan command abort with
`InvalidArgumentException: Please provide a valid cache path.` before it can do anything else.
A `git clone` gives you the `.gitignore` placeholders, but only if the checkout succeeded and
the placeholder files were not filtered out:

```bash
# Verify the writable tree exists and has the expected subdirectories.
ls -d /var/www/kudosly/storage/framework/{cache,sessions,views} /var/www/kudosly/storage/logs
ls -d /var/www/kudosly/storage/app/{private,public}
ls -d /var/www/kudosly/bootstrap/cache

# Create any that are missing.
sudo mkdir -p /var/www/kudosly/storage/framework/{cache/data,sessions,views,testing}
sudo mkdir -p /var/www/kudosly/storage/logs /var/www/kudosly/storage/app/{private,public}
```

| Path | Purpose | Write access needed by |
|---|---|---|
| `storage/app/private/` | Default filesystem disk (`local`) | PHP runtime |
| `storage/app/public/profile-photos/` | Visitor-uploaded customer photos (served via `public/storage`) | PHP runtime |
| `storage/app/public/user-profile-photos/` | Owner-uploaded account profile photos (served via `public/storage`) | PHP runtime |
| `storage/framework/{cache,sessions,views}/` | Compiled views, file cache, file sessions | PHP runtime |
| `storage/logs/` | Application logs | PHP runtime |
| `bootstrap/cache/` | Compiled config, routes, views, service manifest | PHP runtime |

**Do not grant write access to the application root, `config/`, `app/`, `resources/`,
`routes/`, `database/`, `vendor/`, or `.git/`.** The PHP runtime must not be able to modify
application code or the dependency tree.

**Verify the permissions actually work.** These checks read the current state and test real
access as the real identities — they do not change anything. Run them after the permissions
above are applied, and again after the first deployment.

```bash
APP_DIR=/var/www/kudosly
PHP_USER=$(ps -eo user,comm | awk '$2 ~ /^php/ {print $1; exit}')

# 1. The runtime trees must be group-writable (u=rw, g=rw), setgid on
#    directories, and owned by the shared group.
echo "--- runtime tree modes (expect drwxrwsr-x / -rw-rw-r--) ---"
ls -ld "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ls -l  "$APP_DIR/storage/logs" 2>/dev/null | head -3

# 2. The PHP runtime must be able to WRITE to the runtime trees.
#    This is the check that catches a 0644-on-runtime-files mistake.
sudo -u "$PHP_USER" test -w "$APP_DIR/storage" \
  && echo "OK: runtime can write storage/" \
  || { echo "ABORT: runtime cannot write storage/"; exit 1; }
sudo -u "$PHP_USER" test -w "$APP_DIR/bootstrap/cache" \
  && echo "OK: runtime can write bootstrap/cache/" \
  || { echo "ABORT: runtime cannot write bootstrap/cache/"; exit 1; }

# 3. The PHP runtime must NOT be able to write application source.
sudo -u "$PHP_USER" test -w "$APP_DIR/config" \
  && { echo "ABORT: runtime can write config/"; exit 1; } \
  || echo "OK: runtime cannot write config/"
sudo -u "$PHP_USER" test -w "$APP_DIR/vendor" \
  && { echo "ABORT: runtime can write vendor/"; exit 1; } \
  || echo "OK: runtime cannot write vendor/"
sudo -u "$PHP_USER" test -w "$APP_DIR/app" \
  && { echo "ABORT: runtime can write app/"; exit 1; } \
  || echo "OK: runtime cannot write app/"

# 4. The deploy user must be able to write the application tree.
sudo -u "$DEPLOY_USER" test -w "$APP_DIR/app" \
  && echo "OK: deploy user can update app/" \
  || { echo "ABORT: deploy user cannot update app/"; exit 1; }

# 5. Executable scripts must still be executable.
ls -l "$APP_DIR/vendor/bin" | head -5     # expect -rwxr-xr-x on the proxies
test -x "$APP_DIR/vendor/bin/pest" \
  && echo "OK: vendor/bin scripts are executable" \
  || echo "ABORT: vendor/bin scripts lost their executable bit"

# 6. The .env must be readable by the runtime, writable by the deploy user,
#    and unreadable by anyone else.
sudo -u "$PHP_USER"    test -r "$APP_DIR/.env" && echo "OK: runtime can read .env"   || echo "ABORT: runtime cannot read .env"
sudo -u "$DEPLOY_USER" test -w "$APP_DIR/.env" && echo "OK: deploy user can write .env" || echo "ABORT: deploy user cannot write .env"
sudo -u nobody         test -r "$APP_DIR/.env" && echo "ABORT: .env is world-readable" || echo "OK: .env is not world-readable"
ls -l "$APP_DIR/.env"    # expect -rw-r----- (0640)
```

**Never change the ownership or permissions of `/var/www` itself.** `/var/www` is a shared
parent directory that may hold other applications. Give Kudosly its own dedicated parent and
leave the shared one alone:

```bash
# ✗ Do NOT do this — it takes ownership of a shared parent directory.
sudo chown "$DEPLOY_USER" /var/www

# ✓ Do this instead — a dedicated directory owned by the deploy user, so
#   the deploy user can clone into it. /var/www itself is left alone.
sudo mkdir -p /var/www/kudosly
sudo chown "$DEPLOY_USER":"$DEPLOY_USER" /var/www/kudosly
sudo chmod 0755 /var/www/kudosly
```

> **The dedicated directory must be owned by the deploy user *before* the clone.**
> `sudo mkdir` creates a **root-owned** directory, and `git clone` cannot write into a
> directory the deploy user does not own — it fails with
> `fatal: could not create work tree dir '/var/www/kudosly': Permission denied`.
> The `chown` above must happen *before* `git clone`, not after.
>
> If the directory already exists and is root-owned, `sudo chown` it to the deploy user:
>
> ```bash
> sudo chown "$DEPLOY_USER":"$DEPLOY_USER" /var/www/kudosly
> ```
>
> Verify the deploy user can write there **before** cloning:
>
> ```bash
> sudo -u "$DEPLOY_USER" test -w /var/www/kudosly \
>   && echo "OK: the deploy user can clone into /var/www/kudosly" \
>   || { echo "ABORT: the deploy user cannot write /var/www/kudosly"; exit 1; }
> ```

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

Kudosly lives in its own dedicated directory. `/var/www` is a **shared** parent — do not change
its ownership or permissions.

```
/var/www/                  <-- shared parent; owned by root, not modified by this runbook
└── kudosly/               <-- the Kudosly application root; owned by the deploy user
    ├── app/
    ├── bootstrap/
    │   └── cache/         <-- WRITABLE by the PHP runtime (compiled config/routes/views)
    ├── config/
    ├── database/
    ├── public/            <-- web server document root
    ├── resources/
    ├── routes/
    ├── storage/           <-- WRITABLE by the web server (sessions, cache, logs, uploads)
    │   ├── app/
    │   │   ├── private/       <-- default filesystem disk
    │   │   └── public/        <-- served via public/storage
    │   │       ├── profile-photos/
    │   │       └── user-profile-photos/
    │   ├── framework/
    │   │   ├── cache/
    │   │   ├── sessions/
    │   │   ├── testing/
    │   │   └── views/
    │   └── logs/
    ├── vendor/            <-- created by composer install; READ-ONLY to the PHP runtime
    ├── .env               <-- created by you; never committed; mode 0640
    └── node_modules/      <-- temporary; may be deleted after the build
```

Only `storage/` and `bootstrap/cache/` are writable by the PHP runtime. Everything else —
including `vendor/`, `config/`, and `.git/` — is read-only to it.

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
| `PlanSeeder` | **Required** — the `plans` table starts empty | **Required** — idempotent; safe to re-run ([2.5](#25-planseeder--required-on-both-new-and-existing-installations)) |
| Migrations | All 19 run fresh | Only pending ones run ([3.4](#34-database-migrations)) |
| Backups | Not applicable before first launch | **Mandatory** before deploying ([5.1](#51-backup-procedure)) |
| Smoke tests | Full checklist ([Section 6](#6-post-deployment-smoke-tests)) | Full checklist, **plus** a data-integrity check ([6.6](#66-data-integrity-check)) |
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
# Create the dedicated directory and give it to the deploy user.
# `sudo mkdir` creates a ROOT-OWNED directory; git clone cannot write into a
# directory the deploy user does not own. The chown MUST come before the clone.
# Do NOT chown the shared /var/www parent — it may hold other applications.
sudo mkdir -p /var/www/kudosly
sudo chown "$DEPLOY_USER":"$DEPLOY_USER" /var/www/kudosly
sudo chmod 0755 /var/www/kudosly

# Verify the deploy user can write there BEFORE cloning.
sudo -u "$DEPLOY_USER" test -w /var/www/kudosly \
  && echo "OK: the deploy user can clone into /var/www/kudosly" \
  || { echo "ABORT: the deploy user cannot write /var/www/kudosly"; exit 1; }

cd /var/www/kudosly
git clone <your-repository-url> .
```

The repository does **not** contain the built assets (`public/build` is gitignored) or the
dependencies (`vendor/` and `node_modules/` are gitignored). Both are produced by the
deployment steps in Section 3.

> `git clone` requires the target directory to be empty or non-existent. If `/var/www/kudosly`
> already contains a checkout — for example on a re-deploy — do not clone into it; use the
> **EXISTING installation** sequence in [Section 3.9](#39-complete-deployment-sequence-copypaste)
> instead, which uses `git fetch` and `git checkout`.
>
> This section shows the clone on its own. [Section 3.9](#39-complete-deployment-sequence-copypaste)
> contains the **complete** new-installation sequence, which performs the same clone and then
> continues with directories, permissions, dependencies, and the environment. Use one or the
> other — they are the same steps, not two alternative procedures to combine.

### 2.4 Create the Production `.env`

```bash
cp .env.example .env
php artisan key:generate     # NEW environment only — see Section 2.2
```

Then edit `.env` and work through the checklist in
[Section 4](#4-production-environment-variable-checklist). **The application will not function
correctly with the default `.env.example` values** — `APP_ENV=local`, `APP_DEBUG=true`,
`MAIL_MAILER=log`, and no Stripe credentials.

> **Set `APP_NAME=Kudosly` explicitly.** After copying `.env.example`, open `.env` and make
> sure the first line reads:
>
> ```dotenv
> APP_NAME=Kudosly
> ```
>
> `APP_NAME` is what the application calls itself — it appears in the browser tab title, in
> password-reset email subjects and salutations, and in the notification text. Leaving it at
> the `.env.example` default (`Laravel`) would ship the product under the wrong name.
>
> **Note:** `MAIL_FROM_NAME` is commonly written as `MAIL_FROM_NAME="${APP_NAME}"`, which means
> the *From* display name in outgoing email **inherits** `APP_NAME`. So setting `APP_NAME`
> correctly also fixes the sender name — but only if `MAIL_FROM_NAME` is left referencing it.
> If you hard-code a different `MAIL_FROM_NAME`, that value wins and `APP_NAME` no longer
> affects it.

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

### 2.6 Rehearsing the Deployment in an Isolated Sandbox

Before deploying to production, you can rehearse the whole sequence — migrations, seeding,
caching, and a production-mode boot — on a **disposable copy** of the application that is
completely isolated from the real project and the real database.

> #### 🔴 SECURITY WARNING — Read Before Running Any Rehearsal
>
> A rehearsal copies the application's source tree. **The source tree contains the real
> `.env`**, which holds the real database credentials, the mail password, and the application
> key. Copying it creates a second copy of your production secrets on disk.
>
> **The rules that keep a rehearsal safe:**
>
> 1. **Exclude secrets BEFORE copying, not after.** Do not copy first and delete afterwards.
>    A file that exists on disk for even a moment is a file that can be read, backed up,
>    synced, or captured by a shell history or an editor's recovery file. Exclude at the
>    source. If you already copied a `.env`, delete it immediately **and** sweep the
>    sandbox for other secret-bearing files before running anything.
> 2. **Never copy the real `.env`, `.env.backup`, `.env.production`, or any `.env.*.local`**
>    into the sandbox. Write a **synthetic** environment file instead.
> 3. **Use a disposable `APP_KEY`.** Generate one inside the sandbox with
>    `php artisan key:generate --force`. Never copy the production key — a key that exists in
>    two places is a key that can leak from either.
> 4. **Use SQLite, and never reuse the real database credentials.** Point `DB_CONNECTION` at
>    `sqlite` and a throwaway file. Omit `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and
>    `DB_PASSWORD` from the sandbox environment entirely, so there is no reachable database
>    configuration in it at all.
> 5. **Never run migrations, seeders, or any Artisan command against the real database**
>    during a rehearsal. If the sandbox environment resolves to the production database for
>    any reason, `php artisan migrate --force` will write to it. Verify the resolved
>    connection **before** running anything that writes.
> 6. **Keep the sandbox outside the project directory** so it cannot be committed, and delete
>    it — along with its database and its generated key — when the rehearsal finishes.
> 7. **The sandbox needs its own `vendor/`.** `vendor/` is excluded from the source copy
>    because it is large and machine-specific, but **every Artisan command needs
>    `vendor/autoload.php`**. Without it, `php artisan` aborts immediately with
>    `Could not open input file: vendor/autoload.php`. Copy the **already installed** `vendor/`
>    directory from the source tree into the sandbox — do **not** run `composer install` inside
>    the sandbox, and do **not** let it reach the network. This is a plain file copy of
>    dependencies that are already on disk; it copies no secrets, because `vendor/` contains
>    no `.env`, no credentials, and no application configuration.
>
> ```bash
> # Exclude secrets BEFORE copying. Note the .env excludes come first.
> tar --exclude='./.env' --exclude='./.env.backup' --exclude='./.env.production' \
>     --exclude='./.env.*.local' --exclude='./.git' --exclude='./vendor' \
>     --exclude='./node_modules' --exclude='./storage/framework/*' \
>     --exclude='./storage/logs' --exclude='./public/build' --exclude='./public/storage' \
>     -cf - . | (cd "$REH" && tar -xf -)
>
> # Then verify nothing secret made it across, before running anything.
> grep -rl "DB_PASSWORD\|MAIL_PASSWORD\|STRIPE_SECRET\|APP_KEY=" "$REH" --include=".env*" \
>   | grep -v ".env.example" && echo "ABORT: a secret file is present" \
>   || echo "CLEAN: no secret files in the sandbox"
> ```

**The rehearsal sequence:**

```bash
SRC="/var/www/kudosly"                       # the real project
REH="/var/tmp/kudosly-rehearsal"             # the disposable sandbox
PHP="php"

# 1. Copy the source WITHOUT secrets, vendor, or runtime state.
mkdir -p "$REH"
cd "$SRC"
tar --exclude='./.env' --exclude='./.env.backup' --exclude='./.env.production' \
    --exclude='./.env.*.local' --exclude='./.git' --exclude='./vendor' \
    --exclude='./node_modules' --exclude='./storage/framework/*' \
    --exclude='./storage/logs' --exclude='./public/build' --exclude='./public/storage' \
    -cf - . | (cd "$REH" && tar -xf -)

# 2. SAFETY CHECK — no secret file may be present.
grep -rl "DB_PASSWORD\|MAIL_PASSWORD\|STRIPE_SECRET" "$REH" --include=".env*" \
  | grep -v ".env.example" && { echo "ABORT"; exit 1; }

# 2b. Provide the dependencies the sandbox needs. `vendor/` was excluded from the
#     copy above, but every artisan command requires vendor/autoload.php.
#     Copy the already-installed directory; do NOT run composer install here.
cp -a "$SRC/vendor" "$REH/vendor"
test -f "$REH/vendor/autoload.php" || { echo "ABORT: vendor/autoload.php missing"; exit 1; }

# 3. Recreate the runtime directories. Laravel will not create them itself,
#    and a missing storage/framework/views makes every artisan command abort
#    with "Please provide a valid cache path".
mkdir -p "$REH"/storage/framework/{cache/data,sessions,views,testing}
mkdir -p "$REH"/storage/{logs,app/private,app/public} "$REH"/bootstrap/cache

# 4. Write a SYNTHETIC production environment. No real credentials.
cat > "$REH/.env" <<'EOF'
APP_NAME=Kudosly
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://127.0.0.1:8199
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
DB_CONNECTION=sqlite
DB_DATABASE=/var/tmp/kudosly-rehearsal/rehearsal.sqlite
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@rehearsal.invalid"
MAIL_FROM_NAME="${APP_NAME}"
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
EOF

# 5. Disposable key, generated inside the sandbox only.
cd "$REH" && $PHP artisan key:generate --force

# 6. Disposable database, and the schema on it.
: > "$REH/rehearsal.sqlite"
cd "$REH" && $PHP artisan migrate --force
cd "$REH" && $PHP artisan db:seed --class=PlanSeeder --force

# 7. The caches, exactly as in production.
cd "$REH"
$PHP artisan config:cache
$PHP artisan route:cache      # optional — see Section 3.8 for the verification steps
$PHP artisan view:cache
ls bootstrap/cache/

# 8. Production-mode boot and a public-route smoke test.
cd "$REH" && $PHP -S 127.0.0.1:8199 -t public public/index.php > "$REH/server.log" 2>&1 &
for p in /up / /login /register /forgot-password; do
  printf "  GET %s -> %s\n" "$p" \
    "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8199$p" --max-time 20)"
done
# The webhook must refuse an unverifiable payload with 503, never 500.
curl -s -o /dev/null -w "  POST /stripe/webhook -> %{http_code}\n" \
  -X POST http://127.0.0.1:8199/stripe/webhook \
  -H "Content-Type: application/json" -d '{"type":"ping"}'

# 9. CLEANUP — remove the key, the database, and the whole sandbox.
PID=$(ss -ltnp 2>/dev/null | grep ':8199' | grep -oP 'pid=\K[0-9]+' | head -1)
[ -n "$PID" ] && kill "$PID"
rm -rf "$REH"
```

**What a rehearsal of this kind does and does not establish.**

| Established | **Not** established |
|---|---|
| The production-mode boot works with `APP_DEBUG=false` | That the **production** `.env` is correct — the rehearsal uses a synthetic one |
| `config:cache`, `route:cache`, and `view:cache` all succeed and coexist | That migrations behave identically on the **production** database and server — the rehearsal runs against SQLite, while the real target is MySQL/MariaDB |
| The schema applies cleanly on SQLite and `PlanSeeder` produces the right plan rows | That the **asset build** works — the rehearsal does not build Vite assets |
| The public routes return the expected status codes | That the **authenticated** dashboard pages work end to end — the rehearsal exercises public routes only |
| The webhook refuses an unverifiable payload with 503 rather than 500 | That **Stripe** is configured, or that a real checkout or webhook delivery succeeds |
| The main project is left byte-for-byte unchanged | Anything about **DNS, TLS, mail delivery, backups, or the live web server** |

> **Note on database coverage.** The migrations have already been applied on the **local
> MariaDB** database (19/19), and this rehearsal additionally applied 19/19 to a disposable
> SQLite database. Neither result substitutes for verification against the **actual production
> database and server**, where the schema, privileges, character set, and collation may all
> differ. Run `php artisan migrate --force` there only after the backup in
> [Section 5.1](#51-backup-procedure) has been taken and confirmed.

> **A rehearsal is not production deployment verification.** It is a cheap, repeatable check of
> the parts of the deployment that fail *silently* and *expensively*: a missing runtime
> directory, a cache command that cannot write, a route table that cannot be serialised, a
> `debug` flag that stayed on. It does not replace the smoke tests in
> [Section 6](#6-post-deployment-smoke-tests), which must be run against the real deployment.


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

All 19 migrations in this application should show **Ran**. The list is:

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
| `2026_11_22_120000_add_profile_photo_path_to_users_table` | profile photo path on users |

> **⚠️ Take a backup before running migrations on an existing installation.** Migrations are
> not automatically reversible in the general case. See [Section 5](#5-database-backup-restore-and-rollback).
>
> **Never run `migrate:fresh`, `migrate:reset`, or `migrate:refresh` on production.** Those
> drop tables and destroy all data.

### 3.5 Seed the Plans

```bash
php artisan db:seed --class=PlanSeeder
```

Required on **both** new and existing installations — see [Section 2.5](#25-planseeder--required-on-both-new-and-existing-installations) for why
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
> There are **two** upload directories on the `public` disk, and both must be reachable:
>
> | Directory | Written by |
> |---|---|
> | `profile-photos/` | Visitor photo on the public collection form (`SpaceCollectionController` → `->store('profile-photos', 'public')`) |
> | `user-profile-photos/` | The signed-in account's own profile picture (`ProfileController::updatePhoto` → `User::PROFILE_PHOTO_DIRECTORY`) |
>
> A missing symlink breaks both: broken images in the inbox, on the wall of love, in embedded
> widgets, and on the owner's own profile page.

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

> **Both require write access to `bootstrap/cache/` and `storage/framework/views/`.** If the
> deployment user cannot write those directories, they fail with a permission error. See
> [Section 1.4](#14-filesystem-permissions) — the ownership must be set **before** these run.

| Command | What it does | Notes |
|---|---|---|
| `config:cache` | Compiles `config/*.php` into `bootstrap/cache/config.php` | Safe. No `env()` calls exist outside `config/` in this application — verified — so no environment value is baked in incorrectly. **The `.env` must be final before this runs**, because the environment is baked in at this point |
| `view:cache` | Compiles every Blade template to `storage/framework/views` | Safe. Also fails loudly if a Blade template has a syntax error, which is useful |
| `route:cache` | Compiles routes into `bootstrap/cache/routes-v7.php` | **Optional** — see [Section 3.8](#38-route-caching--verify-before-enabling). Clears only its own artefact, so it does not disturb `config:cache` or `view:cache` |

### 3.8 Route Caching — Verify Before Enabling

`php artisan optimize` runs `config:cache`, `event:cache`, `route:cache`, and `view:cache` in
one go. Because `route:cache` is the only one of these that can fail, and because a failure
inside `optimize` aborts the remaining steps, the runbook deliberately **does not** use
`optimize`.

**Current status of route caching in this application: it has been tested and it works.**

A `route:cache` run completed successfully and produced a working `routes-v7.php`; the cached
route table resolved correctly, including `GET /up` and `POST stripe/webhook`. The welcome-page
closure in `routes/web.php:22` (`Route::get('/', function () { return view('welcome'); })`)
does **not** prevent route caching — Laravel 13 serialises route closures with
**`laravel/serializable-closure`** (v2), so the closure is stored in the compiled route file
rather than being rejected.

> **How this works in Laravel 13.** The rule "a closure in a route breaks `route:cache`" is a
> **Laravel ≤ 7** behaviour, where `RouteCacheCommand` threw a `LogicException` for any route
> whose action was a `Closure`. That check **no longer exists**. In Laravel 13
> (`Illuminate\Foundation\Console\RouteCacheCommand`) there is no closure-rejection logic at
> all: it calls `Route::prepareForSerialization()` on every route and writes the compiled
> table. `Route::runCallable()` detects a serialised closure at dispatch time and unserialises
> it via `Laravel\SerializableClosure\SerializableClosure`.
>
> `opis/closure` is **not** installed in this project and is not used. The package that
> performs the serialisation is `laravel/serializable-closure`, present in `composer.lock`.
>
> **Verification performed.** In a disposable sandbox (production mode, SQLite database, no
> real credentials) the following was confirmed:
>
> - `route:cache` completed successfully and produced `bootstrap/cache/routes-v7.php`.
> - **67 routes** were listed from the cached table, and **67 routes** were listed from the
>   uncached table — the cached route table is faithful to the source.
> - The critical routes were all present in the cache: `POST stripe/webhook`, `GET /up`,
>   `spaces.public.show`, `spaces.public.store`, `dashboard`, `billing.checkout`,
>   `wall.public.show`, and `widgets.embed.show`.
> - The `'/'` closure and the generated `/up` health route were both serialised into the
>   compiled file and resolved correctly at runtime.
>
> **However — this was verified against the route table as it exists today.** Route caching
> depends on the *shape* of the route table, and it can break later if a future change
> introduces a route that cannot be serialised (a closure that captures an unserialisable
> object, or a route parameter with a runtime-only constraint).
>
> **Therefore, treat `route:cache` as optional and verify it every time, on every deploy.**
> Route caching remains **optional**, not required: the application is fully functional
> without it.
>
> **Decision for you:** whether to include `route:cache` in the deploy script, or to leave it
> out and accept the small per-request overhead. Leaving it out is the lower-risk option for an
> MVP and costs very little.

**Optional — if you have decided to enable route caching on the target server:**

Run the cache, then verify it **three** ways. Note that `route:list` on its own does **not**
prove a valid cache: it succeeds by falling back to `routes/web.php` when the cached file is
missing or unreadable, so a bare `route:list` check reports success even when no cache exists.

```bash
# 1. Generate the cache.
php artisan route:cache

# 2. Verify the cache FILE was actually written.
test -f bootstrap/cache/routes-v7.php && echo "route cache file OK" || echo "ROUTE CACHE FILE MISSING"

# 3. Verify the critical routes are registered, by name.
php artisan route:list | grep -E "stripe/webhook|dashboard|billing/checkout|spaces.public.store"

# 4. Verify the cached table matches the uncached table (count comparison).
CACHED=$(php artisan route:list --json | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j);')
php artisan route:clear
UNCACHED=$(php artisan route:list --json | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j);')
echo "cached=$CACHED uncached=$UNCACHED"
php artisan route:cache    # re-apply, since step 4 cleared it

# If the counts differ, or step 3 finds a missing route:
php artisan route:clear
```

> **Why step 4 matters.** `route:clear` removes the cached file, so the application falls back
> to loading routes from `routes/web.php` — slower, but fully functional, provided the
> application can still boot and route requests. Route caching is therefore **optional**: the
> correct response to a failed or mismatched route cache is to **clear it** and then **confirm
> the application still works** — not to ignore the failure and move on blindly.
>
> **After clearing a failed route cache, verify the fallback explicitly** before continuing the
> deployment:
>
> ```bash
> php artisan route:clear
> php artisan route:list | wc -l          # routes are still registered from routes/web.php
> curl -s -o /dev/null -w "  GET /up -> %{http_code}\n" https://<your-domain>/up
> ```
>
> If the route list is empty, the application cannot route requests, and the deployment must
> stop and be fixed — that is a real outage, not a caching concern. Only once the fallback is
> confirmed working should you continue.
>
> **Note on ordering.** `config:cache`, `route:cache`, and `view:cache` each clear only their
> own artefact (`config:clear`, `route:clear`, `view:clear` respectively) — they do not
> invalidate one another, so all three can coexist in `bootstrap/cache/` at the same time.
> `route:cache` boots a *fresh* application instance to snapshot the route table, so it must
> be run **after** the production `.env` is final.

### 3.9 Complete Deployment Sequence (Copy/Paste)

> **Ordering note — read before running these.** The writable directories (`storage/`,
> `bootstrap/cache/`) must **exist and be writable by the deployment user before any Artisan
> command runs**. Every step below that calls `php artisan` writes to one of them. Applying
> the ownership change at the *end* — as an earlier revision of this runbook did — leaves the
> deployment user unable to write `storage/` while `migrate`, `storage:link`, `config:cache`,
> and `view:cache` are still running.
>
> The order in both sequences below is therefore: **empty destination → clone → runtime
> directories → ownership and permissions → Artisan commands.** The runtime directories and the
> ownership change both happen *after* the clone and *before* the first `php artisan` call.
>
> If the deployment user and the web server user are different accounts, use the shared-group
> or ACL strategy from [Section 1.4](#14-filesystem-permissions) rather than a blanket
> `chown -R www-data`.
>
> **Scope reminder.** Only `storage/` and `bootstrap/cache/` become group-writable. The PHP
> runtime keeps **read-only** access to `app/`, `config/`, `resources/`, `routes/`,
> `database/`, `vendor/`, and `.git/`. The `.env` is set to mode `0640`, owned by the deploy
> user, with the **PHP runtime group** as its group — the runtime can *read* it (PHP must),
> but neither it nor anyone else can write it. Never `chown` or `chmod` the shared `/var/www`
> parent directory.
>
> **Identify the PHP runtime user before step 4.** The commands below use `$PHP_USER` and
> `$PHP_GROUP`, which are resolved from the running PHP-FPM process — do not assume
> `www-data`. See the identification commands in [Section 1.4](#14-filesystem-permissions).

**NEW production environment:**

```bash
APP_DIR=/var/www/kudosly

# Identify the actual PHP runtime identity (do NOT assume www-data).
PHP_USER=$(ps -eo user,comm | awk '$2 ~ /^php/ {print $1; exit}')
PHP_GROUP=$(ps -eo user,group,comm | awk '$3 ~ /^php/ {print $2; exit}')
echo "PHP runtime identity: $PHP_USER:$PHP_GROUP"

# --- 1. Prepare a DEDICATED, EMPTY destination directory ---
#      `sudo mkdir` creates a ROOT-OWNED directory, and git clone cannot write
#      into a directory the deploy user does not own. The chown must come
#      BEFORE the clone. Do NOT chown the shared /var/www parent.
sudo mkdir -p "$APP_DIR"
sudo chown "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR"
sudo chmod 0755 "$APP_DIR"

# Verify the deploy user can actually write there BEFORE cloning.
sudo -u "$DEPLOY_USER" test -w "$APP_DIR" \
  && echo "OK: the deploy user can clone into $APP_DIR" \
  || { echo "ABORT: the deploy user cannot write $APP_DIR"; exit 1; }

# --- 2. Clone the repository into that empty directory ---
cd "$APP_DIR"
git clone <your-repository-url> .
git checkout <branch-or-tag>

# --- 3. Create the Laravel runtime directories that the repository does not carry ---
#      Laravel will NOT create these itself; a missing storage/framework/views makes
#      every artisan command abort with "Please provide a valid cache path".
mkdir -p storage/framework/{cache/data,sessions,views,testing}
mkdir -p storage/logs storage/app/{private,public}
mkdir -p bootstrap/cache

# --- 4. Ownership and permissions, BEFORE any artisan command ---
#      Group strategy from Section 1.4; substitute the ACL variant if preferred.
#      Only storage/ and bootstrap/cache/ become group-writable.
sudo groupadd -f kudosly
sudo usermod -aG kudosly "$PHP_USER"
sudo usermod -aG kudosly "$DEPLOY_USER"
# Everything is owned by the deploy user first, ...
sudo chown -R "$DEPLOY_USER":"$DEPLOY_USER" .
# ... then the two writable trees are given to the shared group. This order
# matters: a blanket chown afterwards would undo the group ownership.
sudo chown -R root:kudosly storage bootstrap/cache
# Runtime directories: group rwx + setgid, so new files inherit the group.
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} +
# Runtime files: GROUP-WRITABLE (0664). The PHP runtime is not the owner,
# so 0644 would leave it unable to update compiled views, sessions, or logs.
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} +
newgrp kudosly

# --- 5. Dependencies and assets ---
composer install --no-dev --optimize-autoloader
npm ci && npm run build
test -f public/build/manifest.json || { echo "BUILD FAILED"; exit 1; }

# --- 6. Environment ---
cp .env.example .env
php artisan key:generate                    # NEW environment only — Section 2.2
# >>> Edit .env per Section 4 <<<
# >>> Set APP_NAME=Kudosly — Section 2.4 <<<
# Protect the secrets file (Section 1.4):
sudo chown "$DEPLOY_USER":"$PHP_GROUP" .env
sudo chmod 0640 .env
sudo -u "$PHP_USER" test -r .env && echo "OK: PHP runtime can read .env" \
  || { echo "ABORT: PHP runtime cannot read .env"; exit 1; }

# --- 7. Schema and plans ---
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=PlanSeeder

# --- 8. Caches ---
php artisan config:cache
php artisan view:cache
# php artisan route:cache                   # optional — see Section 3.8
```

> **Why the clone comes before the ownership change.** `git clone` refuses a non-empty target
> directory, so the destination must be prepared *before* the clone — but the directories that
> need to exist *inside* the checkout (`storage/`, `bootstrap/cache/`) only exist after it.
> That is why the `mkdir -p` calls in step 3 come after step 2.
>
> **Why the application tree is not swept with `chmod 0644`.** The checkout keeps the modes
> that came from git, so `vendor/bin/*` and any `bin/` console script stay executable. Only
> the two runtime trees are normalised. If you do need to normalise the application tree,
> use the mode-preserving form in
> [Section 1.4](#14-filesystem-permissions).

**EXISTING installation:**

```bash
APP_DIR=/var/www/kudosly

# Identify the actual PHP runtime identity (do NOT assume www-data).
PHP_USER=$(ps -eo user,comm | awk '$2 ~ /^php/ {print $1; exit}')
PHP_GROUP=$(ps -eo user,group,comm | awk '$3 ~ /^php/ {print $2; exit}')
echo "PHP runtime identity: $PHP_USER:$PHP_GROUP"

# --- 1. BACK UP FIRST — Section 5.1 ---
#      The working tree already exists here; there is no clone into an empty
#      directory, and the existing .env is preserved untouched.

cd "$APP_DIR"

# --- 2. Update the code ---
git fetch --all && git checkout <branch-or-tag>

# --- 3. Ensure the runtime directories still exist ---
mkdir -p storage/framework/{cache/data,sessions,views,testing}
mkdir -p storage/logs storage/app/{private,public}
mkdir -p bootstrap/cache

# --- 4. Ownership and permissions, BEFORE any artisan command ---
#      Same scope as the NEW sequence: only storage/ and bootstrap/cache/
#      become group-writable; everything else is read-only to the PHP runtime.
sudo groupadd -f kudosly
sudo usermod -aG kudosly "$PHP_USER"
sudo usermod -aG kudosly "$DEPLOY_USER"
# Everything is owned by the deploy user first, ...
sudo chown -R "$DEPLOY_USER":"$DEPLOY_USER" .
# ... then the two writable trees are given to the shared group. This order
# matters: a blanket chown afterwards would undo the group ownership.
sudo chown -R root:kudosly storage bootstrap/cache
# Runtime directories: group rwx + setgid, so new files inherit the group.
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} +
# Runtime files: GROUP-WRITABLE (0664). The PHP runtime is not the owner,
# so 0644 would leave it unable to update compiled views, sessions, or logs.
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} +
# The .env keeps the deploy user as owner and the PHP runtime group, mode 0640.
sudo chown "$DEPLOY_USER":"$PHP_GROUP" .env
sudo chmod 0640 .env
sudo -u "$PHP_USER" test -r .env && echo "OK: PHP runtime can read .env" \
  || { echo "ABORT: PHP runtime cannot read .env"; exit 1; }
newgrp kudosly

# --- 5. Dependencies and assets ---
# .env is preserved. Do NOT regenerate APP_KEY. Section 2.2.
composer install --no-dev --optimize-autoloader
npm ci && npm run build
test -f public/build/manifest.json || { echo "BUILD FAILED"; exit 1; }

# --- 6. Schema and plans ---
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=PlanSeeder

# --- 7. Caches ---
php artisan config:cache
php artisan view:cache
# php artisan route:cache                   # optional — see Section 3.8
```

> On an existing installation the ownership re-assertion in step 4 is safe: it changes
> ownership and group, never contents. It also **preserves the deployment user's own write
> access**, because the deployment user is a member of the `kudosly` group and the group has
> write permission on the two runtime trees — so the PHP runtime can write uploads and logs
> while the deployment user can still run Artisan commands.
>
> **Note on scope.** Step 4 re-asserts ownership of the whole tree back to the deployment
> user, then re-grants group-write on `storage/` and `bootstrap/cache/` only. This is
> intentional: after `git checkout`, files the deploy user pulled may have new permissions,
> and the deploy user must own them to be able to pull again. The PHP runtime never receives
> write access to `app/`, `config/`, `vendor/`, or `.git/`.
>
> **Note on the missing `chmod` sweep.** Unlike a fresh checkout, an existing installation
> may contain files with unusual modes. Step 4 normalises the two runtime trees deliberately
> and **leaves the application tree's modes alone**, so `vendor/bin/*` and any `bin/` console
> script keep their executable bit. If you need to normalise the application tree on an
> existing installation, use the mode-preserving form in
> [Section 1.4](#14-filesystem-permissions).
>
> **⚠️ `git checkout` on an existing installation can replace the working tree.** If the
> server has local edits (a hand-edited `.env`, a modified config file), commit or stash them
> before running step 2, or `git checkout` will refuse to proceed. The `.env` file is gitignored,
> so it is not affected by `git checkout` — but any *tracked* file that was edited locally is.

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
> backup is not a backup — verify a restore into a **scratch database**
> ([5.2.1](#521-scratch-database-restore-default--safe-non-destructive)) at least once before
> you trust it. Verifying a backup never requires touching the production database.

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
>
> **The scratch-database restore below is the default and the recommended procedure.** It is
> also what you use to *verify* a backup. The destructive production restore is a separate,
> exceptional disaster-recovery procedure — see
> [5.2.2](#522-production-restore-disaster-recovery-only). Do not run it as part of a routine
> deployment, a backup-verification routine, or a smoke test.

#### 5.2.1 Scratch-database restore (default — safe, non-destructive)

Use this to **verify a backup** and to **practise** the restore. It never touches the live
database.

```bash
# A scratch database with a name that cannot be confused with production.
mysql -u root -p -e "CREATE DATABASE kudosly_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c /var/backups/kudosly/daily-2026-01-01.sql.gz \
    | mysql -u root -p kudosly_restore_test

mysql -u root -p -e "USE kudosly_restore_test; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM testimonials; SELECT slug, max_spaces FROM plans;"
```

Then clean up the scratch database when you are done:

```bash
mysql -u root -p -e "DROP DATABASE kudosly_restore_test;"
```

#### 5.2.2 Production restore (disaster recovery only)

> #### 🔴 DESTRUCTIVE — exceptional disaster recovery, NOT a routine step
>
> The procedure below **drops the production database** and replaces it with the contents of a
> backup file. Every row written since that backup is **permanently lost** — testimonials,
> spaces, widgets, users, subscription state, and Stripe entitlements.
>
> **This is not part of any routine deployment, backup-verification, or smoke-test sequence.**
> Do not paste it into a deploy script. Do not run it because a deploy "seemed to go wrong"
> without first doing the checks below.
>
> **Do not proceed unless every one of these is true:**
>
> - [ ] **Explicit operator approval.** A named person has confirmed, in writing, that the
>       production database is to be dropped and replaced. Not "it looked broken".
> - [ ] **A verified recent backup exists.** The archive has been checked with `gzip -t`, is
>       non-empty, its timestamp is known, and it has been restored into a scratch database
>       **successfully** (see
>       [5.2.1](#521-scratch-database-restore-default--safe-non-destructive)).
> - [ ] **The target identity is confirmed.** You are certain of the host, the port, and the
>       database name, and that the `mysql -u root -p` prompt belongs to the **production**
>       server — not a replica, not staging, not a local developer machine. Print the host and
>       confirm it before continuing.
> - [ ] **A maintenance window is in effect** and stakeholders have been told the site will be
>       unavailable.
> - [ ] **A separate recovery plan exists** — documented, and known to more than one person —
>       describing what to do if this restore itself fails or produces a broken database.
> - [ ] **A fresh backup of the current production data has been taken**, so the data being
>       discarded is at least preserved somewhere.
>
> **The `DROP DATABASE` command below is irreversible.** There is no undo. If you are unsure
> whether you should be running this section, you should not be.

**Only after every checkbox above is satisfied:**

```bash
# --- Confirm the target identity BEFORE touching anything ---
hostname
mysql -u root -p -e "SELECT @@hostname, @@port, DATABASE();"

# 1. Put the application into maintenance mode.
php artisan down --render="errors::503" --retry=60

# 2. Take a fresh backup of what is about to be discarded.
mysqldump --single-transaction --quick --routines --triggers \
    --default-character-set=utf8mb4 \
    -u kudosly -p'YOUR_DB_PASSWORD' \
    kudosly | gzip > /var/backups/kudosly/pre-restore-$(date +%Y%m%d-%H%M%S).sql.gz

# 3. Drop and recreate the schema.  ← IRREVERSIBLE.
mysql -u root -p -e "DROP DATABASE kudosly; CREATE DATABASE kudosly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Load the backup.
gunzip -c /var/backups/kudosly/daily-2026-01-01.sql.gz | mysql -u root -p kudosly

# 5. Re-apply anything that ran after the backup was taken.
php artisan migrate --force

# 6. Verify the data landed, BEFORE taking the site out of maintenance mode.
mysql -u root -p -e "USE kudosly; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM testimonials; SELECT slug, max_spaces FROM plans;"

# 7. Bring the site back.
php artisan up
```

> **⚠️ A restore replaces everything written since the backup.** Testimonials submitted by
> customers, spaces, widgets, and subscription state are all lost back to the backup point.
> Take a fresh backup immediately before restoring, so the lost window is as small as possible.
>
> **If the restore fails partway**, the database is in a partial state. Do not retry blindly.
> Put the site into maintenance mode, restore the pre-restore backup from step 2, and escalate
> per the recovery plan.

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
>
> **If a full production restore is genuinely required**, that is the separate, gated
> disaster-recovery procedure in
> [5.2.2](#522-production-restore-disaster-recovery-only) — not a rollback step. It requires
> its own approval, a verified backup, confirmed target identity, a maintenance window, and a
> tested scratch restore.

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
# Use the SAME identity as the PHP-FPM runtime — see Section 1.4 for how to
# identify it. On Debian/Ubuntu with the default pool that is "www-data";
# on RHEL/CentOS it is "apache"; on Alpine it is "_www"; managed hosts often
# use a custom pool user. Match it to your PHP-FPM pool, or the worker will
# not be able to write the runtime files it needs.
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/kudosly
# UMask=0002 so files the worker creates are group-writable, matching the
# `kudosly` group ownership of storage/ and bootstrap/cache/.
UMask=0002
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

> **Note on worker permissions.** The worker must run as the **same identity as the PHP-FPM
> runtime** — not an assumed `www-data`. Identify it with the procedure in
> [Section 1.4](#14-filesystem-permissions) and set `User=` / `Group=` in the unit file to that
> identity. It then inherits the same permission posture: it may write to `storage/` and
> `bootstrap/cache/` (as a member of the `kudosly` group, with runtime files at `0664` and
> directories at `2775`), and it may only **read** `app/`, `config/`, `vendor/`, and `.env`. It
> does not need write access to anything else. The unit's `UMask=0002` makes the files the worker
> creates group-writable, so they stay writable for the web server as well.

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
  `Route::get('/', function () { ... })` closure in `routes/web.php:22` does **not** prevent it.
  Laravel 13 serialises route closures with **`laravel/serializable-closure`**; the
  `Opis\Closure` package is **not** installed and is not used. The cache was cleared
  afterwards, leaving the repository unchanged.
- **`GET /up` is registered** (via `bootstrap/app.php` → `health: '/up'`) and is usable as an
  uptime probe.
- **All 19 migrations are present** and cover every table in PRD §13, plus Cashier's
  `subscriptions`/`subscription_items` and Laravel's `sessions`/`cache`/`jobs`/`failed_jobs`.
- **`public/build` is gitignored** and is not in the repository — hence
  [Section 3.3](#33-verify-the-asset-build--critical-step).
- **`public/storage` is gitignored** — hence [Section 3.6](#36-create-the-storage-symlink).
- **Uploaded photos live in two directories on the `public` disk**: `profile-photos/` for
  visitor uploads (`SpaceCollectionController`) and `user-profile-photos/` for the signed-in
  account's own picture (`ProfileController::updatePhoto` → `User::PROFILE_PHOTO_DIRECTORY`).
  Both are served through `public/storage`.
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

### 9.2 What the isolated sandbox rehearsal established — and what it did not

A rehearsal was run in a disposable sandbox outside the project: a copy of the source tree
with **no real secrets**, a **synthetic** production `.env` with a disposable `APP_KEY`, and a
**SQLite** database. The real database was never contacted.

**Verified in the rehearsal:**

- A **production-mode boot** succeeded (`APP_ENV=production`, `APP_DEBUG=false`, application
  name `Kudosly`).
- **`config:cache`** succeeded, and the cached configuration reported the expected values
  (`production` / debug off) — confirming that `config:cache` bakes the environment in
  correctly, and that the `.env` must be final *before* it is run.
- **`route:cache`** succeeded and produced `bootstrap/cache/routes-v7.php`.
- **`view:cache`** succeeded. All three caches coexist in `bootstrap/cache/` — each clears only
  its own artefact and none invalidates another.
- **The route table was faithful**: **67 routes** from the cached table, **67** from the
  uncached table, with `POST stripe/webhook`, `GET /up`, `spaces.public.show`,
  `spaces.public.store`, `dashboard`, `billing.checkout`, `wall.public.show`, and
  `widgets.embed.show` all present.
- **Five public-route checks** returned the expected status codes: `/up`, `/`, `/login`,
  `/register`, and `/forgot-password` all returned **200**.
- The **Stripe webhook refused an unverifiable payload with 503**, not 500 — it declines to
  act on a payload it cannot verify.
- A request to an unknown route returned a **clean 404 with no stack trace**, confirming
  debug output was genuinely off.
- The **main project was left unchanged**: `bootstrap/cache/` still contained only
  `packages.php` and `services.php`, `.env` was untouched, and no stray files remained. The
  sandbox and its generated key were deleted afterwards.

**Not covered by that rehearsal — do not read it as production verification:**

- **Vite assets were not freshly built** in the sandbox. The asset build was **not** exercised,
  so [Section 3.3](#33-verify-the-asset-build--critical-step) remains unverified by the
  rehearsal and must still be checked on the server.
- **Authenticated dashboard pages were not comprehensively smoke-tested.** The rehearsal
  covered public routes only; the signed-in flows in
  [Section 6.3](#63-authentication-and-core-flow) were not run.
- **The rehearsal used SQLite, not MySQL/MariaDB.** Schema behaviour can differ between the
  two, so a clean SQLite migration is not proof that `migrate --force` will succeed against
  the production database.
- **Neither database result replaces verification on the production server.** The migrations
  have already been applied on the **local MariaDB** database (19/19), and the rehearsal
  applied 19/19 to a disposable SQLite database. The **production** database and server are a
  third environment: schema, privileges, character set, and collation may all differ, and
  `migrate --force` there writes against real data. Run it only after the
  [Section 5.1](#51-backup-procedure) backup has been taken and confirmed.
- **Stripe was not configured and no payment flow was exercised.** No checkout, no webhook
  delivery, no entitlement change — see [Section 6.5](#65-billing-and-stripe).
- **DNS, TLS, mail delivery, backups, and the live web server were out of scope entirely.**

> **Summary.** The rehearsal confirmed that the deployment *commands* work in production mode
> and that the caches are safe to build. It did **not** verify the deployment itself. The
> smoke tests in [Section 6](#6-post-deployment-smoke-tests) are still required against the
> real server, and a rehearsal must never be treated as a substitute for them.

### 9.3 Things this document deliberately does not do

- It does not contain any real secret. Every credential is a placeholder.
- It does not create a Stripe account, a price, a webhook endpoint, or an SMTP mailbox.
- It does not execute any deployment step, migration, seeder, or database write.
- It does not modify `.env`, `phpunit.xml`, `PRD.md`, or any application code.
- It does not run the test suite. The last full run was **679 passed, 1891 assertions**.

### 9.4 Source of truth

When this document and the code disagree, the code wins. Re-verify the facts in
[Section 9.1](#91-facts-that-were-verified-by-inspection) after any change to the route table,
the seeders, or the Vite configuration.
