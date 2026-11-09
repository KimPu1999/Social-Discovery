# Social Discovery

Phase 1 foundation for an interest-based social discovery platform.

## Requirements

- PHP 8.3+, Composer, PostgreSQL 15+
- Node.js 20+, npm

## Setup

Create a PostgreSQL database named `social_discovery`, then configure `backend/.env` from `backend/.env.example` with the local database credentials. Install dependencies and migrate:

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

The API is available at `http://localhost:8000/api/v1`, with `GET /health` as the public health check. The frontend uses `VITE_API_BASE_URL=http://localhost:8000/api/v1` and credentialed Sanctum SPA cookies; authentication tokens are not stored in localStorage.

After configuring a PostgreSQL database, create the public storage link and seed configurable interests:

```bash
php artisan storage:link
php artisan migrate
php artisan db:seed
```

Provision the first administrator interactively. The password is never echoed and no administrator credentials are seeded:

```bash
php artisan social:create-super-admin
```

Phase 2 authentication endpoints include registration/login/logout, email verification, password recovery, profile and media management, interests, settings/privacy, account lifecycle, and Super Admin user status/role management. Moderator routes are restricted to the `moderator` and `super_admin` roles; Super Admin routes require `super_admin` server-side authorization.

Phase 3 adds the professional UI system: semantic CSS-variable design tokens with light/dark/system themes, a localized (EN/MM) component library, responsive member/admin/public/auth layouts with role-aware navigation, an authentic Super Admin user-management table over the paginated admin API, and URL-synchronized search/filter/pagination state. Set `VITE_SOCIAL_LINKS='[{"label":"…","url":"https://…"}]'` to expose optional social links in the footer; none are invented by default.

## Commands

```bash
cd backend && php artisan test
cd frontend && npm run build
cd frontend && npm run lint
cd frontend && npm run test
```

## Phase 1 scope

Implemented: Laravel API versioning, health and session-auth foundations, secure validation and rate limiting, PostgreSQL migrations, responsive public/auth/app/admin layouts, theme-ready Zustand state, typed API client, language-ready copy module, and route placeholders for the requested product surfaces.

Deferred to Phase 2: discovery ranking, production feed, communities and event data, real-time messaging/Reverb, notifications, file storage, and admin workflows backed by live records.
