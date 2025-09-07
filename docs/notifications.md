# Notifications — Server

- `devices` table: user_id, platform, token, last_seen_at
- On `TurnAssigned`:
  - notify all → push all group members
  - current → push only the selected user
  - leader → push the group leader
- Use queued Job for fan‑out with backoff + dead‑letter logging
