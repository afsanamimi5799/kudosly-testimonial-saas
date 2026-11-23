# Kudosly — reviewer guide

This document is written for the person marking this submission. It assumes you have
never seen the project before. It tells you how to get the application running on your
own machine, where to click to see each feature, what the tests cover, and — just as
importantly — what this submission does **not** do, so that nothing is overstated.

Everything stated below was verified against the code in this repository: the routes
come from `php artisan route:list`, the test numbers from an actual run of the suite,
the plan limits from the seeder and the plan catalogue, and the privacy rule from the
model itself.

---

## Table of contents

- [What this is](#what-this-is)
- [Local setup](#local-setup)
- [Running the test suite](#running-the-test-suite)
- [A route-by-route walkthrough](#a-route-by-route-walkthrough)
- [Where the tests live](#where-the-tests-live)
- [The data model in one screen](#the-data-model-in-one-screen)
- [The plan limits](#the-plan-limits)
- [The privacy model](#the-privacy-model)
- [Verifying the screenshots](#verifying-the-screenshots)
- [Known limitations and honest gaps](#known-limitations-and-honest-gaps)
- [Related documentation](#related-documentation)

---

## What this is

Kudosly is a testimonial-collection SaaS. An owner creates branded **Spaces**, shares a
public collection link, and visitors submit testimonials to that link without needing
an account of any kind. The owner moderates the submissions in a shared inbox and
publishes the approved ones on a wall of love or inside embeddable widgets that sit on
their own website. A Free plan and a Pro plan are sold through Stripe. The
application is a Laravel 13 monolith with server-rendered Blade views, a Tailwind CSS 4
front end, Laravel Cashier for billing and a Pest feature-test suite.

---

## Local setup

### Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer (developed on 8.5) | Installed by [Laravel Herd](https://herd.laravel.com) on Windows and macOS |
| Composer | 2.x | Ships with Herd |
| Node.js | 20 or newer (26.x here) | Only needed to build the front end |
| Database | MySQL 8 / MariaDB 10.6 or newer | The development database |

### A note on PHP and the `PATH`

On the machine this was built on, **PHP is not on the `PATH`**. Invoke it by its full
path, or set a shell variable first and use it for every `php` command:

```bash
PHP="/c/Users/TIKTOK.DESKTOP-QVMRCAP/.config/herd/bin/php85/php.exe"
```

That path is **specific to that machine**. On your own machine, `php` on the `PATH` is
fine and the variable is unnecessary. The commands below use `"$PHP"` so they read
correctly either way.

### Steps

Run these in order from the repository root.

```bash
# 1. PHP dependencies, front-end dependencies, .env, app key, migrations and a
#    production asset build — all in one step.
composer setup
```

`composer setup` runs, in order:

1. `composer install`
2. copies `.env.example` to `.env` if `.env` does not exist yet
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `npm install --ignore-scripts`
6. `npm run build`

If you prefer to run the steps by hand:

```bash
composer install
cp .env.example .env
"$PHP" artisan key:generate
npm install --ignore-scripts
npm run build        # or: npm run dev, for a dev server with hot reload
```

### The database

The development database is **MariaDB**, or any MariaDB-compatible MySQL server. The
`.env` file that ships with the project already points at it, and you should keep those
values. The relevant variable **names** are:

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION` | The driver, `mariadb` for the development database |
| `DB_HOST`, `DB_PORT` | Where the server listens |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Which database and which account to use |

Two rules that matter for a reviewer:

- **Never point the test suite at the MariaDB development database.** The suite
  creates and throws away its own SQLite database on every run, so it cannot touch
  your data. See [Running the test suite](#running-the-test-suite).
- **Never run the demo seeder against a shared database.** It writes rows. See
  [Demo data](#demo-data).

### Demo data

A dedicated seeder builds a realistic preview account with several spaces and
testimonials spread across every moderation state, so that the inbox, the wall of love
and the dashboard chart all have something to show.

```bash
# Local preview only. This writes rows — never run it against a shared database.
"$PHP" artisan db:seed --class=DemoSeeder
```

| | |
| --- | --- |
| Email | `demo@kudosly.test` |
| Password | `password` |

`DemoSeeder` is **deliberately separate from `DatabaseSeeder`**, so it can never run by
accident. `DatabaseSeeder` only writes the two plan rows and one factory user; the demo
account, its spaces and its testimonials live exclusively in `DemoSeeder`, which has to
be named explicitly on the command line. The seeder also calls `PlanSeeder` itself, so
the two plan rows are present after seeding.

The demo account is on the Free plan, and every customer e-mail address it writes uses
the reserved `example.test` domain, so nothing it creates can accidentally reach a real
inbox.

### Running the application

```bash
# Starts the PHP server, the queue worker and the Vite dev server together.
composer dev
```

`composer dev` runs `php artisan dev`, which starts the Laravel application server, a
queue worker and the Vite development server with hot module replacement. Then open the
URL the command prints, by default <http://127.0.0.1:8000>.

If you prefer to run the pieces separately:

```bash
"$PHP" artisan serve        # the application
npm run dev                 # front-end assets with hot reload
"$PHP" artisan queue:work   # background jobs, only needed for e-mail delivery
```

If the pages look unstyled, the assets were not built. Run `npm run build` and reload.

---

## Running the test suite

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=":memory:" vendor/bin/pest --compact
```

**Result observed in this repository: 690 tests, 1925 assertions, all passing.**

The environment variables are not optional decoration. They point the suite at an
**in-memory SQLite database** that is created and discarded on each run, which is why
the suite can never touch the MariaDB development database. `phpunit.xml` sets the same
values, but passing them explicitly keeps the behaviour obvious on a machine where a
local `.env` points somewhere else.

To run a single file, use the same environment:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=":memory:" \
  vendor/bin/pest tests/Feature/Billing/WebhookTest.php
```

Two practical notes, both learned the hard way:

- The suite needs a valid `APP_KEY`. If `.env` does not exist yet, run
  `php artisan key:generate` first.
- A handful of tests assert on the rendered HTML of a page, including the presence of a
  stylesheet link. Those assertions fail unless the front end has been built, so run
  `npm run build` before the suite. On this repository the suite is green only after
  the asset build.

---

## A route-by-route walkthrough

Sign in with the demo account first, then work through the table below. Every URI in
it is the real URI from `php artisan route:list`, which reports 55 application routes.

| Area | Route | What to check |
| --- | --- | --- |
| Dashboard | `/dashboard` | The statistics cards (total testimonials, unique customers, submitted this month, average rating) are computed from the database. The submissions chart responds to the 7, 30 and 90 day ranges. Below it are the space list and the plan and usage bar. |
| Spaces list | `/spaces` | One row per space with its testimonial counter, its public link and the per-space actions. The search box filters the list. |
| Create space | `/spaces/create` | The space builder: name and slug, the question to ask, the star-rating toggle, the three themes and the optional-field configuration, with a live preview of the public page. |
| Edit space | `/spaces/{space}/edit` | The same form, pre-filled. Changes are saved with `PUT /spaces/{space}`. |
| Space fields | `/spaces/{space}/fields` | Which optional fields the public form shows, whether each is required, and the order they appear in. |
| Public collection page | `/s/{space:slug}` | Open this **signed out**, or in a private window. The form asks for a name, an e-mail address and the testimonial itself, shows the rating if the space has it enabled, and carries a consent checkbox. The page states plainly that the e-mail address is never displayed publicly. |
| Public submission | `POST /s/{space:slug}` | Submitting lands on `/s/{space:slug}/thanks` and creates a **pending** testimonial. It does not appear publicly. The route is rate limited to five attempts per ten minutes per IP address and is guarded by a honeypot field. |
| Inbox | `/inbox` | The moderation list, with search and filters for space, status and rating. Approve, hide, favourite, pin to the wall of love, edit and delete. The three-dot menu on each row opens **Edit testimonial**, **View public wall** and **Delete testimonial**. |
| Inbox edit | `/inbox/{testimonial}/edit` | The edit form for a single testimonial, saved with `PUT /inbox/{testimonial}`. |
| Wall of love (in-app) | `/wall-of-love` | Every testimonial the account has flagged with a heart, across all of its spaces. The page distinguishes the ones that are publicly eligible from the ones that are not, so you can see why something is not on the public wall yet. |
| Public wall | `/wall/{space:slug}` | Open this **signed out**. Only the hearted testimonials that are also approved and consented appear. No e-mail address is shown. |
| Widgets list | `/widgets` | The widgets belonging to the account, with their layout, theme mode and display limit, and the embed code to copy. |
| Widget builder | `/widgets/create` | Masonry or carousel layout, light or dark theme, background colour, the display limit and the animation toggle. |
| Widget preview | `/widgets/{widget}/preview` | The **rendered** widget, showing the testimonials it actually displays. |
| Widget embed | `/embed/{widget:public_id}` | The widget as a standalone document for an iframe. `/widgets/embed.js` serves the loader script and `/widgets/embed-data/{widget:public_id}` serves the same content as JSON. |
| Billing | `/billing` | The plan and usage bar, the current subscription state and the Free and Pro comparison. `POST /billing/checkout` starts a Stripe Checkout and `POST /billing/portal` opens the customer portal. |
| Profile and settings | `/profile` | The profile photo, the profile information form and the password form. The sidebar label for this page is **Settings**. The password is changed with `PUT /password`. |

---

## Where the tests live

The suite is Pest, split into a Feature half and a Unit half. `tests/Feature` holds
the behaviour tests, which exercise the application through its HTTP layer. `tests/Unit`
holds the isolated logic tests. `tests/Fakes/FakeStripeGateway.php` is the fake Stripe
gateway that the billing tests bind in place of the real one.

| Directory | What it covers |
| --- | --- |
| `tests/Feature/Auth/` | `AuthenticationTest.php`, `RegistrationTest.php`, `PasswordResetTest.php`, `ProfileTest.php`, `ProfilePhotoTest.php` and `AccountSwitchingTest.php` |
| `tests/Feature/Authorization/` | `SpacePolicyTest.php`, `TestimonialPolicyTest.php` and `WidgetPolicyTest.php` — that one account cannot reach another account's records |
| `tests/Feature/Billing/` | `BillingPageTest.php`, `CheckoutTest.php`, `PlanEntitlementTest.php`, `SubscriptionManagementTest.php` and `WebhookTest.php` |
| `tests/Feature/Dashboard/` | `DashboardTest.php` and `DashboardCopyTest.php` |
| `tests/Feature/Inbox/` | `TestimonialInboxTest.php` and `TestimonialModerationTest.php` |
| `tests/Feature/Spaces/` | `SpaceManagementTest.php`, `SpaceFieldsTest.php`, `SpaceThemeTest.php` and `PublicCollectionTest.php` |
| `tests/Feature/WallOfLove/` | `PublicWallTest.php` |
| `tests/Feature/Widgets/` | `WidgetManagementTest.php`, `WidgetEmbedTest.php` and `WidgetEmbedScriptTest.php` |
| `tests/Feature/Navigation/` | `SidebarNavigationTest.php`, `SidebarAccountMenuTest.php` and `TopbarTest.php` |
| `tests/Feature/Models/` | `TestimonialScopeTest.php`, `SpaceHelperTest.php` and `UserEmailTest.php` |
| `tests/Unit/Billing/` | `PlanAndQuotaTest.php` |
| `tests/Feature/UserJourneyTest.php` | The end-to-end path: register, create a space, submit a testimonial as a visitor, approve it, put it on the wall of love and publish it in a widget |

`TestimonialScopeTest.php` is worth reading first if you are checking the privacy
rules, because it is where the public visibility scope is pinned down.

---

## The data model in one screen

The full column-level description, with an entity-relationship diagram, is in
[`data-model.md`](data-model.md). This is the short version.

| Entity | Purpose | Key relationships |
| --- | --- | --- |
| `users` | The account that owns spaces and pays for the plan | Belongs to one `plans` row; has many `spaces` |
| `plans` | The Free and Pro plan definitions and their limits | Referenced by `users.plan_slug` |
| `spaces` | A branded collection page with a public URL | Belongs to a `user`; has many `space_fields`, `testimonials` and `widgets` |
| `space_fields` | Which optional fields the collection form shows | Belongs to a `space` |
| `testimonials` | One submitted testimonial | Belongs to a `space` |
| `widgets` | An embeddable display of a space | Belongs to a `space`; addressed publicly by `public_id` |
| `stripe_webhook_events` | Processed Stripe event ids, so a replay cannot double-apply | — |
| `subscriptions`, `subscription_items` | Laravel Cashier billing state | Belong to a `user` |

Two structural decisions are worth a reviewer's attention. The plan limits live in the
database rather than in code, so the same number drives the UI counters and the
server-side checks. And `widgets.public_id` is a long random id rather than a
sequential one, so the number of widgets in an account cannot be guessed from a link.

---

## The plan limits

The limits are stored in the `plans` table and read through `app/Billing/PlanCatalog.php`.
Changing a limit is a data change rather than a code change.

| Plan | Price | Spaces | Testimonials per space |
| --- | --- | --- | --- |
| Free | $0 | 3 | 100 |
| Pro | $999 per year | 25 | 1,000 |

`database/seeders/PlanSeeder.php` writes exactly these two rows, keyed on the plan
slug so that a repeated run updates them instead of inserting duplicates. The price is
stored **in cents**, so the Pro price is `99900` in the database and `$999` in the
interface. The seeder deliberately never touches `stripe_price_id`, so a configured
Stripe price cannot be erased by a later run.

`PlanCatalog` is the only read path. It resolves the plan from the stored slug, so
reading a plan costs no API call to Stripe: the webhook writes `users.plan_slug` when
a subscription starts and when it ends. It also raises rather than falling back to a
hardcoded default when a plan row is missing, because a missing row is a
misconfiguration an operator has to fix.

The limits are enforced server side, not only shown in the interface. An account at
its space quota cannot create another space, and a space that has reached its
testimonial quota stops accepting new submissions.

---

## The privacy model

This is the part of the submission a reviewer is most likely to test, so it is worth
being precise about.

**A testimonial's e-mail address is never exposed publicly.** It is stored on the
record, because the owner needs to be able to follow up, but no public endpoint
returns it. The public wall renders only the name, the company, the text and the
rating. The widget JSON payload is explicit about it — the payload carries
`customer_name`, `company_name`, `testimonial_text` and `rating`, and nothing else.

**A testimonial appears publicly only when all three conditions hold:** it is
approved, the visitor gave sharing consent, and it has not been soft-deleted. That
single decision lives in one place, the `publiclyVisible` scope on the `Testimonial`
model. Here it is, verbatim from `app/Models/Testimonial.php`:

```php
/**
 * Only testimonials that are eligible for public display.
 *
 * A testimonial must be approved and have explicit sharing consent.
 */
public function scopePubliclyVisible(Builder $query): Builder
{
    return $query->where('status', TestimonialStatus::Approved)
        ->where('sharing_consent', true);
}
```

The third condition — the soft delete — is enforced by the `SoftDeletes` trait, which
the model uses, so a deleted row is excluded by Eloquent's global scope wherever the
query runs. The scope therefore needs to say only two things: the row must be
approved, and the customer must have consented.

Two consequences follow from this design, and both are deliberate:

- **Favouriting or hearting a testimonial never makes it public.** The public wall
  applies `wallOfLove()` and then `publiclyVisible()` on top of it, so a hidden
  testimonial or one without consent stays off the public wall even when the owner has
  flagged it. The account-side wall of love page at `/wall-of-love` still lists it, so
  the owner can see why it is not published yet.
- **Approving is not enough on its own.** The consent checkbox on the public form is a
  real gate, and the server checks it.

**Soft deletes apply to `spaces`, `testimonials` and `widgets`.** A deleted record is
hidden from the inbox, the wall and every widget, but the row is kept so the deletion
can be undone and so a submission id cannot be reused by accident.

---

## Verifying the screenshots

The fifteen images in [`screenshots/`](screenshots/README.md) are **genuine browser
captures of this application**, taken by hand against the running code base.
`docs/screenshots/README.md` lists every one of the fifteen with a description of what
it shows.

To check them against your own running application:

1. Get the application running and sign in with the demo account.
2. Open the image and the corresponding page side by side.
3. Compare the layout, the wording, the ordering and the colours.

The captures were taken from a working account rather than from the seeded preview, so
the **account holder's display name** appears in the page header and in the sidebar,
and one testimonial is shown with its author's name, company and star rating. That is
the only customer data in the images. No e-mail address, password, payment detail, API
key or Stripe identifier appears in any of them. The profile and settings forms are
shown empty, and the public pages show no e-mail address at all — which is the
application's own privacy behaviour, described above.

What the captures do **not** show, stated plainly:

- **They are desktop-width only.** There is no mobile set, so the responsive
  behaviour is not evidenced by the screenshots.
- **The widget is shown in the built-in preview** at `/widgets/{widget}/preview`,
  rather than embedded on a third-party page. The embed code itself is on the widgets
  list, but no capture shows it running on a host site.
- **The billing page shows the Free-plan state**, because no live Stripe subscription
  exists to upgrade to. The Pro column is shown as a comparison, not as an active
  subscription.
- **There is no screen recording.** The captures are still images.

---

## Known limitations and honest gaps

These are listed so the state of the submission is not overstated. Nothing below is a
defect in the application; each is either a missing third-party credential or a
deliberate scope boundary.

| Gap | What it means | What would close it |
| --- | --- | --- |
| **No live Stripe subscription** | The checkout, the customer portal and the webhook handling are written and tested against a fake gateway, but the Pro upgrade path cannot be exercised end to end against a real account. The $999 price is enforced everywhere from the `plans` table, but no live charge has been taken. | `STRIPE_KEY`, `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` from a Stripe account, and a product priced at $999 per year |
| **No SMTP provider** | The mail layer works and the `log` mailer writes messages to `storage/logs/laravel.log`. The password-reset flow is verified end to end except for the final hop: the token is created, hashed, expires correctly, is single use and deletes itself after use. What has never happened is a message reaching a real inbox. | A mail provider: host, port, username, password, encryption and a verified sender address |
| **Rate limiting and honeypot on the public form** | The public submission route at `POST /s/{space:slug}` accepts five attempts per ten minutes per IP address, and a honeypot field named `website` filters bots silently so that a bot cannot tell it was filtered. Both behaviours are covered by tests. This means a reviewer submitting repeatedly from one machine will eventually receive HTTP 429. | Nothing — this is intended behaviour, and it is worth knowing about before you try to hammer the form |
| **Nothing has been deployed** | The application has never been run on a production server. A complete runbook exists at [`deployment.md`](deployment.md) and it is marked as a draft for review; it also lists the prerequisites that only an operator can satisfy, including hosting, a domain, an e-mail provider and a Stripe account. | A real server, and a decision on each item in the runbook's outstanding prerequisites |
| **No queued e-mail in practice** | The queued jobs are configured and the worker command is documented, but no worker has been run against a live mail transport. | A running queue worker (`php artisan queue:work`) and a configured mail provider |

Two further notes on scope. The Pro price of **$999 per year** is the figure the PRD
carries, and it is applied consistently in the seeder, the plan catalogue and the
billing page. And the "Settings" page a reviewer sees in the sidebar is the profile
page at `/profile`; there is no separate settings route, because the settings are the
profile information, the profile photo and the password form.

---

## Related documentation

| Document | Contents |
| --- | --- |
| [`../README.md`](../README.md) | The project overview, the technology stack, the completed feature list and the database overview |
| [`../PRD.md`](../PRD.md) | The product requirements the application was built against |
| [`../CLAUDE.md`](../CLAUDE.md) | The development conventions, the hard rules and the pre-commit checklist |
| [`data-model.md`](data-model.md) | The entities, columns, relationships and the entity-relationship diagram |
| [`deployment.md`](deployment.md) | The production deployment and operations runbook |
| [`screenshots/README.md`](screenshots/README.md) | The fifteen screenshots, with a description of each |
