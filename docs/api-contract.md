# API Contract — Authoritative (Laravel)

> Source of truth for **all clients** (Flutter mobile + Inertia web).  
> Any change here must be accompanied by updated **feature tests** and a sync note to Flutter.

## Auth
### POST /api/v1/auth/firebase/exchange
- Request: `{ idToken: string }`
- Response: `{ token: string, user: {...} }`
- Notes: Verifies Firebase ID token, issues API token (Sanctum/JWT).

## Groups
### GET /api/v1/groups
### POST /api/v1/groups
### GET /api/v1/groups/{id}
### PATCH /api/v1/groups/{id}
### DELETE /api/v1/groups/{id}`
- Model: `Group { id, name, description?, settings{ algorithm, allow_duplicates }, ... }`

## Participants
### GET /api/v1/groups/{id}/participants
### POST /api/v1/groups/{id}/participants
### DELETE /api/v1/groups/{id}/participants/{pid}

## Turns
### POST /api/v1/groups/{id}/turns/next
- Body: `{ algorithm_override?: "random"|"round_robin"|"weighted", exclude?: string[] }`
- Response: `{ selected: Participant, history_entry: TurnHistory }`

### GET /api/v1/groups/{id}/turns/history?limit=50

## Devices (Notifications)
### POST /api/v1/devices
- Body: `{ token: string, platform: "ios"|"android"|"web" }`

## Errors
- Envelope: `{ error: { code, message, details? } }` with proper HTTP status.

## Versioning
- Prefix: `/api/v1`
- Breaking changes require minor version bump and client sync.

## Contract Change Process
1) Update this doc and add/adjust feature tests.
2) Post a message to `turns-flutter/sync/inbox.md` (and `turns-laravel/sync/outbox.md`).
3) Merge only when CI is green.
