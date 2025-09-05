Branch Protection Policy

main branch

- Allowed direct pushes: disabled
- Allowed PR sources: develop only (enforced by pr-policy workflow + manual rule)
- Required status checks (must pass before merge):
  - php-ci (format, static analysis, tests, composer validation)
  - pr-policy (title, branch naming, size, labels, source restriction)
  - push-guard (ensures no direct pushes; mark required)
  - labels-sync (optional informational) – not required
- Require branches up to date before merging: enabled
- Required reviews: 1 (CODEOWNER @ingenioza)
- Dismiss stale approvals on new commits: enabled
- Require approval of most recent reviewable push: enabled
- Include administrators: enabled
- Require signed commits: (optional – decide later; currently not enforced)

develop branch

- Allowed direct pushes: disabled for non-admin; allowed for automation if needed
- Required status checks:
  - php-ci
  - pr-policy
  - (optional) push-guard (surface accidental direct pushes)
- Required reviews: 1 (same reviewer) – may be relaxed later when team expands
- Require branches up to date before merge: enabled
- Dismiss stale approvals: enabled

feature/* branches

- No protection (short-lived). Must originate from latest develop.

Release hotfix procedure (future)

- If urgent production fix needed: create hotfix/* from main, PR -> main (review + checks), then immediately PR merge main -> develop to re-sync.

Configuration Steps (GitHub UI)

1. Settings > Branches > Add rule for main per above.
2. Add rule for develop per above.
3. (Optional later) Enable signed commits and linear history once contribution volume increases.
4. Periodically audit: Settings > Branches for drift.

Automation Notes

- pr-policy workflow already blocks PRs into main unless from develop; branch rule adds redundancy.
- labels-sync not required to avoid blocking merges due to label sync lag.
- Stale workflow helps prune inactive feature branches once PRs closed.
 - push-guard fails any direct push; required status check highlights violation immediately.

Workflow formatting

- All workflow YAML must use spaces (no tabs). If a workflow is ignored, convert tabs to 2 spaces.

Maintenance

- Revisit reviewer count when >1 active maintainer.
- Consider enabling Code Scanning (CodeQL) and dependency review gating once baseline is stable.
