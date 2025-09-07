# API Contract (Authoritative)

This API is the single backend for BOTH:
- Flutter Android/iOS mobile apps (`turns-flutter`)
- Laravel Inertia v2 web app (`turns-laravel` web)

All mobile and web clients MUST go through this API for:
- Auth (Firebase token exchange)
- Group/participant CRUD
- Turn algorithms (random, round-robin, weighted)
- Notifications (device token registration)
- History & fairness analytics

The mobile apps never connect directly to Firebase/Firestore except for authentication; all domain data lives in MySQL through this Laravel API.

Base: `/api/v1`

## Auth
POST /auth/exchange
- Body: `{ firebaseIdToken: string }`
- 200 → `{ accessToken: string, user: { id, name, email? } }`

GET /me
- Headers: `Authorization: Bearer <token>`
- 200 → current user

## Groups
POST /groups
- `{ name: string }` → `{ id, code, name, leaderId }`

POST /groups/join
- `{ code: string }` → `{ id }`

GET /groups/{id}
- details + participants

POST /groups/{id}/participants
- `{ name: string, accountUserId?: string }`

DELETE /groups/{id}/participants/{pid}

## Algorithms
GET /algorithms
- `["random","round_robin","weighted"]`

## Turns
POST /groups/{id}/turns/next
- `{ algorithm: "random"|"round_robin"|"weighted", options?: { weights?: Record<participantId, number> } }`
- 200 → `{ winner: { id,name }, turnId, occurredAt, nextHint? }`

GET /groups/{id}/turns/history?limit=50

## Notifications
POST /devices
- `{ platform: "ios"|"android"|"web", token: string }`

### Notes
- IDs: UUID/ULID
- Times: ISO‑8601 UTC
