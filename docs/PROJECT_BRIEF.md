# Project Brief: Turns Platform (Backend & Turn Lifecycle)

## 1. Executive Summary
The Turns platform enables structured, auditable turn-taking within user-created groups. Core lifecycle operations (start, complete, skip, force-end) plus retrieval endpoints (current, active, history, listings, statistics) are now fully implemented and passing all existing feature tests (14/14). Earlier instability caused by a corrupted `TurnController` and response schema mismatches has been resolved via a clean controller reconstruction and contract alignment with the test suite.

## 2. Original Objectives
- Provide robust Turn lifecycle management (start → active → complete / skip / expire).
- Enforce membership / ownership / admin rules.
- Expose endpoints for real‑time state (current, active) and historical insight (history, stats, listings).
- Achieve/enable target of ≥95% test coverage (current: functional tests pass; coverage measurement pending).
- Prepare foundation for richer domain & DDD layering (Actions, Services, Policies) described in architecture docs.

## 3. Key Achievements
| Area | Outcome |
|------|---------|
| Controller Stability | Rebuilt `TurnController` from scratch, eliminating stale/partial file variants. |
| Lifecycle Integrity | Consistent setting of `started_at`, `ended_at`, `duration_seconds` across complete/skip/force-end. |
| Authorization | Group creator (admin) can manage all turns; members restricted to own active turns. |
| Response Contracts | Adjusted JSON keys to match tests (`data`, `active_turn`, `user_stats`, `group_stats`, `member_stats`). |
| Rotational Logic | Added `next_user` computation using ordered active members & last completed/expired turn. |
| Statistics | Implemented per-user & per-group aggregated metrics including duration aggregates & member breakdown. |
| Test Suite Alignment | All 14 feature tests (TurnApiTest) now green (69 assertions, 0 failures). |
| Extensibility Hooks | Helper methods (`getNextUser`, `formatDuration`) isolate logic for future service extraction. |

## 4. Issues Encountered & Resolved
| Issue | Impact | Resolution |
|-------|--------|-----------|
| Corrupted / stale `TurnController` variants (`.backup`, `.broken`) | Runtime executed outdated logic, masking edits | Full file rewrite; cache clear; removed reliance on partial patches. |
| Field naming mismatch (`completed_at` vs `ended_at`) | Duration & completion tests failed | Standardized on `ended_at` + `duration_seconds`. |
| Response key divergences (`turns`, `current_turn`, `stats`) | Failing JSON structure assertions | Unified to expected keys (`data`, `active_turn`, `user_stats`, etc.). |
| Cumulative query mutation in stats | Risk of incorrect counts | Rebuilt stats using cloned base queries. |
| Missing ordering context (`turn_order`) in `next_user` | Insufficient frontend context | Added `turn_order` to `next_user` & `group_members`. |

## 5. Current Architecture vs Planned Design
| Aspect | Planned (Docs) | Current Implementation | Gap / Next Step |
|--------|----------------|------------------------|-----------------|
| Layering | Controllers → Actions → Domain Services → Repos | Direct Eloquent calls in controller | Extract Actions & Services incrementally. |
| Policies / Authorization | Central Policies + Spatie roles/permissions | Inline checks (creator vs member) | Implement Policies & ability matrix. |
| Eventing / Activity Log | Events on lifecycle transitions | Not yet emitted | Fire domain events (TurnStarted/Completed/Skipped/Expired). |
| Caching | Strategic query caching | None | Add cache for stats & active turn queries. |
| Background Jobs | Deferred processing (notifications, analytics) | None | Queue events for notifications / analytics aggregation. |

## 6. Domain Model (Turn Focus)
Turn fields (essential): `id`, `group_id`, `user_id`, `status` (active|completed|skipped|expired), `started_at`, `ended_at`, `duration_seconds`, `notes`, `metadata (json)`.

Status transitions:
- Start: create (status=active, started_at=now)
- Complete: set status=completed, ended_at, compute duration
- Skip: status=skipped, ended_at, duration, inject `skip_reason`
- Force End: status=expired (admin only), capture reason & timing

## 7. Endpoint Inventory (Implemented)
Base prefix: `/api`
| Method | Path | Purpose | Key Response Keys |
|--------|------|---------|-------------------|
| GET | `/turns` | Paginated user-accessible turns | `data`, `meta` |
| POST | `/turns` | Start a new turn | `message`, `turn` |
| GET | `/turns/{turn}` | Turn detail | `turn` |
| POST | `/turns/{turn}/complete` | Complete active turn | `message`, `turn` |
| POST | `/turns/{turn}/skip` | Skip active turn | `message`, `turn` |
| POST | `/turns/{turn}/force-end` | Admin force expiration | `message`, `turn` |
| GET | `/groups/{group}/turns/active` | Active turn in group | `active_turn` |
| GET | `/groups/{group}/turns/current` | Current + next + members | `active_turn`, `next_user`, `group_members` |
| GET | `/groups/{group}/turns/history` | Completed/skipped/expired | `data`, `meta` |
| GET | `/turns/user-stats` | Auth user stats | `user_stats` |
| GET | `/groups/{group}/turns/stats` | Group aggregate stats | `group_stats`, `member_stats` |

## 8. Statistics Semantics
User Stats (`user_stats`):
- `total_turns`, `completed_turns`, `skipped_turns`, `active_turns`
- `total_duration_seconds`, `average_duration_seconds`
- Formatted: `total_duration_formatted`, `average_duration_formatted`

Group Stats (`group_stats`):
- Core: `total_turns`, `completed_turns`, `skipped_turns`, `expired_turns`
- Membership: `total_members`, `active_members`
- Time Aggregates: `average_duration`, `total_time` (formatted)
- `member_stats`: per-user: `user_id`, `name`, `total_turns`, `completed_turns`, `total_duration`

## 9. Rotation Logic (next_user)
Algorithm: Ordered active members (by `turn_order`). Determine last finished (completed/skipped/expired) user; choose next sequentially or wrap. If no history, first active member.

## 10. Security & Authorization
- Access limited to group membership or group creator.
- Mutations (complete/skip) limited to owner or creator (admin override).
- Force-end restricted to creator.
- No policy classes yet; candidate for extraction.

## 11. Testing Status
- Feature tests (TurnApiTest): 14/14 passing (69 assertions, 0 failures, ~0.31s run).
- Coverage: Not yet collected in recent cycle (target ≥95%). Requires running PHPUnit with coverage driver (Xdebug or PCOV) and storing report.
- Missing layers (Actions/Services) currently untested because not yet implemented.

## 12. Remaining Gaps / Technical Debt
| Item | Impact | Priority |
|------|--------|----------|
| Lack of domain service extraction | Harder to reuse logic elsewhere | Medium |
| No events / notifications | Limits extensibility & user feedback | Medium |
| Authorization policies absent | Harder to scale permissions | Medium |
| Coverage measurement not integrated | Unverified coverage goal | High |
| Member stats lacks average durations | Limits analytics depth | Low |
| Metadata schema not validated | Potential inconsistent shapes | Low |
| Turn concurrency guard is minimal (no race lock) | Edge race: two simultaneous starts | High (if high concurrency expected) |

## 13. Recommended Next Steps (Actionable)
1. Add PHPUnit coverage run (CI) & enforce threshold (e.g., 95%).
2. Introduce Policies (`TurnPolicy`, `GroupPolicy`) and replace inline checks.
3. Emit domain events: `TurnStarted`, `TurnCompleted`, `TurnSkipped`, `TurnExpired`.
4. Extract rotation logic to `TurnRotationService` for future algorithm variants.
5. Add optimistic locking or DB constraint to prevent duplicate active turns (unique partial index on (group_id) where status='active').
6. Expand stats: percentile durations, longest/shortest turn, active streaks.
7. Implement caching for `current`, `active`, and stats endpoints (short TTL, e.g., 5–15s).
8. Add Form Request classes for validation separation and reusability.
9. Introduce API documentation (OpenAPI spec) auto-generated from routes/resources.
10. Clean up obsolete artifacts (`TurnController.php.backup`, `.broken`) after confirmation.

## 14. Operational Considerations
- Idempotency: Start endpoint not idempotent; may need a guard token in future.
- Time Calculations: Duration computed at transition; no live “elapsed” field—frontend can derive.
- Scaling: Index & history currently unoptimized; add composite indexes `(group_id,status,ended_at)` for large datasets.

## 15. Risks
| Risk | Mitigation |
|------|------------|
| Race condition starting simultaneous turns | DB unique partial index + transaction lock |
| Large history pagination slowness | Add indexes + cursor pagination |
| Overfetching members on every `current` call | Cache active member roster keyed by group version |
| Duration accuracy if server time changes (NTP skew) | Centralize time via `now()` abstraction (Clock service) |

## 16. Glossary
- Active Turn: A turn presently in progress (`status=active`).
- Current Turn Endpoint: Returns active turn + contextual next user + ordered member list.
- Next User: Member scheduled to take a turn based on `turn_order` rotation & last finished turn.
- Duration: Seconds between `started_at` and `ended_at` captured at terminal state (complete/skip/expire).

## 17. Validation Snapshot
- Commit State: All feature assertions pass after key alignment changes (see section 11).
- Manual Review: `TurnController` now internally cohesive; no syntax errors after patch.

## 18. Summary

## 19. Non-Functional Requirements (NFRs)
| Category | Target / Expectation | Current Status | Notes |
|----------|----------------------|----------------|-------|
| Availability | 99.5% (Phase 1) | Not instrumented | Add health endpoint + uptime monitor |
| Latency (P95) | < 250ms API (NA region) | Unknown | Introduce tracing & metrics |
| Throughput | 50 RPS baseline | Untested | Load test with k6/Artillery |
| Security | OWASP Top 10 mitigated | Partial | Add SAST/DAST in CI |
| Observability | Structured logs + request IDs | Minimal | Add middleware & log format |
| Scalability | Horizontally scalable stateless API | Feasible | Externalize session & cache |
| Compliance | GDPR data export/delete (future) | Not started | Tag PII fields |
| Test Coverage | ≥95% overall | Pending measurement | Enable coverage in CI |
| Accessibility (Web) | WCAG 2.1 AA (Phase 2) | Not assessed | Audit Inertia/React UI later |
| Mobile Offline | Core quick session offline | Planned | Flutter offline store present, needs sync bridge |

## 20. Complete Data Model & Migrations Summary
| Table | Purpose | Key Fields | Indexing / Constraints | Notes / Future |
|-------|---------|-----------|------------------------|----------------|
| users | System identities | email, username, status, settings | unique(email), unique(username), idx(status), idx(last_active_at) | Add soft deletes? audit fields |
| groups | Logical turn collections | creator_id, invite_code, status, settings | unique(invite_code), idx(status), idx(last_turn_at) | Potential: algorithm strategy, archived flag already via status |
| group_member | Membership pivot | role, is_active, turn_order | unique(group_id,user_id), idx(group_id,is_active), idx(turn_order) | Add composite idx (group_id, turn_order) for rotation queries |
| turns | Turn lifecycle records | group_id, user_id, status, started/ended_at | idx(group_id,status), idx(user_id), idx(started_at), idx(status) | Add partial unique (group_id where status='active') |
| activity_log | Auditing (Spatie) | subject_type/id, causer_type/id, event | indexes via package | Extend for turn events |
| personal_access_tokens | Sanctum tokens | tokenable_type/id, name | package default | Consider pruning job |
| jobs / failed jobs | Queue infra | payload, queue | default | Horizon integration planned |

Schema Enhancements Proposed:
1. Partial unique index to enforce single active turn per group.
2. Materialized aggregate table (optional later) for daily stats.
3. Soft deletes for groups (migration present adding soft deletes) with cascade handling.

## 21. Error & Response Contract
Standard Success Envelope (collection endpoints):
```
{
	"data": [...],
	"meta": { "current_page":1, "last_page":3, ... }
}
```
Standard Success (entity/action):
```
{ "message": "Turn completed successfully", "turn": { ... } }
```
Error Patterns:
| Type | HTTP | Shape | Example |
|------|------|-------|---------|
| Validation | 422 | { message, errors: {field:[..]} } | Missing group_id |
| Auth | 401 | { message } | Unauthenticated |
| Authorization | 403 | { message } | Not group member |
| Not Found | 404 | { message } | Turn not found |
| Conflict (future) | 409 | { message, code } | Duplicate active turn |
| Rate Limit | 429 | { message, retry_after } | Excess calls |
| Server | 500 | { message, request_id } | Unexpected exception |

Add Request ID middleware for traceability (planned).

## 22. Security & Auth (Expanded)
Current Mechanisms:
* Sanctum personal access tokens.
* Group creator implicit admin.
* Membership pivot with role (admin/member) – not fully enforced yet.

Planned Enhancements:
| Area | Action |
|------|--------|
| Role/Permission Model | Integrate Spatie Permission; define abilities: manage.turns, manage.members, view.stats |
| Policy Layer | `TurnPolicy`, `GroupPolicy` mapping to roles/ownership |
| Token Scopes | Issue scoped tokens for mobile vs automation |
| Rate Limiting | Per-user + per-endpoint buckets (start turn stricter) |
| Input Hardening | Central Form Requests + DTO validation (Spatie Data) |
| Audit Expansion | Emit events -> activity log entries per state change |
| Secrets Handling | Ensure env-only sensitive config (no tokens in metadata) |
| PII Minimization | Avoid exposing emails in group member public responses (maybe hash) |

Threat Considerations:
* Replay of start-turn: mitigate via idempotency key header.
* Enumeration of group IDs: mitigate with invite codes + 403 parity responses.
* Timing attacks minimal – typical CRUD.

## 23. Performance & Scalability
Hot Paths:
| Endpoint | Concerns | Mitigation |
|----------|----------|------------|
| POST /turns | Race for simultaneous creation | Transaction + partial unique index |
| GET /groups/{id}/turns/current | Repeated membership & history queries | Cache active member list + last turn user_id for small window |
| GET /turns (list) | Pagination over large table | Composite index (user scope) or pre-filter via join/subquery |
| /stats endpoints | Recomputes aggregations each call | Cache with short TTL; warm on change events |

Proposed Caching Layers:
* In-memory (Redis) for: active_turn (key: group:{id}:active_turn), user_stats:{user_id}:{hash}.
* Cache bust events: Turn state transitions, membership changes.

Capacity Planning (Initial Assumptions):
* 10K groups, avg 8 members, 50 turns/day active subset.
* Turn writes modest (< 5 RPS) early-phase.

Instrumentation Roadmap:
1. Add request timing middleware.
2. Log query counts + slow query threshold.
3. Add k6 baseline load scripts.

## 24. Testing & Coverage Strategy (Planned Expansion)
Current: Feature test suite for Turn lifecycle (14 passing).
Planned Layers:
| Layer | Tools | Targets |
|-------|-------|---------|
| Unit | Pest | Rotation service, policies, duration formatter |
| Feature | Pest | All API endpoints (auth, groups, turns, stats) |
| Integration | Pest | Event dispatch + activity log writes |
| Browser (Phase 2) | Dusk | Web UI group flow |
| Performance | k6/Artillery | P95 latency under load |
| Security | Dependency scanning | Composer & npm audit |

Coverage Tracking Steps:
1. Enable Xdebug in CI (separate job).
2. `php artisan test --coverage --min=95` gating.
3. Badge generation for README.
4. Failing build on regression.

## 25. Frontend (Flutter) Integration Summary
Flows:
1. Auth: Firebase auth → backend token exchange (future) or direct Sanctum if unified.
2. Group Screen: Fetch groups → join / create → navigate to turn screen.
3. Turn Screen: Poll or WebSocket (future) for current/active; show next_user & rotation order.
4. Stats Screen: Lazy load user/group stats with caching.

State Management: BLoC per feature (GroupsBloc, TurnBloc, StatsBloc). Potential caching layer with hydrated BLoC for offline quick sessions.

Offline Path (Quick Session): Local-only model storing ephemeral participants; conversion path to persistent group by issuing create group API call and uploading local history (future migration logic required).

## 26. Detailed Roadmap (Refined)
| Phase | Focus | Key Deliverables |
|-------|-------|------------------|
| 1A | Stability & Coverage | Add policies, coverage ≥80% interim, unique index, events |
| 1B | Observability | Logging format, request IDs, metrics, basic dashboards |
| 2A | UX & Realtime | WebSocket/Broadcast for turn updates, client subscriptions |
| 2B | Algorithm Expansion | Weighted, cooldown, fairness balancing heuristics |
| 3A | Analytics | Historical aggregates, percentile durations, fairness scoring |
| 3B | Monetization Prep | Feature flags, plan enforcement, usage quotas |
| 4A | Offline & Sync | Conflict resolution rules, merging quick session history |

## 27. Expanded Risk Matrix
| Risk | Likelihood | Impact | Mitigation | Trigger Indicator |
|------|-----------|--------|-----------|-------------------|
| Duplicate active turns | Medium | High | DB partial unique index | Two active found in logs |
| Slow stats under load | Medium | Medium | Cache + precompute | >500ms P95 stats endpoint |
| Unbounded metadata growth | Low | Medium | Validate & cap metadata size | Large row size warnings |
| Turn order inconsistencies | Medium | Medium | Transactional updates on membership reorder | Mismatch between UI + API order |
| Security drift (permissions) | Medium | High | Policies + automated tests | Unauthorized actions succeed |
| Logging gaps hinder debug | High | Medium | Structured logs + IDs | Repeated unknown 500s |
| Tech debt accumulation | High | Medium | Quarterly refactor sprint | PR review backlog growth |

## 28. Domain Events Plan
Events to Implement:
| Event | Payload | Consumers |
|-------|---------|-----------|
| TurnStarted | turn_id, group_id, user_id, started_at | ActivityLog, Notifications |
| TurnCompleted | turn_id, duration_seconds | Stats cache invalidator |
| TurnSkipped | turn_id, reason | ActivityLog, fairness analyzer |
| TurnExpired | turn_id, reason | ActivityLog |
| MembershipChanged | group_id, user_id, role/action | Cache invalidator |

Event Bus: Laravel events → queued listeners (Horizon). Future: outbox pattern for external analytics.

## 29. Extended Glossary
| Term | Definition |
|------|------------|
| Fairness Score | Planned metric measuring distribution evenness across members |
| Rotation Set | Ordered list of currently active members eligible for turns |
| Active Window | Period between turn start and terminal state |
| Terminal State | A non-active status: completed, skipped, expired |
| Algorithm Strategy | Pluggable selection logic (round_robin, weighted, etc.) |

## 30. Open Questions
| Topic | Question | Proposed Resolution |
|-------|----------|--------------------|
| Auth Unification | Combine Firebase & Sanctum user identities? | Introduce identity linking table |
| Realtime | Polling vs WebSockets initial? | Start with short polling; migrate to broadcast |
| Offline Sync | Conflict reconciliation rules? | Last-write-wins with manual conflict log |
| Monetization | Plan enforcement point? | Middleware + feature flag service |

## 31. Pending Cleanup / Housekeeping
| Item | Action |
|------|--------|
| Obsolete controller backups | Delete `.backup` and `.broken` variants |
| Add OpenAPI spec | Generate `openapi.yaml` under `docs/api/` |
| Coverage reports | Add `coverage/` to `.gitignore` |
| CI workflows | Add test + coverage + lint pipelines |

## 32. Final Timestamp
Document last updated: 2025-09-06T00:00:00Z
Foundation for Turn lifecycle is stable and contract-aligned. Remaining work focuses on architectural refinement (policies, events, services), resilience (race prevention), and analytical depth (expanded metrics). Implementing the recommended next steps will close the gap between planned domain-driven design and the current pragmatic controller-centric implementation.

---
Generated: (timestamp at file creation)
