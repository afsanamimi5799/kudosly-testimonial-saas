---
name: kudosly-doc-review
description: Verify Kudosly documentation and screenshot assets for link integrity, privacy leaks and secret exposure. Use when reviewing docs/, README.md or docs/screenshots/ before a commit.
argument-hint: "[paths to review, e.g. README.md docs/]"
when_to_use: Use when documentation, Markdown files or screenshot assets need a link, privacy and secrets check before they are committed or shipped.
allowed-tools:
  - Bash(git diff:*)
  - Bash(git status:*)
  - Bash(git log:*)
  - Bash(git show:*)
  - Read
  - Glob
  - Grep
  - LS
version: 1.0.0
---

# Kudosly documentation and privacy review

You are reviewing Kudosly documentation and screenshot assets for three classes of
defect: broken Markdown links, personal information that should not be published,
and credentials or secrets that must never enter the repository.

This is a **read-only review**. Report findings; do not fix them unless the user
explicitly asks for fixes.

## Scope

Review the files the user names. If no paths are given, review `README.md`,
`docs/**/*.md` and the screenshot assets under `docs/screenshots/`.

The skill directory is `${PUKU_CLI_SKILL_DIR}`.

## Phase 1 — Establish the review set

1. Run `git status --short` and `git diff --name-only` to see what is actually
   changed. Review the changed files plus any files the user named.
2. List the screenshot assets with `Glob` if `docs/screenshots/` is in scope.

## Phase 2 — Markdown link integrity

For every Markdown file in scope, find every image and link target and check that
it resolves on disk.

- Links may be written as `![alt](path)` or `![alt](<path with spaces>)`.
  **The angle-bracket form is required** for any filename containing a space,
  otherwise the link silently fails to render.
- Resolve each target relative to the directory of the Markdown file that
  contains it, not relative to the repository root.
- Check relative links to other Markdown files as well as images.

Report each target that does not exist, with the Markdown file and the target.

## Phase 3 — Secret exposure

Search the documentation text **and the raw bytes of every image asset** for:

| Pattern | Why it matters |
| --- | --- |
| `pk_live_`, `pk_test_`, `sk_live_`, `sk_test_`, `rk_live_` | Stripe keys |
| `whsec_` | Stripe webhook secrets |
| `Bearer <token>` | API bearer tokens |
| `password` / `secret` / `api_key` followed by a literal value | Hardcoded credentials |
| `-----BEGIN * PRIVATE KEY-----` | Private keys |

Also check for **real credential values in the documentation prose**, and for
`.env` contents. Naming an environment variable such as `STRIPE_SECRET` is
correct and must **not** be reported; only a value assigned to it is a finding.

## Phase 4 — Personal information

Kudosly has a deliberate privacy model, and documentation must not undermine it.

- A testimonial's `customer_email` must never appear in any document or image.
  This is the application's own rule, enforced by the `publiclyVisible` scope on
  the `Testimonial` model.
- The reserved domain `example.test` and the local demo account
  `demo@kudosly.test` are safe; report them as acceptable, not as findings.
- Any other e-mail address is a finding. Classify it as **blocking** if it is a
  real address, and note whether it appears in prose, in an image, or in both.
- A person's display name appearing in a screenshot is a **disclosure, not a
  leak**. Report it as such, and say plainly whether any credential, payment
  detail or e-mail address accompanies it.

## Phase 5 — Report

Produce a markdown report with these sections, and omit any section with no
findings:

```markdown
## Review summary
<files reviewed, and the headline verdict>

## Broken links
| File | Target | Problem |

## Secrets
| File | Pattern | Line or location | Severity |

## Personal information
| File | What is visible | Classification | Severity |

## Notes
<anything that is correct but worth stating, e.g. "no mobile screenshot set">
```

**Severity vocabulary** — use exactly these words:

- `blocking` — must not be committed: a credential, a private key, or a real
  e-mail address.
- `disclosure` — should be acknowledged in the document: a person's name or
  company, with no credential alongside it.
- `info` — an observation only.

End the report with a one-line verdict, one of:

- `VERDICT: clean` — no blocking finding, no broken links.
- `VERDICT: clean with disclosures` — no blocking finding, but names or
  similar are visible and should be stated in the document.
- `VERDICT: blocked` — at least one blocking finding.
