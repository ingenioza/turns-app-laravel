# MVP Checklist — Laravel 12 + Inertia v2 (Living)

## Phase 0 — Bootstrap
- [x] Add `/docs` and instruction file (COPILOT_INSTRUCTIONS.md added)
- [x] Install Pest v4; set up CI (Pest + Pint) (Pest v3.8.4 + CI workflow)
- [x] Decide and scaffold Inertia v2 with **React or Vue** (React scaffolded with TypeScript)
- [x] Tailwind CSS configured (vite plugin + base stylesheet)

## Phase 1 — Auth & Users
- [x] Firebase token verify middleware
- [x] `/auth/exchange` endpoint
- [x] Map external identity to local users
- [x] Tests: happy/invalid token

## Phase 2 — Groups & Participants
- [x] Migrations: users, groups, participants, group_user, devices, turns
- [x] Spatie/laravel-permission roles: member, admin (+ leader → admin)
- [x] Policies for Group, Participant, Turn
- [x] API: groups CRUD/join + participants endpoints (with tests)
- [ ] Web: groups pages (list/create/join), participants management

## Phase 3 — Web Pages (Current)
- [ ] Choose React OR Vue for Inertia v2 frontend
- [ ] Groups list/create/join pages
- [ ] Group details and member management
- [ ] Turn UI and history display
- [ ] Browser tests with Pest v4

## Phase 3 — Algorithms & Turns
- [ ] Services: Random, RoundRobin, Weighted
- [ ] API: `POST /groups/{id}/turns/next` + `GET /groups/{id}/turns/history`
- [ ] Web: run next turn UI + history
- [ ] Tests: fairness, idempotency guards

## Phase 4 — Notifications
- [ ] `POST /devices` registry
- [ ] Event `TurnAssigned` → Listener → Job fan‑out
- [ ] Tests: queue fake assertions

## Phase 5 — Hardening & Polish
- [ ] Rate limiting
- [ ] Policy deny‑by‑default checks
- [ ] Error structure consistency
- [ ] Accessibility pass on web views
