Contributing

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
