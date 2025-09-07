# Task: Refactoring for New Architecture Requirements

**Date:** September 7, 2025  
**Epic:** Architectural Compliance  
**Branch:** `chore/architecture-refactoring`  

## Scope
Refactor existing codebase to comply with new Copilot instructions before proceeding to Phase 3.

## Acceptance Criteria
- [ ] Migrate primary keys from auto-increment to UUIDs
- [ ] Extract validation from controllers to FormRequest classes
- [ ] Confirm React setup for Inertia v2 frontend
- [ ] Add basic Events/Listeners structure for future notifications
- [ ] All existing tests continue to pass
- [ ] Update any affected documentation

## Touchpoints
- **Models**: User, Group, Turn - add UUID primary keys
- **Migrations**: Create new migrations for UUID conversion
- **Controllers**: GroupController, TurnController, AuthController - extract validation
- **FormRequests**: Create validation classes
- **Events**: Create basic event structure
- **Tests**: Update tests for UUID changes
- **Frontend**: Confirm React/Inertia setup

## Tests Required
- [ ] Unit tests for models with UUIDs
- [ ] Feature tests for API endpoints (should continue working)
- [ ] Validation tests for new FormRequest classes

## Implementation Plan
1. Create UUID migration strategy
2. Create FormRequest classes
3. Update controllers to use FormRequests
4. Create basic Events/Listeners structure
5. Update tests for UUID compatibility
6. Verify React/Inertia setup

## Notes
- This is a foundational refactoring to ensure compliance before Phase 3
- Maintain API compatibility during transition
- Consider backwards compatibility for existing data
