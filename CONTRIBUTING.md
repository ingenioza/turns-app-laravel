Contributing

Workflow
- Use Gitflow: feature/* -> develop -> main
- Keep PRs small, focused, and test-backed.
- PRs into main must come from develop and represent production-ready state.

Quality Gates (must pass before merge)
1. All tests green (feature + unit) and coverage threshold met.
2. Static analysis (PHPStan / Larastan) no errors of configured level.
3. Code style (Pint) applied.
4. No debug/output artifacts committed.

Commit Messages
- Conventional format: type(scope): description
  Examples:
  feat(turns): add forced end turn endpoint
  fix(groups): correct member stats calculation
  chore(ci): add coverage badge generation
  docs(api): document error response schema

Pull Request Checklist
- [ ] Conventional title
- [ ] Linked issue (if applicable)
- [ ] Tests added/updated
- [ ] Docs updated (README / PROJECT_BRIEF / Postman)
- [ ] Static analysis & style pass locally
- [ ] No leftover debug dumps / dead code

Branches
- main: production only (protected)
- develop: integration branch
- feature/*: short‑lived, single concern
- chore/* or fix/* as needed

Releases
- Merge develop -> main only when release criteria satisfied.
- Tag semantic version after merge (e.g., v0.1.0).

Security
- Never commit secrets. Use .env and vault mechanisms.
- Report vulnerabilities privately to maintainers.

Review Guidelines
- Prefer focused diffs; request split if too large.
- Confirm negative as well as positive test coverage.
- Question domain invariants & error handling paths.

Thank you for contributing.Contributing

- Use Gitflow: feature/* -> develop -> main
- Small, test-backed PRs only. PRs into main must come from develop.
- Keep Copilot instructions concise; move details to docs and re-trim when over threshold.
- Before pushing: run Pint, PHPStan, and tests locally.
- Required reviewers: 1 (currently @ingenioza is sole reviewer / CODEOWNER). Merge only after their approval.

PR checklist

- [ ] Conventional PR title (feat|fix|chore|docs|refactor|perf|test|ci)
- [ ] Updated docs/tests
- [ ] No static analysis errors; all tests pass
 - [ ] Reviewed & approved by @ingenioza
