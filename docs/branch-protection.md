Branch Protection Policy (Consolidated)

Branches
- main: production only; fast-forward or merge from hardened develop; no direct pushes (enforced by push-guard + protection rules).
- develop: integration branch; feature branches merge here via PR.

Requirements for Merging into main
1. All tests green.
2. Static analysis (phpstan) passes.
3. No debug artifacts / backup files.
4. Documentation updated for user-facing or API changes.
5. Conventional commits and PR title.

PR Policy
- Enforced semantic titles.
- Small, reviewable scope (< ~400 lines diff preferred, excluding generated docs).
- Linked issue or clear rationale in description.

Automation
- push-guard prevents direct pushes to main.
- php-ci runs tests on develop PRs.
- labels-sync keeps labels consistent.
- stale marks inactive issues/PRs.

Fast-Forward Strategy
When develop is production-ready: update main via ff-only merge to preserve linear history.

Security
Never merge secrets. Review .env.example and workflows for secret misuse.

Revision
This policy evolves; propose changes via docs update PR.

main branch

- Allowed direct pushes: disabled
- Allowed PR sources: develop only (enforced by pr-policy workflow + manual rule)
- Required status checks (must pass before merge):
  - php-ci (all jobs)
  - pr-policy (title & source validation)
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
