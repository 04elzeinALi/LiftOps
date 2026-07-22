# Driver App (Today's Trips + Manifest) — Design

## Overview

Build the first real screens of the Driver app, replacing the placeholder `DashboardHome` at `/driver`. Scope for this pass: a driver logs in, sees the trips assigned to them for today, opens one to see its passenger manifest, marks passengers as boarded, and starts/ends the trip. Walk-up/cash-rider boarding (no existing reservation) is explicitly out of scope for this pass — it needs its own travel-card + payment mini-flow and is deferred to a later sub-project.

This is new, non-templated work — unlike the admin resources (which all copied a proven CRUD shape), the driver app has its own data-scoping rules, its own layout, and requires backend permission changes that don't exist yet.

## Decisions

| Area | Decision | Why |
|---|---|---|
| First screen scope | Today's trips + manifest (not just a bare trip list, not attendance-first) | Directly serves the driver's actual daily job: know your trips, know who's supposed to be on the bus |
| Walk-up/cash riders | Deferred, not built in this pass | Needs its own travel-card+payment creation flow; keeps this pass focused on the reservation-backed happy path |
| Trip status control | Driver can transition their own trip scheduled→ongoing→completed | Natural "start/end trip" action; requires loosening `TripController::update` (see Backend Changes) |
| `TripController::index()` scoping | Currently returns **all** trips to **any** authenticated user, including drivers — a real gap. Add driver-role auto-scoping (mirrors the existing pattern in Boardings/Reservations/Attendance) | Drivers should only ever see trips they're assigned to; this was simply never wired up when Trips was built |
| "Today's trips" filter | New optional `?trip_date=` query param on `TripController::index()` | Avoids relying on pagination ordering/client-side filtering to find "today" among a driver's full trip history |
| Manifest data source | `GET /api/reservations?trip_id=` + `GET /api/boardings?trip_id=` (both need a new optional `trip_id` filter) | Neither endpoint currently supports filtering to one trip — only role-based scoping exists today |
| Marking a passenger boarded | `POST /api/boardings` with `{trip_id, reservation_id, passenger_id: reservation.passenger_id, travel_card_id: reservation.travel_card_id, boarded_at: now}` | Reuses the existing, already-authorized Boarding creation endpoint — no new backend endpoint needed |
| Layout | New minimal `DriverLayout` (header + `Outlet`, no sidebar) | This app has one real section right now; a full sidebar shell (like `AdminLayout`) would be over-built for a single list+detail flow |
| Trips list UI | Card list, not a table | Admin pages use dense tables because they manage many resources with many columns; a driver's daily trip list is short and glanceable — cards fit better and set a better pattern for a possibly-mobile-leaning app |
| Manifest pagination | Fetch page 1, check `last_page`, fetch remaining pages in parallel and concatenate if `last_page > 1` | `paginate(15)` is a fixed backend-wide convention; a full bus (capacity can exceed 15) must not silently truncate the manifest |

## Backend Changes

All four are additive/backward-compatible — existing admin/passenger behavior is unchanged when the new query params are omitted, and the admin write path is untouched.

### 1. `TripController::index()` — driver scoping + date filter
```php
public function index(Request $request)
{
    $query = Trip::with(['schedule.route', 'bus', 'driver']);

    if ($request->user()->role === 'driver') {
        $query->whereHas('driver', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    if ($request->filled('trip_date')) {
        $query->whereDate('trip_date', $request->query('trip_date'));
    }

    return $query->paginate(15);
}
```

### 2. `TripController::update()` — allow driver status-only updates
Route change in `routes/api.php`: move `update` out of the `role:admin` group into the general `auth:sanctum` group.
```php
// before
Route::apiResource('trips', TripController::class)->only(['index', 'show']);
Route::middleware('role:admin')->group(function () {
    Route::apiResource('trips', TripController::class)->only(['store', 'update', 'destroy']);
});

// after
Route::apiResource('trips', TripController::class)->only(['index', 'show', 'update']);
Route::middleware('role:admin')->group(function () {
    Route::apiResource('trips', TripController::class)->only(['store', 'destroy']);
});
```
Controller logic branches on role:
```php
public function update(Request $request, string $id)
{
    $user = $request->user();
    $trip = Trip::findOrFail($id);

    if ($user->role === 'driver') {
        $ownDriver = Driver::where('user_id', $user->id)->first();

        if (! $ownDriver || $trip->driver_id !== $ownDriver->id) {
            return response()->json(['message' => 'Forbidden: you are not the driver for this trip.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:scheduled,ongoing,completed,cancelled',
        ]);

        $trip->update($validated);

        return $trip;
    }

    // existing admin path — unchanged fields/validation
    $validated = $request->validate([
        'schedule_id' => 'sometimes|required|exists:schedules,id',
        'bus_id' => 'sometimes|required|exists:buses,id',
        'driver_id' => 'sometimes|required|exists:drivers,id',
        'trip_date' => 'sometimes|required|date',
        'actual_departure' => 'nullable|date',
        'actual_arrival' => 'nullable|date',
        'status' => 'sometimes|required|in:scheduled,ongoing,completed,cancelled',
    ]);

    if (isset($validated['bus_id'])) {
        $bus = Bus::findOrFail($validated['bus_id']);
        if ($bus->status !== 'in_service') {
            return response()->json(['message' => 'This bus is not in service.'], 422);
        }
    }

    if (isset($validated['driver_id'])) {
        $driver = Driver::findOrFail($validated['driver_id']);
        if ($driver->status !== 'active') {
            return response()->json(['message' => 'This driver is not active.'], 422);
        }
    }

    $trip->update($validated);

    return $trip;
}
```
Non-admin, non-driver roles (passenger) fall through to `abort(403)` implicitly — the `role === 'driver'` branch returns early, and the admin path is now reachable by any authenticated user per the route change, so an explicit passenger block must be added at the top:
```php
if ($user->role === 'passenger') {
    return response()->json(['message' => 'Forbidden'], 403);
}
```

### 3. `ReservationController::index()` — trip_id filter
```php
public function index(Request $request)
{
    $user = $request->user();

    $query = Reservation::with(['passenger', 'trip', 'travelCard']);

    if ($user->role === 'passenger') {
        $query->whereHas('passenger', fn ($q) => $q->where('user_id', $user->id));
    }

    if ($user->role === 'driver') {
        $query->whereHas('trip', function ($q) use ($user) {
            $q->whereHas('driver', fn ($dq) => $dq->where('user_id', $user->id));
        });
    }

    if ($request->filled('trip_id')) {
        $query->where('trip_id', $request->query('trip_id'));
    }

    return $query->paginate(15);
}
```

### 4. `BoardingController::index()` — trip_id filter
Same pattern as #3, added to the existing `Boarding::with([...])` query after its existing role-scoping.

## Frontend Architecture

### File structure
```
src/
├── layouts/
│   └── DriverLayout.jsx           # NEW — header (driver name + logout) + <Outlet/>, no sidebar
├── api/
│   └── driverTrips.js             # NEW — useTodaysTrips, useTripDetail, useUpdateTripStatus,
│                                   #        useTripManifest, useMarkBoarded
├── pages/driver/
│   ├── DriverTripsPage.jsx        # NEW — today's assigned trips, card list
│   └── TripManifestPage.jsx       # NEW — trip header + status button + manifest table
```
`src/pages/driver/DashboardHome.jsx` (the current placeholder) is removed; `App.jsx`'s `/driver` route becomes a nested route tree under `DriverLayout`, matching the `/admin` structure:
```jsx
<Route element={<ProtectedRoute allowedRoles={["driver"]} />}>
  <Route path="/driver" element={<DriverLayout />}>
    <Route index element={<Navigate to="/driver/trips" replace />} />
    <Route path="trips" element={<DriverTripsPage />} />
    <Route path="trips/:id" element={<TripManifestPage />} />
  </Route>
</Route>
```

### Data hooks (`src/api/driverTrips.js`)
- `useTodaysTrips()` — `useQuery`, `GET /api/trips?trip_date=<today's YYYY-MM-DD>`. Backend auto-scopes to the driver, so the response is already just their trips.
- `useTripDetail(id)` — `useQuery`, `GET /api/trips/:id`.
- `useUpdateTripStatus()` — `useMutation`, `PUT /api/trips/:id` with `{status}`. Invalidates `useTripDetail`'s and `useTodaysTrips`' query keys on success.
- `useTripManifest(tripId)` — `useQuery` returning `{reservations, boardings}`. Fetches page 1 of both `GET /api/reservations?trip_id=` and `GET /api/boardings?trip_id=` in parallel; if either response's `last_page > 1`, fetches the remaining pages in parallel and concatenates `data` arrays before returning.
- `useMarkBoarded()` — `useMutation`, `POST /api/boardings` with the payload shown in Decisions above. Invalidates the `useTripManifest` query key for that trip on success.

### `DriverTripsPage`
One card per trip: route name (`trip.schedule.route.route_name`), departure/arrival time, bus plate, a status pill (same color convention as the admin Trips page: scheduled=amber, ongoing/completed=green, cancelled=red). Tapping a card navigates to `/driver/trips/:id`. Empty state: "No trips scheduled for today."

### `TripManifestPage`
- Header: route, times, bus plate/capacity, status pill.
- Action button, contextual on `trip.status`:
  - `scheduled` → "Start Trip" button → `useUpdateTripStatus()` with `{status: "ongoing"}`
  - `ongoing` → "End Trip" button → `useUpdateTripStatus()` with `{status: "completed"}`
  - `completed`/`cancelled` → no button
- "X / capacity boarded" counter: `boardings.length` vs `trip.bus.capacity`.
- Manifest table: one row per reservation with `status === "booked"` — passenger name (`reservation.passenger.first_name` + `last_name`, following the same field-naming convention as Drivers), seat number, pickup location. Action column: if a `boarding` exists with `boarding.reservation_id === reservation.id`, show a "Boarded" badge (disabled); otherwise show a "Mark Boarded" button calling `useMarkBoarded()`.
- Errors from `useMarkBoarded()` / `useUpdateTripStatus()` surface the same way as the admin dialogs' `generalError` pattern (message-only 422s included — reusing the fix just applied there): a dismissable red text line, not a silent failure.

## Out of scope (for this sub-project)

- Walk-up/cash-rider boarding (creating a Boarding + TravelCard + Payment with no existing reservation)
- Attendance (clock-in/out) screens
- Any Passenger-app work
- Automated tests (consistent with the rest of this project)
