# LiftOps Frontend Foundation — Design

## Overview

Build the foundation of the LiftOps React frontend: a separate standalone app (not inside the Laravel project) that authenticates against the existing Sanctum API and shows a different dashboard shell depending on the logged-in user's role (admin / driver / passenger). This is sub-project 1 of 4 (Foundation → Admin panel → Driver app → Passenger app); only placeholder/mockup-matching shell screens are built here — real per-resource CRUD screens come in the Admin panel sub-project next.

## Decisions

| Area | Decision | Why |
|---|---|---|
| Language | Plain JavaScript | Keep the learning curve on React itself, not React+TS at once |
| Project location | Separate standalone app, sibling folder `c:\Users\Admin\liftops-frontend\` | Realistic split; requires CORS config on the Laravel side |
| App shape | One React app, role-based routing | Shared login/API client/components; `user.role` drives which screens render |
| Data fetching | Axios + TanStack Query | Handles loading/error/caching/refetch across 16 resources without hand-rolled boilerplate |
| UI components | shadcn/ui + Tailwind | Accessible, editable (copied in, not a black box), fast for CRUD-heavy screens |
| Auth token storage | localStorage | Standard for token-based (non-cookie) APIs; accepted XSS tradeoff for this project's scope |
| Registration scope | Login only for now | No public self-registration yet — accounts created via the Admin panel (next sub-project). Also: the existing `/register` endpoint currently trusts a client-sent `role` field (security gap) — must be fixed to force `role: passenger` before any public registration form ships |

## Visual Design System (approved via mockup)

Full interactive mockup: 4 screens (Login, Admin Dashboard, Driver App, Passenger App), both light/dark themes — approved by user before implementation began.

**Identity:** transit operations console — a "dispatch/wayfinding" aesthetic (not generic SaaS).

**Color tokens** (dark shown; light is the same structure, see mockup CSS for exact light values):
```
--bg: #0F1B24        --surface: #16252F      --surface-2: #1D2E3A
--border: #28404D    --text: #EAF0F2         --text-muted: #86A0AA
--accent: #4FC7BF     (teal, "route-line" brand color)
--success: #4FCB8C   --warning: #E7B75C      --critical: #EC7C6B
```
Status pills (in_service/active/paid = success; maintenance/pending = warning; out_of_service/suspended/cancelled = critical) always pair color with a text label — never color alone.

**Typography:**
- Display/headings: **Barlow Condensed** (600/800) — evokes highway/transit signage
- Body/UI: **Barlow** (400/500/600)
- Data/tabular (plate numbers, times, capacities, money): **IBM Plex Mono** (400/500), `font-variant-numeric: tabular-nums`

**Layout patterns:**
- Admin (desktop): sidebar nav grouped by domain (Fleet, Network, Schedule, People, Commerce) + topbar + stat-tile row + data tables with status pills
- Driver/Passenger (mobile-first): card-based single-column screens, bottom tab nav
- Both themes fully designed via CSS custom properties, overridable by `data-theme` attribute (matches Artifact theme-toggle contract, and will map to a real light/dark preference toggle in the built app)

Reference the published mockup artifact for exact component styling (buttons, cards, pills, tables, travel-card visual, trip card) — the real components should match it closely.

## Architecture

### Project structure
```
liftops-frontend/
├── src/
│   ├── api/
│   │   └── client.js          # Axios instance, auto-attaches Bearer token, 401 → logout
│   ├── auth/
│   │   ├── AuthContext.jsx    # { user, token, login, logout }
│   │   └── ProtectedRoute.jsx # redirect if unauthenticated / wrong role
│   ├── pages/
│   │   ├── Login.jsx
│   │   ├── admin/DashboardHome.jsx
│   │   ├── driver/DashboardHome.jsx
│   │   └── passenger/DashboardHome.jsx
│   ├── components/            # shared UI (nav, layout shell, shadcn components)
│   ├── styles/                # design tokens (CSS variables from the mockup)
│   ├── App.jsx                # route definitions
│   └── main.jsx
├── .env                        # VITE_API_URL=http://127.0.0.1:8000/api
```

### Route map
```
/login                     public
/                          protected — redirects to /admin, /driver, or /passenger by role
/admin/*                  protected, admin-only   (placeholder dashboard for now)
/driver/*                 protected, driver-only  (placeholder dashboard for now)
/passenger/*               protected, passenger-only (placeholder dashboard for now)
```

### Auth flow
1. On load, `AuthContext` checks localStorage for a token; if present, calls `GET /api/user` to validate + populate the current user.
2. Login page posts `{ email, password }` to `POST /api/login`; on success stores the token, sets the user in context, redirects by role.
3. `ProtectedRoute` blocks unauthenticated access (→ `/login`) and wrong-role access (→ the user's own role home).
4. Logout calls `POST /api/logout`, clears localStorage + context, redirects to `/login`.
5. Axios response interceptor catches any `401` globally → clears auth state → redirects to `/login` (handles expired/revoked tokens everywhere, not per-screen).

### API client & CORS
- Single Axios instance (`src/api/client.js`) with a request interceptor attaching `Authorization: Bearer <token>` from localStorage, and a response interceptor handling global `401`.
- TanStack Query wraps the app in `QueryClientProvider`; each resource gets small hooks (e.g. `useBuses()`, `useCreateBus()`) built on this Axios instance.
- Backend touch point: `config/cors.php` needs `paths` including `api/*` and `allowed_origins` including the frontend dev URL (`http://localhost:5173`). No `supports_credentials`/Sanctum stateful-domain config needed since this is Bearer-token auth, not cookie-based.

## Out of scope (for this sub-project)

- Real per-resource CRUD screens (Admin panel — next sub-project)
- Driver/Passenger real functionality beyond the shell (their own sub-projects after Admin)
- Public self-registration page
- Fixing the `/register` role-trust bug (flagged, deferred until registration ships)
- Automated frontend tests
