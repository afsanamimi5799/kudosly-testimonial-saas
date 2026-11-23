# Kudosly — development instructions

These instructions are for any AI coding agent working in this repository. They are
hand-written: Laravel Boost is installed as a development dependency, but
`php artisan boost:install` was not run, so nothing here is machine-generated.

Read this file completely before making a change.

---

## 1. What this project is

Kudosly is a testimonial collection SaaS. Owners create branded **Spaces**, share a
public collection link, collect written testimonials from visitors who do not need an
account, moderate them in a shared inbox, and publish the approved ones on a wall of
love or in embeddable widgets on their own website. A Free and a Pro plan are sold
through Stripe.

The authoritative specification is [`PRD.md`](PRD.md). When a request and the PRD
disagree, the PRD wins unless the user says otherwise.

---

## 2. Hard rules

These are not preferences. Breaking them damages the project or its data.

1. **Never run migrations or seeders against the real MariaDB database.** The
   development database is MySQL/MariaDB; the test database is SQLite. Work happens
   against SQLite (see §4) or against a local preview database, never against a
   shared or production one.
2. **Never modify `PRD.md` unless the user explicitly asks.** It is the specification,
   not a scratch file.
3. **Never modify `.env`.** Only `.env.example` may be edited, and only to add a
   variable with a placeholder value.
4. **Never commit secrets.** `.env`, `*.sqlite`, `*.sql`, `/storage/app/private` and
   `/storage/app/public` are gitignored for a reason. Check `git status` before staging
   anything.
5. **Never write to `storage/` runtime directories by hand.** They hold session files,
   caches, compiled views and uploaded profile pictures.
6. **Ask before destructive or shared actions** — dropping tables, resetting the
   database, rewriting history, pushing, deploying.
7. **Do not install dependencies** unless the user asks. Adding a package changes the
   lock file and the deployment surface.

---

## 3. Environment

| Tool | Path / command |
| --- | --- |
| PHP | `C:\Users\TIKTOK.DESKTOP-QVMRCAP\.config/herd/bin/php85/php.exe` on this machine, or `php` on PATH elsewhere |
| Composer | `composer` |
| Node | `node` (v26.x here) |
| Test suite | `vendor/bin/pest` |

On this Windows machine PHP is **not on `PATH`**. Invoke it by full path, or set a
shell variable first:

```bash
PHP="/c/Users/TIKTOK.DESKTOP-QVMRCAP/.config/herd/bin/php85/php.exe"
"$PHP" artisan migrate --status
```

---

## 4. Commands

### The test suite — always use SQLite in memory

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=":memory:" vendor/bin/pest --compact
```

The `:memory:` database is created and thrown away for each run, so the suite can
never touch the development data. Run a single file the same way:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=":memory:" \
  vendor/bin/pest tests/Feature/Billing/WebhookTest.php
```

The suite must stay green before any commit. The baseline is **690 tests**.

### Code style

```bash
vendor/bin/pint              # format
vendor/bin/pint --test       # check only
```

Run Pint on the files you touched. Note that a small number of style failures exist
in files nobody has touched yet — do not "fix" them as a side effect of an unrelated
change, and do not let them hide a real failure in your own files.

### Front end

```bash
npm run build    # production build into public/build
npm run dev      # dev server with hot reload
```

Tailwind CSS 4 is configured **CSS-first**, in `resources/css/app.css`. There is no
`tailwind.config.js` and there must not be one. Utility classes are discovered from
the Blade templates through the `@source` globs, so a class that is only used inside
a PHP string has to be written out in full.

### Migrations and seeders

```bash
php artisan migrate --status
php artisan db:seed --class=PlanSeeder    # the Free and Pro plan rows
php artisan db:seed --class=DemoSeeder    # demo account and content — local preview only
```

`DemoSeeder` creates a login of `demo@kudosly.test` / `password`. It is deliberately
separate from `DatabaseSeeder` so it cannot run by accident. It writes rows, so it
belongs on a preview database only.

---

## 5. Conventions

### Structure

- Controllers live in `app/Http/Controllers/` and are grouped by area: `Auth`,
  `BillingController`, `InboxController`, `PublicCollection`, `PublicWall`,
  `SpacesController`, `WidgetsController`, and so on.
- Validation belongs in **Form Requests** under `app/Http/Requests/`, not inline in a
  controller.
- Authorization belongs in **policies** under `app/Policies/`. Every controller that
  touches a space, testimonial or widget calls the policy.
- Billing logic belongs in `app/Billing/`. Stripe is reached through the
  `StripeGateway` **contract**, so a fake can be bound in tests. Do not call Stripe
  directly from a controller.
- Shared UI lives in `resources/views/components/app/`.

### Models

- Eloquent attributes use the **attribute syntax** already in use:
  `#[Fillable([...])]`, `#[Hidden([...])]`, and `protected function casts(): array`.
  Do not mix in the older `$fillable` array style.
- Define a relationship method for every foreign key. `Space` has `user()`,
  `fields()`, `testimonials()` and `widgets()`; `Testimonial` has `space()`, and so on.
- Soft-deleted models (`Space`, `Testimonial`, `Widget`) must never appear on a
  public page. The `publiclyVisible` scope on `Testimonial` is the single place that
  decision is made.

### Views

- Blade with Tailwind utility classes. No frontend framework, no Alpine.
- Interactive behaviour is **small inline vanilla-JS blocks** in a `<script>` tag at
  the bottom of the view, wrapped in an IIFE, following the pattern already used in
  `resources/views/components/app/layout/sidebar.blade.php` and
  `resources/views/dashboard/overview.blade.php`.
- A script must find its own elements and give up quietly when they are absent:

  ```js
  (function () {
      var root = document.getElementById('something');

      if (! root) {
          return;
      }

      // ...
  })();
  ```

- Server-rendered HTML is the default. JavaScript enhances it; it does not replace it.
- Dark mode is supported: every custom colour needs a `dark:` counterpart.

### Conventions in force

- **Comments explain why, not what.** The codebase uses full sentences with a capital
  letter and a closing full stop. Match that style — it is noticeably different from
  the terse style Laravel's own comments use.
- **PHP 8.3+ syntax is welcome**: constructor property promotion, `match`, enums,
  readonly properties, named arguments.
- **Never leak private data.** A testimonial's `customer_email`, and any testimonial
  without sharing consent, must never reach a public endpoint, a widget payload or a
  wall page.

---

## 6. Working method

1. **Read before writing.** Open the controller, the request class, the model and the
   view involved. Do not guess at the shape of the code.
2. **Write the test first** for behaviour that has a rule attached to it. The suite is
   the specification that does not change.
3. **Make the smallest change that solves the problem.** A bug fix does not need the
   surrounding code tidied.
4. **Measure before you guess.** For an interface problem, reproduce it in a browser
   and read the computed geometry. Several defects in this project were misdiagnosed
   on first guess and only resolved by measuring.
5. **Verify in layers**: the focused test file, then the full suite, then Pint, then
   the browser if the change touches the interface.
6. **Report honestly.** If a test fails, say so and show the output. If something was
   not verified, say that too.

### Testing in a browser

Playwright is available as a Node package. When a UI change needs to be checked:

- Serve the app with `php artisan serve`, or with a small router that serves
  `public/` statically — **a router that sends every request through
  `public/index.php` will return HTML for CSS and JavaScript requests**, which makes
  the page render unstyled and produces misleading measurements.
- Never point browser tests at the shared database. Seed a throwaway SQLite file.
- Remove the seed database, the router and the scripts when you are done.

---

## 7. Money, e-mail and the outside world

- The Pro plan is **$999 per year**, billed annually through Stripe. The figure lives
  in the `plans` table and is enforced from there.
- **Never activate the Pro plan from a Checkout success redirect.** Only a verified
  webhook may change an entitlement.
- Verify Stripe webhook signatures, and process each event id once.
- The mail layer works. Real delivery does not, because no SMTP provider is
  configured — the `log` mailer writes to `storage/logs/laravel.log`. Do not describe
  an e-mail as "sent" unless it reached a real inbox.
- The public submission route is rate limited by IP and guarded by a honeypot. Both
  behaviours are covered by tests in
  `tests/Feature/Spaces/PublicCollectionTest.php`; keep them working.

---

## 8. Before you commit

- [ ] The focused test file passes.
- [ ] The full suite passes — `690` tests, no failures.
- [ ] `vendor/bin/pint --test` is clean for the files you touched.
- [ ] `git diff --cached --name-only` lists only the intended files.
- [ ] No `.env`, `storage/`, `*.sqlite` or `PRD.md` in the staged set.
- [ ] `git diff --cached --check` reports no whitespace errors.
- [ ] The commit message states the **why**, in the imperative mood, under about 70
      characters.
