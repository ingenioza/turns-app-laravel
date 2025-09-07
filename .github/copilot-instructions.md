# GitHub Copilot Instructions — Laravel Project (Turns API & Web)

You are an expert Laravel/PHP developer working on the **Turns** backend API and the Inertia.js web UI.

## 🚦 Project Constraints (never change)
- **Backend**: Laravel 12.4.x, PHP 8.3+, MySQL 8+, Redis  
- **Frontend**: **Vue 3.5** + **Inertia.js 2.1**, Vite 7, Tailwind 4.1  
- **Packages**: spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-medialibrary  
- **Testing**: Pest v4 (unit, feature, **and browser** tests); QA with PHPStan (max level), Laravel Pint  
- **IDs**: UUID/ULID primary keys  
- **AuthZ**: Policies + Spatie permissions  
- **Validation**: **Form Requests only**  
- **Forbidden**: Jetstream/Fortify; stack changes without ADR approval

## Mission
Deliver a production-ready MVP:
- Firebase token exchange (mobile/web identity → API token/session)
- Groups CRUD & join via code/link
- Turn algorithms: random, round-robin, weighted (service pattern)
- History & fairness summaries
- Notifications fan-out (queue + retries)
- Inertia.js web UI (Vue 3.5 + Tailwind 4.1)
- **Pest v4** test coverage across **unit/feature/browser**

## API Contract Discipline
- This repo is the **authoritative contract** for **all clients** (Flutter mobile + Inertia web).
- Contract lives in `/docs/api-contract.md`.  
- When changing endpoints or shapes:
  1) Update `/docs/api-contract.md` + fixtures and **feature tests**,
  2) Post a sync message to Flutter (see **Cross-Repo Messaging Protocol**),
  3) Only then implement code changes and merge.

## Context Hygiene
- Treat `/docs/**` as the source of truth. Open only what you need per task.
- Always update `/docs/checklists/*`, `/docs/plans/next-actions.md`, and `/docs/progress.md` when behavior changes.
- Avoid silent drift between code and docs.

## Cross-Repo Messaging Protocol (Flutter ↔ Laravel)
Use Markdown “mailboxes” for asynchronous coordination.

**Files in this repo**
- `sync/inbox.md`   — Messages from Flutter to Backend.
- `sync/outbox.md`  — Messages from Backend to Flutter.

**Mirroring (if repos are siblings under the same parent folder):**
- Also write a copy to: `../turns-flutter/sync/inbox.md` (create if missing).
- If sibling path not available, **note “Mirror pending”** at the top of your `sync/outbox.md`.

**Message Format (append to the bottom)**
```markdown
## SYNC MESSAGE
From: laravel
To: flutter
Type: API_READY | QUESTION | BLOCKER | CONTRACT_CHANGE | RELEASE_TAG
Relates: /docs/api-contract.md#<anchor> (if applicable)
Summary: <1–3 sentences>
Checklist:
- [x] Contract updated
- [x] Feature tests updated
- [x] Sample payloads attached (see sync/fixtures/*.json)
Artifacts:
- Branch/PR: <branch or #id>
- Commit: <sha>
- Tag: vX.Y.Z (if release)
Timestamp: 2025-09-07T00:00:00Z
```

**Agent loop requirements**
- At task start and before PR: **read** `sync/inbox.md`, process any items addressed “To: laravel.”
- After contract/API changes: **write** a message to `sync/outbox.md` and mirror it.

## CI Failure Handling (mandatory)
- After pushing, **wait for CI**. Do not continue while red.
- If CI fails:
  1) Copy the exact error summary into `/docs/progress.md` under **CI Failures**,
  2) Reproduce locally (PHPStan, Pint, Pest, Vite),
  3) Fix; extend tests if needed,
  4) Push; repeat until green.

## CRITICAL: Git Commit Workflow
**NEVER FORGET TO COMMIT.** Commit small, logical slices frequently (every 30–60 minutes or completion of a test/feature).

### Commit Message Format
```
type(scope): brief description

feat(auth): Exchange Firebase ID token for API token
fix(groups): Prevent duplicate participant names in request
docs(api): Update /turns/next response schema
test(browser): Add E2E for turn assignment flow
```

---

## 🔄 Work Loop (after Phase 0, never skip)
1. **Select Task** → top unchecked item in active checklist  
2. **Branch** → `feature/<epic>-<short-task>` from `develop`  
3. **Plan** → `/docs/tasks/YYYYMMDD-<slug>.md` (Scope, AC, Touchpoints, Tests)  
4. **Implement** → small vertical slice (≤200 LOC or ≤10 files) with **Pest tests**  
5. **Validate** → **Pint**, **PHPStan (max)**, **Pest**, **Vite build** (web)  
6. **Commit** → Conventional commits, small chunks; push branch  
7. **Docs Update** → update `/docs/progress.md`; tick checklist item  
8. **PR** → to `develop` using template; **CI must be green**  
9. **Repeat** → after merge, pick next item  

## 🔒 Git Rules (must follow)
- Protected branches: **main**, **develop**  
- Branch names: `feature/...`, `fix/...`, `chore/...`  
- No giant commits; always commit in slices  
- Conventional commits only  
- **Feature work**: from `develop` → PR to `develop`  
- **Bugfixes**: `fix/<issue>` → `develop`  
- **Chores**: `chore/<task>`  
- **Release**: merge approved `develop` → `main`, tag `vX.Y.Z`  
- **Hotfix**: branch from `main` → merge back into `main` + `develop`  
- **CI must pass** before merging any PR.

## Order of Execution
Follow `/docs/checklists/mvp-checklist.md`. For each step:
1) Read relevant `/docs` sections,  
2) Implement incrementally,  
3) Write/extend **unit, feature, and browser tests**,  
4) Run tests; commit,  
5) Update checklists and `/docs/plans/next-actions.md`.

## Technology & Patterns (concise)
- **Laravel 12.4.x**, PHP 8.3+; MySQL 8; Redis queues; Horizon (optional)
- **Vue 3.5 + Inertia 2.1 + Tailwind 4.1**
- **FormRequests** for validation; **Policies** + Spatie for authz
- Services for business logic; Events/Listeners/Jobs for side-effects
- Notifications fan-out via queued Jobs (backoff + dead-letter logging)
- E2E/browser tests using **Pest v4 Browser** group

## Testing Scope (Pest v4)
- **Unit**: Services, Policies, FormRequests, Action classes
- **Feature (API)**: `/auth/exchange`, `/groups`, `/groups/join`, `/groups/{id}/participants`, `/turns/next`, `/turns/history`, `/devices`
- **Browser (E2E)**: login → create/join group → run turn (all algorithms) → history/fairness → notifications UI; error & offline states

## Performance & Security
- P95 API < 200ms; DB indexes + eager loading
- Rate limiting on sensitive endpoints
- Consistent error envelopes; no secrets in responses
- CSRF/session for web; token/JWT for API
