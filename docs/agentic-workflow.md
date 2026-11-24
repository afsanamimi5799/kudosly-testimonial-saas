# Kudosly — agentic workflow

This document describes how Kudosly was built with the assistance of an AI coding
agent, and it describes only what can be verified from the repository and from the
recorded session logs. Where a number, a timestamp or a file name appears below, it
was read from one of those two sources rather than estimated.

The companion disclosure in the root [`README.md`](../README.md) is a short summary.
This document is the long form, with the evidence behind each claim.

---

## Table of contents

- [The tool that was used](#the-tool-that-was-used)
- [Subagents](#subagents)
- [The project skill](#the-project-skill)
- [The verification loop](#the-verification-loop)
- [Database isolation](#database-isolation)
- [Parallel and branch-based work](#parallel-and-branch-based-work)
- [What was not used](#what-was-not-used)
- [Related documentation](#related-documentation)

---

## The tool that was used

All AI-assisted work in this repository was carried out through **Puku CLI**, a
command-line coding agent. It was used for five things.

**Reading and understanding the code base before every change.** No file was edited
before it had been read. The agent's own instructions in
[`../CLAUDE.md`](../CLAUDE.md) require it, and the session logs show the pattern:
a change is preceded by a survey of the files it touches, and by an explicit read of
the relevant section of [`../PRD.md`](../PRD.md).

**Planning against the PRD, phase by phase.** The PRD splits the product into
phases. Work was planned against one phase at a time, and the plan was reviewed
before implementation rather than after it. Phase 3, "Collection and Inbox", is
the clearest surviving example: it was audited with two dedicated subagents before
any code was written for it, and the resulting inbox, moderation and wall-of-love
behaviour is covered by 62 tests in `tests/Feature/Inbox/`.

**Implementation.** Controllers, models, migrations, policies, Form Requests, Blade
views, tests and the front end were all written with the agent's help. The
resulting tree is the one described in the project structure section of the
`README.md`: 19 controllers grouped by area, 7 models, 3 policies, 58 Blade
templates and a Tailwind CSS 4 front end.

**Verification.** Every change was followed by the focused test file, then the
full suite, then Laravel Pint, and — for anything that touched the interface — an
automated browser check with Playwright. The browser checks were driven by Node
scripts that used the Playwright package installed with `npm install --no-save
playwright`, followed by `npx playwright install chromium` for the browser binary.
Those scripts were written to a scratch directory outside the tracked source tree
and deleted once the check was finished.

**Diagnosis by measurement rather than guesswork.** The clearest example in this
code base is the inbox action menu. The three-dot dropdown on a testimonial was
being clipped, and the cause was an `overflow: hidden` ancestor: the testimonial
list was wrapped in `overflow-hidden`, and the dropdown is absolutely positioned
inside it. Because an absolutely positioned element cannot escape an ancestor's
overflow rule, raising the `z-index` would not have helped.

The agent did not guess at this. It measured the rendered geometry in a real
Chromium browser and found that the clipping container's bottom edge coincided
exactly with the bottom of the menu on every one of twelve seeded testimonials. It
then ran a hit test at the page bottom, where `document.elementFromPoint` on the
"Delete testimonial" button returned the surrounding `<main>` element rather than
the button, which proved the action was not merely hidden but completely
unreachable. A screenshot confirmed the menu was reduced to a sliver of about
eight pixels below the last card.

Only then was the cause confirmed by an experiment: setting `overflow: visible` on
the container in the live DOM made the button hit-testable again. The fix was
written after that proof. It removes `overflow-hidden` from the list container and
moves the rounded corners onto the first and last list items, so the design is
unchanged, and it adds a small vanilla-JS placement block that flips the menu
above its button when there is not enough room below and clamps it horizontally
so it stays inside the viewport.

The change was then verified across three viewports, with 36 of 36 cases passing,
every action confirmed to be inside the viewport and hit-testable. The commit is
`d34d13d`, "fix: prevent inbox action dropdown from being clipped".

---

## Subagents

The `Agent` tool was used with `subagent_type: "Explore"` to survey parts of the
code base in parallel instead of serially. An Explore subagent is read-only: it
reads files and reports back, and it does not modify anything.

**Nine Explore subagents** were dispatched across the two recorded sessions. Their
descriptions and timestamps below are taken from the session logs, which record
the tool call itself.

| # | Description recorded in the log | Area covered | Dispatched (UTC) |
| --- | --- | --- | --- |
| 1 | `Audit Space access patterns` | Every place a `Space` object is accessed, gathered before the `SpacePolicy` was written | 2026-11-18T10:43:27.722Z |
| 2 | `Audit dashboard view surface` | The existing view, asset and layout surface, gathered before the dashboard was built | 2026-11-18T11:06:20.570Z |
| 3 | `Audit Phase 3 test coverage` | Which Phase 3 behaviour was already covered by tests | 2026-11-19T23:26:29.102Z |
| 4 | `Audit Phase 3 Blade views` | The Blade views and Livewire components implementing the Phase 3 interface | 2026-11-19T23:26:31.540Z |
| 5 | `Audit public collection flow` | The public submission path, from the form to the stored testimonial | 2026-11-21T17:45:41.353Z |
| 6 | `Audit moderation, wall, widgets` | Moderation, the wall of love and the widget layer, surveyed together | 2026-11-21T17:45:44.877Z |
| 7 | `Verify Laravel throttle patterns` | The framework's own rate-limiting behaviour, read from the installed Laravel source | 2026-11-21T18:12:24.825Z |
| 8 | *(no description recorded)* | A map of the authenticated dashboard's route, controller and view structure, for a UI polish pass | 2026-11-22T22:37:01.123Z |
| 9 | `Map dashboard view structure` | The same dashboard area, surveyed again for the polish pass | 2026-11-22T22:37:06.369Z |

The two pairs at rows 3 and 4, and at rows 8 and 9, were dispatched seconds apart
from one another. The first pair went out at `2026-11-19T23:26:29.102Z` and
`2026-11-19T23:26:31.540Z`, two and a half seconds apart, and the second pair at
`2026-11-22T22:37:01.123Z` and `2026-11-22T22:37:06.369Z`, five seconds apart.
In both cases the second subagent was launched before the first had returned, so
the two genuinely ran **concurrently** rather than one after the other. The first
pair was additionally dispatched with `run_in_background: true`, so both results
came back in the same batch.

This parallelism mattered for the Phase 3 audit in particular. Surveying test
coverage and surveying the view layer are independent questions about the same
phase, and answering them at the same time shortened the gap between reading the
code and writing the next change.

---

## The project skill

One skill was written for this repository: `kudosly-doc-review`, at
`.puku-cli/skills/kudosly-doc-review/SKILL.md`. It is a project skill, committed
to the repository so that anyone reviewing the documentation can run the same
check.

It is a read-only review in five phases. It establishes the review set from the
working tree, then checks Markdown link integrity, then secret exposure, then
personal information, and finally reports in a fixed format.

**Link integrity.** Every image and link target in the files in scope is resolved
on disk, relative to the directory of the Markdown file that contains it. The
skill pays particular attention to filenames containing spaces, which must use
the angle-bracket form — `![alt](<docs/screenshots/Wall of Love_2.png>)` — or the
link silently fails to render.

**Secret exposure.** The documentation text *and the raw bytes of every image
asset* are searched for Stripe keys (`pk_live_`, `pk_test_`, `sk_live_`,
`sk_test_`, `rk_live_`), webhook secrets (`whsec_`), bearer tokens, hardcoded
credentials and private keys. The distinction the skill draws is the important
one: naming an environment variable such as `STRIPE_SECRET` is correct and is not
reported, while a value assigned to it is a finding.

**Personal information.** A testimonial's `customer_email` must never appear in a
document or an image, which is the application's own rule, enforced by the
`publiclyVisible` scope on the `Testimonial` model. The reserved `example.test`
domain and the local demo account are safe. A person's display name appearing in
a screenshot is treated as a disclosure rather than a leak, and the report has to
say plainly whether any credential, payment detail or e-mail address accompanies
it.

**Severity vocabulary.** Every finding is classified with exactly one of three
words: `blocking`, which means it must not be committed; `disclosure`, which
means it should be acknowledged in the document; and `info`, which is an
observation only. The report ends with one of three verdicts: `clean`,
`clean with disclosures`, or `blocked`.

### The invocation

The skill was genuinely invoked through the Puku CLI `Skill` tool. The session
log for this worktree,
`d60e45f4-9c97-4a4f-bf96-5eb74bf87e56.jsonl`, records it. On log line 6 there is
a tool-use block whose name is `Skill` and whose input is
`{"skill": "kudosly-doc-review", "args": "README.md docs/"}`. The response on log
line 8 is the loader's own reply, which begins with "Base directory for this
skill:" and continues with the resolved skill directory followed by the skill's
front matter.

The skill then ran its five phases and produced a report. The summary was that
there were **no broken links**, **no secrets** and **no e-mail address leaks**.
Every relative target resolved on disk, including the angle-bracket forms for the
image filenames containing spaces, and all internal anchor links in
[`deployment.md`](deployment.md) resolved to their headings. The only Stripe key
mentions in the documentation were truncated placeholders that are never assigned
values, `.env.example` contained no real credentials, and a scan of the raw bytes
of all fifteen PNG assets found no e-mail addresses and no credential material.

The report did find **two genuine disclosures**: the account holder's display name
appears in the page header and the sidebar footer of the screenshots, and the
space name appears as well. Neither is accompanied by a credential, a payment
detail or an e-mail address. The verdict at the end of the report was
`clean with disclosures`.

One further note from the report is worth recording here, because it is a
statement about the accuracy of the documentation rather than about the
application: the display name also appears as the *testimonial author* on the
public wall of love and inside the rendered widget, which are the two pages a
real customer would see. The `README.md` summary sentence did not make that
explicit, although `docs/screenshots/README.md` describes the same content more
precisely. The document in this repository refers to the name generically as "the
account holder's display name" for the same reason the screenshots README does.

---

## The verification loop

Every change was followed by the same four-step loop: the focused test file, the
full suite, Laravel Pint, and the browser when the change touched the interface.

PHP is not on `PATH` on this machine, so the full path to the Herd binary is
always used. The test suite is run like this:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=":memory:" \
  "/c/Users/TIKTOK.DESKTOP-QVMRCAP/.config/herd/bin/php85/php.exe" \
  vendor/bin/pest --compact
```

The style check uses the same binary:

```bash
"/c/Users/TIKTOK.DESKTOP-QVMRCAP/.config/herd/bin/php85/php.exe" \
  vendor/bin/pint --test
```

**The in-memory SQLite database is not a convenience; it is a safety mechanism.**
The development database for this project is MariaDB, and it holds the working
account, the real spaces and the real testimonials. If the test suite were to
connect to it, a test that wrote a row, truncated a table or ran a migration
would destroy genuine data that cannot be recovered from a repository. Setting
`DB_CONNECTION=sqlite` and `DB_DATABASE=":memory:"` for the test run means the
suite operates on a database that exists only for the duration of the process and
is discarded when it exits. It is not a file that can be left behind, and it
cannot be reached by any other process.

**The baseline is 690 tests and 1925 assertions, all passing.** This was
verified by running the command above against the committed state of the
repository. The distribution across the areas of the application is given in the
`README.md`.

**Pint is the second gate.** It is run in `--test` mode, which reports rather
than rewrites, so the check is read-only and cannot silently reformat the code
under review. A change is only considered finished once the full suite is green
and Pint reports no style issues in the files that were touched.

**The browser is the fourth gate, for interface work.** For the inbox action
menu alone, the verification ran at 1440×900, 768×1024 and 390×844 and produced
36 passing cases. The same change was also verified by the focused suite for that
area, which passed 62 tests with 196 assertions, and then by the full suite,
which stayed at 690 passed with 1925 assertions.

---

## Database isolation

Migrations and seeders are never run against the real MariaDB database. The
development database is MySQL/MariaDB; the test database is SQLite; and the two
are never mixed.

The isolation technique is to override the connection with environment variables
rather than by editing `.env`, so that the override applies to a single command
and cannot persist by accident. `DB_CONNECTION` is set to `sqlite` and
`DB_DATABASE` is set to the path of a temporary SQLite file **outside the
project directory**.

The order of operations matters, and it is: verify first, then act. Before any
migration or seeding runs, the effective connection is confirmed by asking the
framework itself, using `DB::connection()->getDriverName()`. The recorded check
printed the resolved configuration and confirmed that the connection actually in
use was the temporary SQLite file rather than the MariaDB database:

```text
target: C:/Users/TIKTOK~1.DES/AppData/Local/Temp/kudosly-capture.sqlite
=== isolation check ===
default=sqlite
driver=sqlite
database=C:/Users/TIKTOK~1.DES/AppData/Local/Temp/kudosly-capture.sqlite
driver_in_use=sqlite
```

Only after that check passed were `artisan migrate --force` and
`artisan db:seed --class=DemoSeeder` run against that file, and only then were
the row counts read back to confirm what had been written. The temporary file
was outside the repository, so it was never a candidate for being committed, and
it was deleted afterwards.

This is the same discipline that the browser checks followed. When the inbox
dropdown was verified, it was verified against an isolated SQLite database with
twelve seeded testimonials, on a local server started for the purpose and
stopped afterwards. The temporary harness files — the Playwright scripts, the
router and the seeded database — were removed, and the run left nothing behind.

---

## Parallel and branch-based work

The documentation work for this repository was carried out on separate branches in
separate Git worktrees, each located outside the repository directory, with one
file to write on each.

`git worktree list` reports three checkouts:

| Worktree | Branch |
| --- | --- |
| The main repository directory | `main` |
| A worktree under the system temporary directory | `feature/agentic-workflow-doc` |
| A second worktree under the system temporary directory | `feature/reviewer-guide` |

The worktrees sit outside the repository so that the agents working in them
cannot touch the main working directory, which may hold uncommitted changes that
must not be disturbed. Each branch was committed independently, and each was
merged back into `main` with `--no-ff` so that the history records a real merge
commit rather than a fast-forward. The result is that the branch structure is
visible in `git log --graph` instead of being flattened away.

Long-running commands were also started in the background so that other work
could continue in the foreground. The session logs record twelve such background
invocations in the main development session alone, covering test suites, asset
builds and local servers.

---

## What was not used

This section is deliberately precise, because an accurate disclosure is worth
more than a generous one.

**Claude Code was not used.** There is no Claude Code session history, no
`~/.claude` directory on this machine and no `.claude/` directory anywhere in
the repository. The `CLAUDE.md` and `AGENTS.md` files in the repository root are
hand-written project documentation, written for any coding agent that might work
on this code base, including Puku CLI. They are not generated artefacts and they
are not evidence that a particular tool was used.

**Laravel Boost is installed but was deliberately not run.** `composer.json`
requires `laravel/boost` at `^2.2` as a development dependency. The command
`php artisan boost:install` was never run, so `CLAUDE.md` and `AGENTS.md` contain
hand-written instructions rather than generated ones. `AGENTS.md` states this
itself, in its opening paragraph.

**No custom hooks were configured.** There is no hook configuration in the
project settings.

**No MCP servers were configured.** The project entry in
`~/.puku-cli/puku-cli.json` carries an empty `mcpServers` object and an empty
`enabledMcpjsonServers` list, and there is no `.mcp.json` file anywhere in the
repository. The browser checks described above were therefore driven by Playwright
through Node scripts run by the shell, not through a Playwright MCP server.

**No custom skills were written beyond the single project skill.** The
`kudosly-doc-review` skill described above is the only skill authored for this
repository.

---

## Related documentation

| Document | Contents |
| --- | --- |
| [`../README.md`](../README.md) | The project overview and the short-form AI tooling disclosure |
| [`../PRD.md`](../PRD.md) | The specification the application was built against |
| [`../CLAUDE.md`](../CLAUDE.md) | Development instructions for any coding agent, including the database rules |
| [`data-model.md`](data-model.md) | Entities, columns, relationships and an ER diagram |
