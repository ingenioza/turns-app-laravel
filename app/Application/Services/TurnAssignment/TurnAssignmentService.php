<?php

namespace App\Application\Services\TurnAssignment;

use App\Models\Group;
use App\Models\User;
use InvalidArgumentException;

class TurnAssignmentService
{
    private array $strategies = [];

    private string $defaultStrategy = 'random';

    public function __construct()
    {
        $this->registerStrategy(app(RandomTurnStrategy::class));
        $this->registerStrategy(app(RoundRobinTurnStrategy::class));
        $this->registerStrategy(app(WeightedTurnStrategy::class));
    }

    /**
     * Register a turn assignment strategy
     */
    public function registerStrategy(TurnAssignmentStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    /**
     * Get next user for a group using its preferred strategy
     */
    public function getNextUser(Group $group): ?User
    {
        $strategyName = $group->settings['turn_strategy'] ?? $this->defaultStrategy;

        return $this->getNextUserWithStrategy($group, $strategyName);
    }

    /**
     * Get next user using a specific strategy
     */
    public function getNextUserWithStrategy(Group $group, string $strategyName, array $config = []): ?User
    {
        $strategy = $this->getStrategy($strategyName);

        if (! empty($config)) {
            $strategy->setConfiguration($config);
        }

        try {
            return $strategy->getNextUser($group);
        } catch (\Exception $e) {
            // Fallback to default strategy if preferred strategy fails
            if ($strategyName !== $this->defaultStrategy) {
                return $this->getNextUserWithStrategy($group, $this->defaultStrategy);
            }

            throw $e;
        }
    }

    /**
     * Get available strategies
     */
    public function getAvailableStrategies(): array
    {
        return array_map(function (TurnAssignmentStrategyInterface $strategy) {
            return [
                'name' => $strategy->getName(),
                'description' => $strategy->getDescription(),
                'configuration' => $strategy->getConfiguration(),
            ];
        }, $this->strategies);
    }

    /**
     * Get a specific strategy instance
     */
    public function getStrategy(string $name): TurnAssignmentStrategyInterface
    {
        if (! isset($this->strategies[$name])) {
            throw new InvalidArgumentException("Turn assignment strategy '{$name}' not found");
        }

        return $this->strategies[$name];
    }

    /**
     * Set the default strategy
     */
    public function setDefaultStrategy(string $strategyName): self
    {
        if (! isset($this->strategies[$strategyName])) {
            throw new InvalidArgumentException("Strategy '{$strategyName}' is not registered");
        }

        $this->defaultStrategy = $strategyName;

        return $this;
    }
}
