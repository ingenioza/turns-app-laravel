# Project Docs

> 🚫 **Do not delete the `docs/` folder.** CI guards and CODEOWNERS protect this path.  
> Keep docs current — they’re the source of truth for planning, checklists, and test scope.

## 🔄 Work Loop (after Phase 0, never skip)
1. **Select Task** → top unchecked item in active checklist  
2. **Branch** → `feature/<epic>-<short-task>` from `develop`  
3. **Plan** → `docs/tasks/YYYYMMDD-<slug>.md` (Scope, AC, Touchpoints, Tests)  
4. **Implement** → small vertical slice (≤200 LOC or ≤10 files) with tests  
5. **Validate** → run formatters, linters, **all tests**  
6. **Commit** → Conventional commits, small chunks; push branch  
7. **Docs Update** → update `docs/progress.md`; tick checklist item  
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
- CI must pass before merging any PR.

## Files in this folder
- `api-contract.md` — canonical API (server) or client mirror (mobile)
- `testing-strategy.md` — what to test at each tier
- `checklists/mvp-checklist.md` — the living MVP checklist
- `plans/next-actions.md` — short rolling plan (≤ 150 lines)
- `progress.md` — log of CI failures, decisions, and status
