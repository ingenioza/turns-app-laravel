# Laravel Architecture

## Backend
- Laravel 12, PHP 8.3+
- MySQL, Redis (queues)
- Spatie/laravel-permission for roles/permissions
- Firebase token verification (kreait/firebase-tokens or JWT verify)
- Events/Listeners/Jobs for side‑effects

## Front‑end (Inertia v2)
- Tailwind CSS
- Pick one: latest React OR latest Vue
- Component directory per route with clear props types (TS for React, or script setup for Vue 3)
- Form handling via Inertia helpers; validation errors surfaced from FormRequests

## Folders (suggested)
- app/Domain/{Groups,Participants,Turns}/
- app/Http/Controllers/Api/ and app/Http/Controllers/Web/
- app/Http/Requests/
- app/Policies/
- app/Services/
- app/Events/, app/Listeners/, app/Jobs/
- resources/js/ (React or Vue app with Inertia v2)
- resources/views/app.blade.php (Inertia root)
- tests/{Feature,Unit}
