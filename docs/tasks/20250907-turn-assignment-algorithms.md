# Turn Assignment Algorithms Implementation

**Date:** September 7, 2025  
**Epic:** Phase 3 - Algorithms & Turns  
**Task:** Services: Random, RoundRobin, Weighted  
**Branch:** `feature/algorithms-turn-services`  

## 📋 Scope

Implement turn assignment algorithms as service classes to determine which user should take the next turn in a group. This will replace the current basic turn logic with configurable, fair algorithms.

### Deliverables
1. `TurnAssignmentStrategyInterface` contract
2. `RandomTurnStrategy` service  
3. `RoundRobinTurnStrategy` service
4. `WeightedTurnStrategy` service  
5. `TurnAssignmentService` orchestrator
6. Unit tests for all strategies (≥95% coverage)
7. Integration tests for turn assignment flow

## ✅ Acceptance Criteria

### AC1: Strategy Interface
- [ ] Define contract with `getNextUser(Group $group): User` method
- [ ] Include metadata about strategy name and description
- [ ] Support for strategy configuration parameters

### AC2: Random Strategy  
- [ ] Randomly select from active group members
- [ ] Exclude currently active turn user (if any)
- [ ] Support seed for testing reproducibility

### AC3: Round Robin Strategy
- [ ] Cycle through members in `turn_order` sequence
- [ ] Handle edge cases (skipped users, removed members)
- [ ] Reset cycle after all members have had turns

### AC4: Weighted Strategy
- [ ] Assign turns based on member weights/activity
- [ ] Consider factors: last turn time, completion rate, skip frequency
- [ ] Configurable weight parameters

### AC5: Service Integration
- [ ] Update `TurnService` to use strategies
- [ ] Add group setting for preferred strategy
- [ ] Maintain backward compatibility with existing turn logic

## 🎯 Technical Touchpoints

### New Files (≤10 files target)
```
app/Application/Services/TurnAssignment/
├── TurnAssignmentStrategyInterface.php
├── RandomTurnStrategy.php  
├── RoundRobinTurnStrategy.php
├── WeightedTurnStrategy.php
└── TurnAssignmentService.php

tests/Unit/TurnAssignment/
├── RandomTurnStrategyTest.php
├── RoundRobinTurnStrategyTest.php  
├── WeightedTurnStrategyTest.php
└── TurnAssignmentServiceTest.php
```

### Modified Files
```
app/Application/Services/TurnService.php      # Integration
app/Models/Group.php                          # Strategy setting
database/migrations/xxx_add_strategy_to_groups.php
```

### Code Volume Estimate: ~150 LOC
- Interface: 20 LOC
- 3 Strategy classes: 30 LOC each = 90 LOC  
- Service orchestrator: 40 LOC
- Tests: ~200 LOC (separate from main count)

## 🧪 Testing Strategy

### Unit Tests
- **RandomTurnStrategy**: Seed-based reproducibility, exclusion logic
- **RoundRobinTurnStrategy**: Order cycling, edge case handling  
- **WeightedTurnStrategy**: Weight calculation, fairness verification
- **TurnAssignmentService**: Strategy selection and delegation

### Integration Tests  
- End-to-end turn assignment through TurnService
- Group setting configuration and strategy switching
- Performance testing with large groups (100+ members)

### Test Coverage Target: ≥95%

## 🔗 Dependencies
- Existing `TurnService` class
- `Group` and `User` models with relationships
- Group membership data in `group_user` pivot table

## 🚧 Implementation Notes

1. **Start small**: Implement Random strategy first for immediate value
2. **Configurability**: Use group settings table for strategy preferences  
3. **Fallback strategy**: Default to Random if preferred strategy fails
4. **Performance**: Consider caching for large groups in Weighted strategy
5. **Extensibility**: Interface design should support future strategies

## ✅ Definition of Done
- [ ] All acceptance criteria met
- [ ] Unit tests passing with ≥95% coverage  
- [ ] Integration tests demonstrate end-to-end functionality
- [ ] PHPStan level max passes
- [ ] Pint formatting applied
- [ ] Documentation updated in relevant service classes
- [ ] No breaking changes to existing API endpoints
