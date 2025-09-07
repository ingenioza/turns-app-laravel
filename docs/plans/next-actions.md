# Next Actions (≤150 lines)

## Current Status (September 7, 2025)
- **Phase 1 COMPLETE** ✅ - Firebase auth & user management
- **Phase 2 COMPLETE** ✅ - Groups, authorization system, API endpoints
- **Frontend Choice**: **React** (Inertia v2) — locked decision

## Immediate Refactoring (Required by New Instructions)
1. **UUID/ULID Primary Keys** - Migrate database from auto-increment
2. **FormRequest Validation** - Extract from controllers to FormRequest classes
3. **Events/Listeners/Jobs** - Add for turn assignments and side effects

## Phase 3 — Web Pages (Current Priority)
1. **Setup Inertia v2 with React** - Confirm/upgrade existing scaffold
2. **Groups Management Pages**:
   - `/groups` - List user's groups
   - `/groups/create` - Create new group
   - `/groups/join` - Join group with invite code
   - `/groups/{id}` - Group details and member management
3. **Turn Management UI**:
   - Active turn display
   - Turn history
   - Start/complete/skip actions
4. **Browser Tests** - Add Pest v4 browser testing

## Git Workflow (New Requirement)
- Create feature branches: `feature/phase3-groups-ui`
- Work in small slices (≤200 LOC, ≤10 files)
- Update docs with each commit

Decision Log excerpt: React chosen 2025-09-07 (see COPILOT_INSTRUCTIONS.md)
