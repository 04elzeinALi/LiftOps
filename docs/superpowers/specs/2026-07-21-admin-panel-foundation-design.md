# Admin Panel Foundation — Design

## Overview

Build the real admin dashboard shell on top of the frontend Foundation (already complete): the sidebar/topbar layout matching the approved mockup, and one resource — Buses — built fully end-to-end (list, create, edit, delete) to establish the reusable pattern every other admin resource will copy. This is sub-project 2 of the larger frontend plan (Foundation → **Admin Panel Foundation** → remaining resources → Driver app → Passenger app).

## Decisions

| Area | Decision | Why |
|---|---|---|
| Create/Edit UX | Modal dialog (shadcn `Dialog`), not a separate route | No extra routing per resource; fast to replicate across the remaining 15 resources |
| Sidebar nav | Full nav shown from day 1, matching the mockup's grouped sections (Overview, Fleet, Network, Schedule, People, Commerce) | Feels complete immediately; sidebar file doesn't need editing every time a new resource ships |
| Unbuilt resources | Route to a shared `ComingSoon` placeholder component | Keeps every nav link real/clickable without building 15 pages up front |
| Data table | Plain HTML table styled per the mockup, reading Laravel's built-in pagination response directly (`current_page`/`last_page`/`data`) | No new dependency; the API already returns everything needed |
| Form handling | Plain `useState` per field, matching the existing `Login.jsx` pattern | Consistency with the Foundation's established convention; no form library needed for a 6-field form |
| Validation errors | Inline, per-field, driven directly by the backend's `422` response shape (`errors: { field: ["message"] }`) | Matches what the API already returns — no transformation needed |
| Data fetching | TanStack Query hooks (`useBuses`, `useCreateBus`, `useUpdateBus`, `useDeleteBus`) wrapping the existing Axios client | This is exactly what Task 9 of the Foundation set up `QueryClientProvider` for |

## Architecture

### File structure
```
src/
├── layouts/
│   └── AdminLayout.jsx         # sidebar + topbar shell; wraps all /admin/* pages via <Outlet/>
├── components/
│   └── admin/
│       └── ComingSoon.jsx      # reusable placeholder, takes a `title` prop
├── api/
│   └── buses.js                 # useBuses(), useCreateBus(), useUpdateBus(), useDeleteBus()
├── pages/admin/
│   ├── buses/
│   │   ├── BusesPage.jsx       # list + "Add Bus" button + edit/delete row actions
│   │   └── BusFormDialog.jsx   # shared create/edit modal
│   ├── stations/ComingSoonPage.jsx
│   ├── routes/ComingSoonPage.jsx
│   ├── drivers/ComingSoonPage.jsx
│   ├── schedules/ComingSoonPage.jsx
│   ├── schedule-days/ComingSoonPage.jsx
│   ├── maintenance/ComingSoonPage.jsx
│   ├── route-stations/ComingSoonPage.jsx
│   └── trips/ComingSoonPage.jsx
```

`pages/admin/DashboardHome.jsx` (the Foundation's flat "Welcome, {name}" placeholder) is removed — superseded by `AdminLayout` + the nested route tree below.

### Routing
```
/admin              → redirect to /admin/buses
/admin/buses        → BusesPage (real CRUD)
/admin/stations      → ComingSoon
/admin/routes        → ComingSoon
/admin/drivers       → ComingSoon
/admin/schedules     → ComingSoon
/admin/schedule-days → ComingSoon
/admin/maintenance   → ComingSoon
/admin/route-stations → ComingSoon
/admin/trips         → ComingSoon
```
All nested under the existing `<ProtectedRoute allowedRoles={["admin"]}>` wrapper from the Foundation — no change to auth/role-gating logic, just what renders inside it.

### Buses CRUD flow
1. **List** — `useBuses()` calls `GET /api/buses`, renders rows in a styled table (plate, manufacturer, model, year, capacity, status pill), Prev/Next driven by the pagination meta.
2. **Create/Edit** — `BusFormDialog` is one shared component for both; pre-filled when editing. On submit, calls the matching mutation hook. A `201`/`200` closes the dialog and calls `queryClient.invalidateQueries(['buses'])` so the list refreshes automatically. A `422` maps `error.response.data.errors` directly onto per-field error text under each input.
3. **Delete** — a confirm step (shadcn `AlertDialog`, "Delete this bus?") before calling `useDeleteBus()`, then the same auto-refetch.

## Out of scope (for this sub-project)

- Real functionality for any resource besides Buses (the other 8 admin sections stay `ComingSoon` placeholders)
- Trips' split view (shared read / admin-only write) — deferred to when Trips itself gets built
- Driver/Passenger real functionality (their own future sub-projects)
- Automated tests (consistent with the rest of this plan)
