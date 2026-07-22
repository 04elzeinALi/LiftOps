# Passenger App (Book a Trip) — Design

## Overview

Build the first real screens of the Passenger app, replacing the placeholder `DashboardHome` at `/passenger`. Scope for this pass is the full booking flow: browse upcoming trips, buy a travel card if the passenger doesn't already have a valid one for that route, book a reservation, and view/cancel existing reservations. Unlike the Driver app (one flow, no nav needed), this has three independent sections, so it needs a small nav in the layout.

This is new, non-templated work. Investigating the booking path (`ReservationController`, `TravelCardController`, `PaymentController`, `Trip::available_seats`) surfaced three real backend gaps that block a trustworthy booking flow — all fixed as part of this pass, not deferred.

## Decisions

| Area | Decision | Why |
|---|---|---|
| First screen scope | Full booking flow: browse → buy card if needed → book → view/cancel reservations | Approved directly — a read-only dashboard alone has nothing meaningful to show a passenger with no cards yet |
| `TravelCard.total_trips`/`expiry_date` | Computed server-side from `card_type`, no longer client-settable | Previously fully client-trusted (a client could send `total_trips: 999999`), unlike `amount` which is already server-computed. Values chosen to match the multiplier basis `TravelCard::calculatePrice()` already uses: `single`→1 trip/1-day validity, `return`→2 trips/3-day, `weekly`→5 trips/7-day, `monthly`→20 trips/30-day |
| Reservation route validation | `ReservationController::store()` now checks `travelCard.route_id === trip.schedule.route_id` | Previously nothing stopped a card bought for one route being used to reserve a trip on a completely different route |
| `Trip::available_seats` | Redefined to count booked **reservations**, not boardings | The accessor's number didn't match what `ReservationController::store()` already enforces (which correctly counts reservations) — a trip could show "30 seats available" while fully reserved. Confirmed nothing in the frontend currently reads this field, so the fix is non-breaking |
| Payment flow | Instant mock-pay: passenger picks a method, frontend calls `POST /travel-cards` then immediately `POST /payments` with `payment_status: "paid"` | No real payment gateway exists anywhere in this project; this is a known, accepted simplification (same tier as the already-documented `/register` role-trust gap) |
| Seat selection | Plain number input, not a seat map | No endpoint exposes which specific seats on a trip are taken to a passenger (their own Reservation index is scoped to their own records only) — conflicts surface as a 422, same pattern as everywhere else in this app |
| Passenger profile | No onboarding/"create profile" step | Confirmed via direct query: all 11 seeded passenger-role users already have a linked `Passenger` record |
| Layout | New `PassengerLayout` with header + a small 3-link nav (Book a Trip / My Travel Cards / My Reservations) | Unlike the Driver app's single flow, this has 3 independent sections a passenger moves between |

## Backend Changes

All three are bug fixes / trust-gap closures, not new features. None change the API's success-path shape for already-correct requests.

### 1. `TravelCardController` — compute `total_trips`/`expiry_date` server-side
```php
private function computeCardTerms(string $cardType): array
{
    return match ($cardType) {
        'single' => ['total_trips' => 1, 'expiry_days' => 1],
        'return' => ['total_trips' => 2, 'expiry_days' => 3],
        'weekly' => ['total_trips' => 5, 'expiry_days' => 7],
        'monthly' => ['total_trips' => 20, 'expiry_days' => 30],
    };
}

public function store(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'passenger_id' => 'required|exists:passengers,id',
        'route_id' => 'required|exists:routes,id',
        'card_type' => 'required|in:single,return,weekly,monthly',
        'status' => 'required|in:active,expired,suspended',
    ]);

    if ($user->role === 'passenger') {
        $ownPassenger = Passenger::where('user_id', $user->id)->firstOrFail();
        $validated['passenger_id'] = $ownPassenger->id;
    }

    $terms = $this->computeCardTerms($validated['card_type']);
    $validated['total_trips'] = $terms['total_trips'];
    $validated['purchase_date'] = now()->toDateString();
    $validated['expiry_date'] = now()->addDays($terms['expiry_days'])->toDateString();

    $travelCard = TravelCard::create($validated);

    return response()->json($travelCard, 201);
}
```
`update()` gets the same treatment when `card_type` is included in the request:
```php
public function update(Request $request, string $id)
{
    $travelCard = TravelCard::findOrFail($id);

    $this->authorizeWrite($request, $travelCard);

    $validated = $request->validate([
        'passenger_id' => 'sometimes|required|exists:passengers,id',
        'route_id' => 'sometimes|required|exists:routes,id',
        'card_type' => 'sometimes|required|in:single,return,weekly,monthly',
        'status' => 'sometimes|required|in:active,expired,suspended',
    ]);

    if (isset($validated['card_type'])) {
        $terms = $this->computeCardTerms($validated['card_type']);
        $validated['total_trips'] = $terms['total_trips'];
        $validated['expiry_date'] = now()->addDays($terms['expiry_days'])->toDateString();
    }

    $travelCard->update($validated);

    return $travelCard;
}
```
`purchase_date`/`expiry_date`/`total_trips` are dropped from the validated/acceptable input entirely — no existing frontend page currently depends on setting them directly (the admin TravelCards page doesn't exist yet).

### 2. `ReservationController::store()` — route-matching check
Widen the trip load to include its route, and add the check right after the existing expiry/status check:
```php
$trip = Trip::with(['bus', 'schedule.route'])->findOrFail($validated['trip_id']);

// ...existing cancelled/completed check...

$travelCard = TravelCard::findOrFail($validated['travel_card_id']);

// Business rule: the travel card must be active and not expired.
if ($travelCard->status !== 'active' || $travelCard->expiry_date < now()->toDateString()) {
    return response()->json([
        'message' => 'This travel card is not active or has expired.',
    ], 422);
}

// Business rule: the travel card must be valid for this trip's route.
if ($travelCard->route_id !== $trip->schedule->route_id) {
    return response()->json([
        'message' => 'This travel card is not valid for this route.',
    ], 422);
}

// ...existing payment/capacity/seat checks unchanged...
```

### 3. `Trip::availableSeats()` — count reservations, not boardings
```php
protected function availableSeats(): Attribute
{
    return Attribute::make(
        get: fn () => max(0, $this->bus->capacity - $this->reservations()->where('status', 'booked')->count()),
    );
}
```

## Frontend Architecture

### File structure
```
src/
├── layouts/
│   └── PassengerLayout.jsx        # NEW — header + 3-link nav + <Outlet/>
├── api/
│   ├── passengerTrips.js          # NEW — useUpcomingTrips
│   ├── passengerCards.js          # NEW — useMyTravelCards, useBuyTravelCard
│   └── passengerReservations.js   # NEW — useMyReservations, useCreateReservation, useCancelReservation
├── pages/passenger/
│   ├── TripsBrowsePage.jsx        # NEW — upcoming trips list
│   ├── BookTripPage.jsx           # NEW — seat + card picker, book a reservation
│   ├── MyTravelCardsPage.jsx      # NEW — own cards list + link to buy
│   ├── BuyTravelCardPage.jsx      # NEW — route + card type → preview → pay
│   └── MyReservationsPage.jsx     # NEW — own reservations + cancel
```
`src/pages/passenger/DashboardHome.jsx` (current placeholder) is removed. `App.jsx`'s `/passenger` route becomes a nested tree under `PassengerLayout`:
```jsx
<Route element={<ProtectedRoute allowedRoles={["passenger"]} />}>
  <Route path="/passenger" element={<PassengerLayout />}>
    <Route index element={<Navigate to="/passenger/trips" replace />} />
    <Route path="trips" element={<TripsBrowsePage />} />
    <Route path="trips/:id/book" element={<BookTripPage />} />
    <Route path="cards" element={<MyTravelCardsPage />} />
    <Route path="cards/buy" element={<BuyTravelCardPage />} />
    <Route path="reservations" element={<MyReservationsPage />} />
  </Route>
</Route>
```

### Data hooks
- `useUpcomingTrips()` (`src/api/passengerTrips.js`) — `GET /trips`, then filters client-side to `status === "scheduled"` (no backend change needed; passengers already see all trips, no role-scoping applies to them).
- `useMyTravelCards()` (`src/api/passengerCards.js`) — `GET /travel-cards` (already passenger-scoped server-side).
- `useBuyTravelCard()` — composite mutation: `POST /travel-cards` with `{route_id, card_type, status: "active"}`, then `POST /payments` with `{travel_card_id: <new card's id>, payment_method, payment_status: "paid"}`. Invalidates `["my-travel-cards"]` on success.
- `useMyReservations()` (`src/api/passengerReservations.js`) — `GET /reservations` (already passenger-scoped).
- `useCreateReservation()` — `POST /reservations` with `{passenger_id, trip_id, travel_card_id, seat_number, pickup_location, reservation_time: now(), status: "booked"}`. `passenger_id` is ignored server-side and forced to the caller's own profile regardless of what's sent, but the validation still requires the field be present — the frontend reads it straight off the selected card's own `passenger_id` (every `TravelCard` already carries one), since a card must be selected to book in the first place.
- `useCancelReservation()` — `PUT /reservations/:id` with `{status: "cancelled"}`.

### `TripsBrowsePage`
Card list (same visual pattern as the Driver app's trips list): route name, times, bus plate, `available_seats` (now reservation-accurate), and a "Book" button linking to `/passenger/trips/:id/book`. Only trips with `status: "scheduled"` are shown.

### `BookTripPage`
- Trip header (route, times, bus, seats available).
- Card picker: filters the passenger's own `useMyTravelCards()` result to `status === "active"`, not expired, `remaining_trips > 0`, and `route_id` matching this trip's route. If the filtered list is empty, shows "You need a travel card for this route" with a link to `/passenger/cards/buy?route_id=<this trip's route id>`.
- Seat number input (plain number field, 1 to bus capacity).
- Submit calls `useCreateReservation()`; 422s (including message-only ones like the new route-mismatch/seat-taken errors) surface via the same `extractErrorMessage` pattern introduced in the Driver app's `TripManifestPage`.

### `MyTravelCardsPage`
List of the passenger's own cards: route, card type, status pill, `remaining_trips`, `expiry_date`. "Buy a Travel Card" button linking to `/passenger/cards/buy`.

### `BuyTravelCardPage`
- Reads an optional `?route_id=` query param (set when arriving from `BookTripPage`'s "you need a card" link) to pre-select the route.
- Route `Select` (reuses the existing `useRoutes()` dropdown hook from `src/api/routes.js`) and a native `card_type` select.
- Live preview line computed **client-side purely for display** (not authoritative — the server computes the real values): shows the same total_trips/expiry-days table as the backend's `computeCardTerms()`, plus the price via the route's `fare` and the same multiplier math as `TravelCard::calculatePrice()` (duplicated client-side for preview only; the actual `amount` charged is still server-computed on the Payment).
- Payment method native select (`cash`/`credit_card`/`bank_transfer`/`wish`).
- Submit calls `useBuyTravelCard()`; on success, navigates to `/passenger/cards`.

### `MyReservationsPage`
Table: route, trip date/time, seat number, status pill. A "Cancel" button on `booked` rows calling `useCancelReservation()`, hidden once `cancelled`/`completed`.

## Out of scope (for this sub-project)

- Passenger profile editing (phone number, etc.)
- Public self-registration (separate deferred item, needs the `/register` role-trust fix first)
- A real seat map / live seat availability display
- Any admin-side Transactions/Payments page (separately deferred, queued for after this)
- Automated tests (consistent with the rest of this project)
