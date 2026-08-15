<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\PassengerController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleDayController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TravelCardController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\BoardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\RouteStationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PricingSettingController;
use App\Http\Controllers\CashBoardingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;





Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Rate-limited so these public endpoints can't be brute-forced (login) or
// spammed to mass-create accounts (register): max 6 attempts per minute per IP.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Stations and Routes: any authenticated user can browse them (drivers
    // and passengers need this to populate pickers — travel card routes,
    // pickup-location stations); only admins can create/edit/delete.
    Route::apiResource('stations', StationController::class)->only(['index', 'show']);
    Route::apiResource('routes', RouteController::class)->only(['index', 'show']);

    // Read-only for any authenticated user: drivers and passengers need the
    // current distance/fare bands to preview a price before it's charged
    // (see Route::fareForKm()). Only admins may change them, below.
    Route::get('pricing-settings', [PricingSettingController::class, 'show']);

    // admin-only management resources
    Route::middleware('role:admin')->group(function () {
        Route::put('pricing-settings', [PricingSettingController::class, 'update']);
        Route::apiResource('buses', BusController::class);
        Route::apiResource('stations', StationController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('routes', RouteController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('drivers', DriverController::class);
        Route::get('users', [UserController::class, 'index']);
        Route::apiResource('schedules', ScheduleController::class);
        Route::apiResource('schedule-days', ScheduleDayController::class);
        Route::apiResource('maintenance', MaintenanceController::class);
        Route::get('route-stations', [RouteStationController::class, 'index']);
        Route::post('route-stations', [RouteStationController::class, 'store']);
        Route::put('route-stations/reorder', [RouteStationController::class, 'reorder']);
        Route::delete('route-stations', [RouteStationController::class, 'destroy']);
        // Must be registered before the payments apiResource below, or
        // "summary" gets swallowed by the payments/{payment} show route.
        Route::get('payments/summary', [PaymentController::class, 'summary']);

    // Operational reports. Admin-only: these aggregate across every
    // passenger, driver and payment in the system.
    Route::middleware('role:admin')->prefix('reports')->group(function () {
        Route::get('revenue', [ReportController::class, 'revenue']);
        Route::get('revenue/detail', [ReportController::class, 'revenueDetail']);
        Route::get('driver-cash', [ReportController::class, 'driverCash']);
        Route::get('fleet', [ReportController::class, 'fleet']);
    });

        // Admin-only per-user activity for one local day. Passengers'
        // apiResource lives in the ownership-gated group below, so these
        // admin views are declared explicitly here.
        Route::get('drivers/{driver}/activity', [DriverController::class, 'activity']);
        Route::get('passengers/{passenger}/activity', [PassengerController::class, 'activity']);
    });

    // Shifts: a driver's working day on a route, which generates the trips
    // (legs) it runs. Drivers read their own and may move only the status;
    // everything else is admin-only (see ShiftController).
    Route::apiResource('shifts', ShiftController::class)->only(['index', 'show', 'update']);
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('shifts', ShiftController::class)->only(['store', 'destroy']);
    });

    // Trips: any authenticated user can view; drivers may update only their
    // own trip's status (see TripController::update); only admins can
    // create/delete or edit other fields.
    Route::apiResource('trips', TripController::class)->only(['index', 'show', 'update']);
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('trips', TripController::class)->only(['store', 'destroy']);
    });

    // Ownership-gated resources — the controllers themselves check that a
    // passenger/driver only sees or touches their own records; admins see all.
    Route::apiResource('passengers', PassengerController::class);
    Route::apiResource('travel-cards', TravelCardController::class);
    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('boardings', BoardingController::class);
    Route::apiResource('cash-boardings', CashBoardingController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('attendance', AttendanceController::class);

    // A person's own in-app messages (see NotificationController — every
    // action is scoped to the signed-in user, whatever their role).
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
});



