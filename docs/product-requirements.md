# Product Requirements — Laravel 12 + Inertia v2

## API Features
- Auth: POST /auth/exchange (Firebase ID token → API token / session)
- Groups: create/join, participants CRUD, settings (notify: all/current/leader)
- Algorithms & Turns: random, round‑robin, weighted; `/turns/next`; history w/ fairness
- Notifications: device tokens registry; fan‑out on TurnAssigned

## Web UI (Inertia v2 + Tailwind)
- Authenticated dashboard
- Groups list/create/join
- Participants management
- Run Next Turn (choose algorithm, preview) + history
- Basic analytics (fairness summary)

## Non‑Functional
- P95 < 200ms typical
- Policies deny by default; rate limits on sensitive endpoints
- Pest v4 tests; Pint formatting
