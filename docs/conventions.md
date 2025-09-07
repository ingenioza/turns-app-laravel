# Conventions — Laravel + Inertia

- Controllers thin; business logic in Services
- FormRequest validation only
- Policies on all domain resources; deny by default
- Events + Jobs for side‑effects
- Inertia pages/components use a single chosen stack (React OR Vue); keep types consistent
