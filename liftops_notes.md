# LiftOps — Project Notes

## Overview

LiftOps is a bus transportation management system built with **Laravel** (backend) and **React** (frontend).

---

## Project Setup

- **Project name:** LiftOps
- **Framework:** Laravel (latest)
- **Auth:** Laravel Sanctum
- **Database:** MySQL via XAMPP
- **DB name:** `liftops`
- **Frontend (next phase):** React

---

## Progress

- [x] Project created
- [x] Database connected
- [x] Sanctum installed
- [x] All migrations written and verified (19 total)
- [x] All models created with relationships (16 total)
- [x] All factories created (16 total)
- [x] All seeders created (15 total) + DatabaseSeeder wired up
- [ ] API Routes + Controllers
- [ ] React frontend

---

## Factories — status

```
✅ UserFactory
✅ DriverFactory
✅ BusFactory
✅ StationFactory
✅ RouteFactory
✅ ScheduleFactory
✅ ScheduleDayFactory
✅ PassengerFactory
✅ TravelCardFactory
✅ TripFactory
✅ ReservationFactory
✅ BoardingFactory
✅ PaymentFactory
✅ MaintenanceFactory
✅ AttendanceFactory
```

## Seeders — status

```
✅ UserSeeder
✅ DriverSeeder
✅ BusSeeder
✅ StationSeeder
✅ RouteSeeder
✅ ScheduleSeeder
✅ ScheduleDaySeeder
✅ PassengerSeeder
✅ TravelCardSeeder
✅ TripSeeder
✅ ReservationSeeder
✅ BoardingSeeder
✅ PaymentSeeder
✅ MaintenanceSeeder
✅ AttendanceSeeder
✅ DatabaseSeeder (calls all of the above, in dependency order)
```

---

## Concepts — Factories vs Seeders

**Factories** define what fake data looks like for a *single* model. A blueprint — "a fake Bus has a random plate number, manufacturer, capacity between 25-50," etc. Calling a factory alone doesn't seed the DB by itself unless you call `->create()`.

**Seeders** actually run factories (or manual inserts) to populate the database. A seeder decides *quantity* and *relationships* — how many records, and how they link to other tables via foreign keys.

```
Factory = the recipe (how to make one fake record)
Seeder  = the chef (how many to make, how they connect)
```

Factories are also reusable directly inside tests (e.g. `Passenger::factory()->create()`), independent of seeders.

---

## Concepts — Seeding Order Matters

Seeders must run in dependency order because child tables need parent IDs to already exist:

```
Users
 → Drivers, Passengers (both need user_id)
Buses, Stations, Routes (no dependencies)
 → Schedules (needs route_id)
   → ScheduleDays (needs schedule_id)
 → TravelCards (needs passenger_id, route_id)
   → Trips (needs schedule_id, bus_id, driver_id)
     → Reservations (needs passenger_id, trip_id, travel_card_id)
       → Boarding (needs trip_id, reservation_id, passenger_id, travel_card_id)
     → Payments (needs travel_card_id)
Maintenance (needs bus_id)
Attendance (needs driver_id, trip_id)
```

---

## Concepts — "Which collection do I loop through?"

When a seeder needs to link several foreign keys together, loop through whichever entity **must have guaranteed representation**, and pick the others via `->random()`.

- **TripSeeder**: loop through `Schedule` (every schedule should produce at least one trip). Buses and drivers are *shared resources* reused across many trips, so those are picked randomly.
- **ReservationSeeder**: loop through `TravelCard`, not `Trip`. A TravelCard already has a fixed `passenger_id` baked in — looping through it and pulling `travelCard->passenger_id` guarantees the reservation's passenger always matches the travel card's real owner. Looping through Trips and randomizing passenger + travel_card independently risks a data mismatch (passenger A reserving with passenger B's card).
- **BoardingSeeder**: loop through `Reservation` — it already ties `passenger_id`, `trip_id`, and `travel_card_id` together correctly, so boarding just copies those straight across.

General rule: find the entity that already "knows" the correct foreign keys for the others, and loop through that one.

---

## Common Faker Methods Used

```php
fake()->word()                     // random word
fake()->sentence()                 // random sentence
fake()->name() / firstName() / lastName()
fake()->phoneNumber()
fake()->date() / dateTime() / time()
fake()->numberBetween(1, 100)      // random integer in range
fake()->randomFloat(2, 50, 500)    // decimal, 2 places, in range
fake()->randomElement([...])       // pick one value from a list
fake()->latitude() / longitude()   // geo decimals
fake()->bothify('??-#######')      // custom pattern (letters/numbers)
```

---

## Common Mistakes Caught This Session

- Wrong/incomplete namespace imports (`use Bus;` instead of `use App\Models\Bus;`)
- Enum values in factory not matching the migration's actual enum values
- Using a collection variable (`$travelCards->id`) instead of a single loop item (`$travelCard->id`)
- Calling `->random()` on an already-single record instead of on the full collection
- Column name typos (`travelCard_id` vs `travel_card_id`)
- Faker methods that don't exist (`fake()->productionYear()`, `fake()->capacity()`) — use generic methods like `numberBetween()` instead

---

## ERD — Final Schema (v2)

*(See original ERD notes for full column-by-column table definitions — USERS, DRIVERS, BUSES, STATIONS, ROUTES, ROUTE_STATIONS, SCHEDULES, SCHEDULE_DAYS, PASSENGERS, TRAVEL_CARDS, TRIPS, RESERVATIONS, BOARDING, PAYMENTS, MAINTENANCE, ATTENDANCE.)*

### Key design decisions
| Issue | Fix |
|---|---|
| Reservations and Boarding were disconnected | Added nullable `reservation_id` FK on BOARDING |
| Boarding required a card but Reservations didn't | Added `travel_card_id` FK to RESERVATIONS |
| `remaining_trips` was a stored field (risky) | Renamed to `total_trips`, now computed dynamically |
| USERS table was fully isolated | Added `user_id` FK to both DRIVERS and PASSENGERS |
| `pickup_location` was fixed on PASSENGERS | Moved to RESERVATIONS (per-trip) |
| `day_of_week` was a single value on SCHEDULES | Extracted to separate SCHEDULE_DAYS table |
| `email` duplicated on PASSENGERS | Removed — already exists on USERS (3NF) |
| No bus maintenance tracking | Added MAINTENANCE table |
| No driver attendance tracking | Added ATTENDANCE table (API-ready for biometric hardware) |

### cascade vs nullOnDelete
| Use | When |
|---|---|
| `cascadeOnDelete()` | Child record is meaningless without parent |
| `nullOnDelete()` | Child record still has value without parent |

---

## Next Up: API Routes + Controllers