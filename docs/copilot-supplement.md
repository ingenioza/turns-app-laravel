# Copilot Supplement — Laravel (Detailed)

This document holds the detailed guidance, patterns, and examples that were trimmed from `.github/copilot-instructions.md` to keep the assistant’s core context lightweight.

Use this as the canonical reference for in-depth architecture, patterns, examples, and checklists. Keep the main instructions short and link here for details.

## Contents
- Architecture and domain patterns (DDD, Actions, Repositories, Services)
- API design patterns, resources, and requests
- Turn algorithm services and examples
- Authentication/authorization patterns, policies, and Spatie integrations
- Inertia.js + React + TypeScript frontend guidelines
- Database design, migrations, and indexing strategies
- Testing with Pest: feature, unit, and seeding strategies
- Performance, caching, and queue usage
- Security, validation, and rate limiting
- Code generation rules and extended quality checklist
- Constraints and operational concerns

---

## Architecture Patterns

- Laravel application layering: Actions, Events, Listeners, Http (Controllers, Requests, Resources), Models, Policies, Repositories, Services, Jobs.
- Domain groupings for Group Management, Turn System, and User Management.

## API and Validation

- RESTful controllers with Resources, strict validation via Form Requests, consistent error envelopes.

## Algorithms

- Strategy-based algorithms with history awareness: Random, Round-robin, Weighted, Custom hooks.

## AuthN/Z and Spatie

- Sanctum for API, Breeze for web; spatie/laravel-permission and spatie/laravel-activitylog patterns and examples.

## Frontend (Inertia.js)

- TypeScript-first interfaces, Pages, Components, Hooks, and forms via Inertia helpers.

## Database & Migrations

- Schema examples, FK constraints, timestamps, soft deletes, and indexes guidance.

## Testing with Pest

- Coverage goals, example tests for authorization and algorithms, factories, and seeders.

## Performance & Caching

- Eager loading, indexes, caching patterns, and Horizon for queue monitoring.

## Security & Validation

- OWASP-aligned validation, middleware for access control, rate limiting, and logging.

## Code Generation Guidelines

- Imports/namespaces, PHPDoc, error handling, validation, tests, authorization, and activity logs.

## Extended Quality Checklist

- PSR-12, strict types, validation, authorization, tests, Spatie usage, security, performance, docs.

## Constraints & Ops

- PHP/Laravel versions, DB choices, Node/Build, testing budgets, API latency targets, accessibility.

> For the full historic content that informed this supplement, see previous versions in git history. New detailed guidance should be added here first and only summarized in `.github/copilot-instructions.md`.
