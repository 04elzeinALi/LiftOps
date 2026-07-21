# Admin Remaining Resources (Batch) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Stations, Routes, Schedule Days, Maintenance, Route Stations, and Trips — 6 more admin resources, each a direct application of the Buses (scalar) or Schedules (FK-dropdown) template already proven and reviewed twice.

**Architecture:** Identical file shape to prior sub-projects: `api/<resource>.js` (TanStack Query hooks) + `pages/admin/<resource>/<Resource>Page.jsx` (list/pagination/CRUD) + `pages/admin/<resource>/<Resource>FormDialog.jsx` (create/edit modal). Route Stations is the one variant: no Edit (backend has no update endpoint), composite key instead of a single `id`.

**Tech Stack:** Same as prior sub-projects — no new dependencies.

## Prerequisite (already done, noted for context)

`ScheduleDayController` and `TripController` were widened from `with('schedule')`/`with(['schedule', ...])` to `with('schedule.route')`/`with(['schedule.route', ...])` (commit `8806b69` in the LiftOps backend repo) — additive, verified via curl. Without this, the Schedule Days and Trips tables below would have no route name to display for their Schedule column. `RouteStationController` and `MaintenanceController` were checked too — both already eager-load everything needed (`['route', 'station']` and `'bus'` respectively, both flat/one level, no fix needed there).

## Global Constraints

- Plain JavaScript only. No automated tests. No Playwright/browser-automation tooling.
- All styling via existing design tokens — no new colors.
- Every list/dialog follows the exact Buses/Schedules shape: `useState` per field, inline 422 errors under each field, a `generalError` paragraph for non-422 failures, `AlertDialog` delete confirm with the commented `event.preventDefault()` fix (see Schedules' `SchedulesPage.jsx` for the exact comment text — copy it verbatim, don't paraphrase).
- FK dropdowns are shadcn `Select` (already installed); static/fixed enums (status fields, `day_of_week`) are plain native `<select>` — same distinction established in Schedules.
- Reuse existing read hooks for dropdown data instead of creating duplicates: Schedule dropdown → `useSchedules(1)` (from `@/api/schedules`), Bus dropdown → `useBuses(1)` (from `@/api/buses`), Route dropdown → `useRoutes()` (from `@/api/routes`, dropdown-only, unchanged). Station dropdown reuses this batch's own new `useStations(1)`.
- Every commit happens inside `c:\Users\Admin\liftops-frontend\`.
- Real working admin account for verification: `elzeinali04@gmail.com` / `password`. MySQL/backend/frontend may need starting (`--defaults-file=c:\xampp1\mysql\bin\my.ini` for MySQL if not running).

---

### Task 1: Stations (scalar — Buses template)

**Files:**
- Create: `src/api/stations.js`
- Create: `src/pages/admin/stations/StationFormDialog.jsx`
- Create: `src/pages/admin/stations/StationsPage.jsx`
- Modify: `src/App.jsx` (import `StationsPage`, swap the `stations` route)

**Interfaces:**
- Produces: `useStations(page)`, `useCreateStation()`, `useUpdateStation()`, `useDeleteStation()` — the latter also consumed by Task 5 (Route Stations) for its Station dropdown, reusing `useStations(1)`.

**Fields:** `station_name` (text, required), `latitude` (number, required), `longitude` (number, required). No FK, no enum, no status pill.

**Table columns:** Name, Latitude, Longitude, (actions).

- [ ] Write `api/stations.js` — exact shape of `api/buses.js` (query key `["stations", page]`, mutations invalidate `["stations"]`), swapping `/buses` → `/stations` and the 3 fields above.
- [ ] Write `StationFormDialog.jsx` — exact shape of `BusFormDialog.jsx` minus the status `<select>` (Stations has no enum field), with the 3 fields above, `latitude`/`longitude` sent as `Number(...)`.
- [ ] Write `StationsPage.jsx` — exact shape of `BusesPage.jsx` minus the status column/pill, 3-column table + actions.
- [ ] Wire into `App.jsx`: add `import StationsPage from "@/pages/admin/stations/StationsPage";`, change `<Route path="stations" element={<ComingSoon title="Stations" />} />` to `<Route path="stations" element={<StationsPage />} />`.
- [ ] Commit: `git add -A && git commit -m "Add real Stations page with full CRUD"`

---

### Task 2: Routes (scalar — Buses template, extends existing `routes.js`)

**Files:**
- Modify: `src/api/routes.js` (add to the existing file — do not remove or change the existing `useRoutes()` dropdown hook)
- Create: `src/pages/admin/routes/RouteFormDialog.jsx`
- Create: `src/pages/admin/routes/RoutesPage.jsx`
- Modify: `src/App.jsx`

**Interfaces:**
- Produces (added to `routes.js`): `useRoutesPage(page)` (query key `["routes", page]` — deliberately distinct from the existing `["routes-list"]` dropdown cache), `useCreateRoute()`, `useUpdateRoute()`, `useDeleteRoute()`. **Important:** all three mutations must invalidate BOTH `["routes"]` AND `["routes-list"]` on success, so the Schedules/Trips/Route-Stations dropdowns (which use `useRoutes()`) stay fresh when an admin edits a route here.

**Fields:** `route_name`, `origin`, `destination` (text, required), `distance_km`, `fare` (number, required), `estimated_duration` (text, required, e.g. "1h 30m").

**Table columns:** Name, Origin, Destination, Distance, Duration, Fare, (actions).

- [ ] Add to `api/routes.js`: import `useMutation`, `useQueryClient` alongside the existing `useQuery` import; add `useRoutesPage`, `useCreateRoute`, `useUpdateRoute`, `useDeleteRoute` per the interface above.
- [ ] Write `RouteFormDialog.jsx` — exact shape of `BusFormDialog.jsx`, 6 fields as listed, `distance_km`/`fare` sent as `Number(...)`.
- [ ] Write `RoutesPage.jsx` — exact shape of `BusesPage.jsx`, using `useRoutesPage`/`useDeleteRoute`, 6-column table + actions.
- [ ] Wire into `App.jsx`: import `RoutesPage`, swap the `routes` route.
- [ ] Commit: `git add -A && git commit -m "Add real Routes page with full CRUD"`

---

### Task 3: Schedule Days (FK — Schedules template)

**Files:**
- Create: `src/api/scheduleDays.js`
- Create: `src/pages/admin/schedule-days/ScheduleDayFormDialog.jsx`
- Create: `src/pages/admin/schedule-days/ScheduleDaysPage.jsx`
- Modify: `src/App.jsx`

**Interfaces:**
- Consumes: `useSchedules(1)` from `@/api/schedules` for the Schedule dropdown (read `.data.data`, since it returns the full paginated envelope).
- Produces: `useScheduleDays(page)`, `useCreateScheduleDay()`, `useUpdateScheduleDay()`, `useDeleteScheduleDay()`.

**Fields:** `schedule_id` (shadcn Select, required, options labeled `"{route.route_name} — {departure_time.slice(0,5)}–{arrival_time.slice(0,5)}"`), `day_of_week` (native `<select>`, required, options: monday/tuesday/wednesday/thursday/friday/saturday/sunday, capitalize the label).

**Table columns:** Schedule (same label format as the dropdown), Day (capitalized), (actions).

- [ ] Write `api/scheduleDays.js` — exact shape of `api/schedules.js`, query key `["schedule-days", page]`, endpoint `/schedule-days`.
- [ ] Write `ScheduleDayFormDialog.jsx` — exact shape of `ScheduleFormDialog.jsx`'s Select-usage pattern, but with a Schedule dropdown (not Route) plus a native `<select>` for `day_of_week` (7 fixed options, no API call — same treatment as Bus's `status` field).
- [ ] Write `ScheduleDaysPage.jsx` — exact shape of `SchedulesPage.jsx`, 2-column table (Schedule label, Day) + actions.
- [ ] Wire into `App.jsx`: import `ScheduleDaysPage`, swap the `schedule-days` route.
- [ ] Commit: `git add -A && git commit -m "Add real Schedule Days page with a Schedule dropdown"`

---

### Task 4: Maintenance (FK — Schedules template)

**Files:**
- Create: `src/api/maintenance.js`
- Create: `src/pages/admin/maintenance/MaintenanceFormDialog.jsx`
- Create: `src/pages/admin/maintenance/MaintenancePage.jsx`
- Modify: `src/App.jsx`

**Interfaces:**
- Consumes: `useBuses(1)` from `@/api/buses` for the Bus dropdown.
- Produces: `useMaintenance(page)`, `useCreateMaintenance()`, `useUpdateMaintenance()`, `useDeleteMaintenance()`.

**Fields:** `bus_id` (Select, required, labeled `"{plate_number} — {manufacturer} {model}"`), `maintenance_type` (native select, required, 7 options: oil_change/tire_replacement/brake_inspection/engine_repair/transmission_service/electrical_system_check/suspension_inspection — label each by replacing `_` with space and title-casing), `maintenance_status` (native select, required, 3 options: scheduled/in_progress/completed, same label treatment), `description` (text, nullable — send `null` if empty), `cost` (number, nullable — send `null` if empty, else `Number(...)`), `scheduled_at` (date input, required), `completed_at` (datetime-local input, nullable — send `null` if empty).

**Table columns:** Bus (plate), Type (labeled), Status (pill: `scheduled`→warning, `in_progress`→warning, `completed`→success), Scheduled date, Cost (or "—" if null), (actions).

- [ ] Write `api/maintenance.js` — exact shape of `api/buses.js`, query key `["maintenance", page]`, endpoint `/maintenance`.
- [ ] Write `MaintenanceFormDialog.jsx` — 7 fields as listed above; a small `labelFor(value)` helper (split on `_`, capitalize each word, join with space) used for both enum selects' option labels.
- [ ] Write `MaintenancePage.jsx` — table as listed; reuse (or redefine locally, it's a 3-line function) the same `labelFor`-style helper for the Type column's display text.
- [ ] Wire into `App.jsx`: import `MaintenancePage`, swap the `maintenance` route.
- [ ] Commit: `git add -A && git commit -m "Add real Maintenance page with a Bus dropdown"`

---

### Task 5: Route Stations (special — list + Add + Delete only, no Edit)

**Files:**
- Create: `src/api/routeStations.js`
- Create: `src/pages/admin/route-stations/RouteStationFormDialog.jsx`
- Create: `src/pages/admin/route-stations/RouteStationsPage.jsx`
- Modify: `src/App.jsx`

**Interfaces:**
- Consumes: `useRoutes()` from `@/api/routes` for the Route dropdown; `useStations(1)` from `@/api/stations` (Task 1) for the Station dropdown.
- Produces: `useRouteStations(page)`, `useCreateRouteStation()`, `useDeleteRouteStation({ route_id, station_id })` — **no update hook, matching the backend's lack of a PUT endpoint.**

**Fields (create only, no edit):** `route_id` (Select), `station_id` (Select), `station_order` (number, required).

**Table columns:** Route (`"{origin} → {destination}"`), Station (`station_name`), Order, (Delete only — no Edit button). Row `key` must be `` `${route_id}-${station_id}` `` since there's no single `id`.

**The key backend-shape difference:** `useDeleteRouteStation`'s `mutationFn` takes `{ route_id, station_id }` (not a bare id) and calls `api.delete("/route-stations", { data: { route_id, station_id } })` — axios requires the request body to go in the `data` key of the config object for DELETE requests, not as a second positional argument. `RouteStationFormDialog` has no `record`/edit-mode prop at all (always create-mode) since there's nothing to edit.

- [ ] Write `api/routeStations.js` per the interface above — `useRouteStations(page)` (query key `["route-stations", page]`), `useCreateRouteStation()` (invalidates `["route-stations"]`), `useDeleteRouteStation()` (mutationFn destructures `{ route_id, station_id }`, invalidates `["route-stations"]`).
- [ ] Write `RouteStationFormDialog.jsx` — create-only version of the established dialog pattern (no `isEditing` branch, no pre-fill `useEffect` keyed on a record — just reset to empty form whenever `open` becomes true), 3 fields as listed.
- [ ] Write `RouteStationsPage.jsx` — list + "Add Route Station" button + Delete-only per-row action (no Edit) + the `AlertDialog` delete confirm (same `event.preventDefault()` pattern), calling `deleteRouteStation.mutateAsync({ route_id: deletingRouteStation.route_id, station_id: deletingRouteStation.station_id })`.
- [ ] Wire into `App.jsx`: import `RouteStationsPage`, swap the `route-stations` route.
- [ ] Commit: `git add -A && git commit -m "Add real Route Stations page (list, add, delete — no edit endpoint exists)"`

---

### Task 6: Trips (FK ×3 — Schedules template, new minimal Driver dropdown hook)

**Files:**
- Create: `src/api/drivers.js` (minimal, read-only, mirrors `api/routes.js`'s `useRoutes()` — **not** full Drivers CRUD, which stays deferred)
- Create: `src/api/trips.js`
- Create: `src/pages/admin/trips/TripFormDialog.jsx`
- Create: `src/pages/admin/trips/TripsPage.jsx`
- Modify: `src/App.jsx`

**Interfaces:**
- Consumes: `useSchedules(1)` (Schedule dropdown), `useBuses(1)` (Bus dropdown), and this task's own new `useDriversList()` (Driver dropdown).
- Produces: `useDriversList()` (query key `["drivers-list"]`, `GET /drivers?page=1`, returns `res.data.data` — a flat array, same shape as `useRoutes()`); `useTrips(page)`, `useCreateTrip()`, `useUpdateTrip()`, `useDeleteTrip()`.

**Fields:** `schedule_id` (Select, labeled same as Schedule Days' dropdown), `bus_id` (Select, labeled same as Maintenance's dropdown), `driver_id` (Select, labeled `"{first_name} {last_name}"`), `trip_date` (date, required), `actual_departure` (datetime-local, nullable), `actual_arrival` (datetime-local, nullable), `status` (native select, required, 4 options: scheduled/ongoing/completed/cancelled, capitalize each label).

**Table columns:** Schedule (route+times label), Bus (plate), Driver (name), Trip Date, Status (pill: `scheduled`→warning, `ongoing`→success, `completed`→success, `cancelled`→critical), (actions).

- [ ] Write `api/drivers.js` — exactly mirrors `api/routes.js`'s `useRoutes()`: one `useQuery`, key `["drivers-list"]`, `GET /drivers?page=1`, return `res.data.data`.
- [ ] Write `api/trips.js` — exact shape of `api/schedules.js`, query key `["trips", page]`, endpoint `/trips`.
- [ ] Write `TripFormDialog.jsx` — 3 Selects (Schedule/Bus/Driver) + `trip_date` + 2 nullable datetime-local inputs + a native `<select>` for `status` (4 options). `actual_departure`/`actual_arrival` sent as `form.value || null`.
- [ ] Write `TripsPage.jsx` — 5-column table as listed + actions, using the 4-way status pill mapping above.
- [ ] Wire into `App.jsx`: import `TripsPage`, swap the `trips` route.
- [ ] Commit: `git add -A && git commit -m "Add real Trips page with Schedule/Bus/Driver dropdowns"`

---

## Verification (once, at the end — per the agreed faster execution mode)

Start MySQL/backend/frontend once. Get a fresh admin token. Via curl, for each of the 6 resources: confirm the list endpoint returns real data with the labels the dropdowns/tables expect (route names, plate numbers, driver names all present via eager-loading); create one real record; if the resource supports it, edit it; delete it (for Route Stations, using the `{route_id, station_id}` body shape). Also confirm a 422 case shows the right field-level error shape for at least one FK field (e.g. a nonexistent `bus_id`). Confirm the frontend compiles cleanly (no Vite errors) for all new/changed files. Stop all servers, revoke the token, confirm no orphaned processes.

## Self-Review Notes

- **Spec coverage:** all 6 resources from the design doc are covered; Drivers correctly absent (deferred); Route Stations correctly has no Edit/update hook.
- **Consistency check:** dropdown reuse is consistent (Schedule/Bus/Route dropdowns are reused via existing page-1 hooks everywhere they're needed, never duplicated); the `event.preventDefault()` delete-confirm pattern is specified identically across all 6; the `Route.useRoutes()` vs `useRoutesPage()` naming split is deliberate and consistent (dropdown cache vs. paginated admin view), with cross-invalidation specified so editing a Route doesn't leave stale dropdown data elsewhere.
- **Backend prerequisite fix** (eager-loading `schedule.route`) already applied and verified — confirmed via curl before this plan was finalized, not just assumed.
