# Testing Strategy — Laravel (Pest v4)

## Tiers
1) **Unit** — Services, Policies, FormRequests, Actions.
2) **Feature (API)** — Endpoints and flows with DB.
3) **Browser (E2E)** — Inertia UI critical paths via Pest Browser.

## Concrete Tests

### Unit
- Services:
  - TurnAlgorithmService: random / round-robin / weighted picks
  - Fairness metrics: balances after N turns
- Policies:
  - GroupPolicy: view/update/delete for owner/member/guest
- FormRequests:
  - StoreGroupRequest: validation, messages, min/max participants
- Events/Jobs:
  - TurnAssigned event dispatch; NotifyTurn job retry/backoff

### Feature (API)
- Auth:
  - /auth/firebase/exchange → returns API token; invalid ID token rejected
- Groups:
  - CRUD + access control (owner, member, guest)
- Participants:
  - add/remove; duplicates rejected when rules forbid
- Turns:
  - /turns/next executes algorithm, records history, returns result
  - /turns/history paginates + orders desc

### Browser (E2E)
- Login → create group → add participants → run turn (all algorithms)
- History & fairness page shows updates
- Notifications banner appears when mocked

## Commands
- `composer test`
- Lint: `./vendor/bin/pint`
- Static: `vendor/bin/phpstan analyse --level=max`
