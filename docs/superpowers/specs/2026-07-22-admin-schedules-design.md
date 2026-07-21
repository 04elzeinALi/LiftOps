# Admin Schedules (FK Dropdown Pattern) — Design

## Overview

Build the real Schedules admin page — the first resource in this project with a foreign key (`route_id`) — to establish the reusable "FK dropdown populated from another resource" pattern before it gets copied to the remaining FK-heavy resources (Trips, Maintenance, Route Stations, Schedule Days). This follows the exact same list/create/edit/delete shape as the Buses template from the Admin Panel Foundation, with one new piece: a `route_id` field rendered as a shadcn `Select` populated from a live `/api/routes` list, instead of a plain text/enum field.

## Decisions

| Area | Decision | Why |
|---|---|---|
| Route dropdown component | shadcn `Select` (new CLI addition) | Matches the polish of the rest of the form; the more realistic pattern for the remaining FK-heavy resources to copy, vs. a plain native `<select>` |
| Routes data source | A minimal `useRoutes()` read-only hook in a new `src/api/routes.js` | Only needed to populate the dropdown — a full Routes CRUD admin page is a separate, later resource |
| Route display in the Schedules table | Read directly from the already-eager-loaded `schedule.route` object | `ScheduleController::index()`/`show()` already do `Schedule::with('route')->paginate(15)` server-side — no extra client fetch needed for display |
| Time fields | `<input type="time">` for `departure_time`/`arrival_time` | Native time picker, matches the `time` DB column type |
| Everything else (list, pagination, create/edit dialog, delete confirm, error handling) | Identical pattern to the Buses template, including the `event.preventDefault()` fix (now commented) in the delete confirm handler | Proven, reviewed pattern — no need to redesign what already works |

## Architecture

### File structure
```
src/
├── api/
│   ├── routes.js               # NEW — useRoutes() only (no create/update/delete yet)
│   └── schedules.js            # useSchedules(), useCreateSchedule(), useUpdateSchedule(), useDeleteSchedule()
├── pages/admin/schedules/
│   ├── SchedulesPage.jsx       # list + pagination + Add/Edit/Delete, same shape as BusesPage
│   └── ScheduleFormDialog.jsx  # create/edit dialog — route_id as a Select, departure/arrival as time inputs
```

### The FK dropdown flow
1. `npx shadcn@latest add select` adds the styled Select component.
2. `ScheduleFormDialog` calls `useRoutes()` to get the full route list, and renders each as a `SelectItem` labeled `"{origin} → {destination}"` (readable, not a raw ID).
3. Radix's `Select` uses `onValueChange(value)` (a string) rather than a native `onChange` event — the handler converts it to a number before storing: `handleChange("route_id", Number(value))`.
4. On submit, `route_id` is sent as a number, validated server-side via `exists:routes,id` (already enforced by the existing `ScheduleController`).

### List display
`SchedulesPage`'s table has a **Route** column reading `schedule.route.route_name` directly from the paginated response (already nested, no extra request), alongside **Departure**, **Arrival**, and the same Edit/Delete actions as Buses.

### Everything else
Identical to the Buses template: `useSchedules(page)` mirrors `useBuses(page)`'s query-key/invalidation shape; `ScheduleFormDialog` mirrors `BusFormDialog`'s state/validation/422-handling shape; delete confirm mirrors `BusesPage`'s `AlertDialog` + the `event.preventDefault()` fix (carried forward with its explanatory comment intact, not re-introducing the bug it fixed).

## Out of scope (for this sub-project)

- A full Routes CRUD admin page (still `ComingSoon` — only a minimal read hook exists for the dropdown)
- Any other resource besides Schedules (Trips, Maintenance, Route Stations, Schedule Days, Stations, Drivers all stay `ComingSoon`)
- Automated tests (consistent with the rest of this plan)
