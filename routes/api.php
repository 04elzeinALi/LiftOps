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





Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Rate-limited so these public endpoints can't be brute-forced (login) or
// spammed to mass-create accounts (register): max 6 attempts per minute per IP.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // admin-only management resources
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('buses', BusController::class);
        Route::apiResource('stations', StationController::class);
        Route::apiResource('routes', RouteController::class);
        Route::apiResource('drivers', DriverController::class);
        Route::get('users', [UserController::class, 'index']);
        Route::apiResource('schedules', ScheduleController::class);
        Route::apiResource('schedule-days', ScheduleDayController::class);
        Route::apiResource('maintenance', MaintenanceController::class);
        Route::get('route-stations', [RouteStationController::class, 'index']);
        Route::post('route-stations', [RouteStationController::class, 'store']);
        Route::delete('route-stations', [RouteStationController::class, 'destroy']);
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
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('attendance', AttendanceController::class);
});



