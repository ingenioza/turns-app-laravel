# COPILOT INSTRUCTIONS — TURNS API & WEB (Laravel 12 + Inertia v2)

You are the execution engine for the **server + web app** in `turns-laravel/`.

## Mission
Deliver a production-ready MVP using:
- **Laravel 12**
- **Pest v4** (unit, feature, and browser tests for full end-to-end coverage)
- **PHPStan** (max level) for static analysis
- **Laravel Pint** for code style
- **Tailwind CSS v4**
- **Inertia.js v2** for the web front-end
- Choose exactly **one**: latest **React** OR latest **Vue** for Inertia v2, then stick to it
- Features: Firebase token exchange; Groups CRUD/join; Turn algorithms (random, round-robin, weighted); History & fairness; Notifications fan-out

## Architecture (Non-Negotiable)
- Validation: **FormRequest** (never in controllers)
- AuthZ: **Policies** and **Spatie/laravel-permission**
- Business logic: **Services**; controllers remain thin
- Side-effects: **Events/Listeners/Jobs** (e.g., notify on TurnAssigned)
- DB: **MySQL**; Queues: **Redis**
- IDs: UUID/ULID primary keys  
- Backend: Laravel 12.4.x, PHP 8.3+, MySQL 8+, Redis  
- Frontend: pick React OR Vue and keep the stack consistent + Inertia.js 2.1, Vite 7, Tailwind 4.1  
- Testing: Pest v4 (unit, feature, browser), QA with PHPStan (max level), Laravel Pint  
- Consistent API contract (see `/docs/api-contract.md`)

## Context Hygiene
- All detailed specs are in `/docs/`. Open only the files you need for the current step.
- Always **update docs** as the system evolves (e.g., `/docs/progress.md`, `/docs/checklists/`, `/docs/plans/next-actions.md`).
- Never work from memory alone; confirm requirements in docs before coding.

## 🔄 Work Loop (never skip)
1. **Select Task** → top unchecked item in active checklist  
2. **Branch** → `feature/<epic>-<short-task>` from `develop`  
3. **Plan** → create `/docs/tasks/YYYYMMDD-<slug>.md` (Scope, AC, Touchpoints, Tests)  
4. **Implement** → small vertical slice (≤200 LOC or ≤10 files)  
   - Always add **unit + feature + browser tests** with Pest v4  
   - Browser tests must simulate full user flow (auth → group → turn → notification UI)  
5. **Validate** → Pint, PHPStan (max), Pest (all suites), Vite build (if frontend)  
6. **Commit** → Conventional commits, small slices; push branch  
7. **Docs Update** → tick checklist item; update `/docs/progress.md` and `/docs/plans/next-actions.md`  
8. **PR** → to `develop` using template; CI must be green  
9. **Repeat** → after merge, pick next item

## Git & CI
- Small, green commits with Conventional Commits
- CI runs: Pest v4 (unit + feature + browser), PHPStan (max), Pint, Vite build
- Disallow merge if CI fails

## 🔒 Git Rules (must follow)
- Protected branches: `main`, `develop`
- Branches:
  - Features: `feature/<epic>-<short-task>` from `develop`  
  - Fixes: `fix/<issue>` from `develop`  
  - Chores: `chore/<task>` from `develop`  
  - Hotfixes: `hotfix/<issue>` from `main` → merged back into `main` + `develop`
- Releases: merge approved `develop` → `main`, tag `vX.Y.Z`
- No giant commits; all commits must be in small, test-covered slices

## Order of Execution
Follow `/docs/checklists/mvp-checklist.md` for this repo. For each step:
1. Read doc sections
2. Implement incrementally
3. Write/extend **unit, feature, and browser tests** for every new/changed flow
4. Run tests; commit
5. Update checklists and `/docs/plans/next-actions.md`

## When Uncertain
- Use safe defaults
- Add TODOs with assumptions in `/docs/` and proceed
- Ask only if blocked