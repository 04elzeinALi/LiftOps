# Admin Remaining Resources (Batch) — Design

## Overview

Build 6 more admin resources by directly applying the two patterns already proven and reviewed (Buses = scalar CRUD, Schedules = FK-dropdown CRUD), plus one small new shape for Route Stations' composite-key/no-edit backend. This clears the admin panel down to just Drivers remaining (deferred — no Users admin page exists yet to populate its dropdown).

## Decisions

| Resource | Pattern | FK dropdown(s) | Notes |
|---|---|---|---|
| Stations | Buses (scalar) | none | `station_name`, `latitude`, `longitude` |
| Routes | Buses (scalar) | none | `route_name`, `origin`, `destination`, `distance_km`, `estimated_duration`, `fare` |
| Schedule Days | Schedules (FK) | Schedule (reuses `useSchedules(1)`) | `day_of_week` is a native `<select>` (fixed enum, not API-backed) |
| Maintenance | Schedules (FK) | Bus (reuses `useBuses(1)`) | `maintenance_type`/`maintenance_status` are native `<select>`s |
| Route Stations | New: list + Add + Delete, **no Edit** | Route (reuses `useRoutes()`), Station (reuses this batch's new `useStations(1)`) | Backend has no update endpoint (composite key `route_id`+`station_id`, no single `id`); delete sends `{route_id, station_id}` as the request body via axios's `delete(url, { data: {...} })` |
| Trips | Schedules (FK ×3) | Schedule (reused), Bus (reused), Driver (**new** minimal `useDriversList()`, mirrors `useRoutes()` — read-only, page 1, dropdown-only; does not mean building full Drivers CRUD) | `status` is a native `<select>` |

**Deferred:** Drivers (full CRUD) — still `ComingSoon`, blocked on a future Users admin page/picker.

**Unchanged from prior sub-projects:** list/pagination shape, dialog-based create/edit, inline per-field 422 errors, the delete-confirm `AlertDialog` with its commented `event.preventDefault()` fix, design tokens, no automated tests.

## Dropdown label conventions (for readability, not raw IDs)

- Route: `"{origin} → {destination}"` (already established in Schedules)
- Schedule: `"{route.route_name} — {departure_time}–{arrival_time}"`
- Bus: `"{plate_number} — {manufacturer} {model}"`
- Station: `"{station_name}"`
- Driver: `"{first_name} {last_name}"`

## Out of scope

- Drivers (deferred per above)
- Any backend changes (Route Stations' lack of an edit endpoint is accepted as-is, not something this sub-project adds)
- Automated tests
