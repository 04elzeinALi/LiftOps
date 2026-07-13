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





Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('buses', BusController::class);
Route::apiResource('stations', StationController::class);
Route::apiResource('routes', RouteController::class);
Route::apiResource('passengers', PassengerController::class);
Route::apiResource('drivers', DriverController::class);
Route::apiResource('schedules', ScheduleController::class);
Route::apiResource('schedule-days', ScheduleDayController::class);
Route::apiResource('travel-cards', TravelCardController::class);
Route::apiResource('trips', TripController::class);
Route::apiResource('reservations', ReservationController::class);
Route::apiResource('boardings', BoardingController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('maintenance', MaintenanceController::class);
Route::apiResource('attendance', AttendanceController::class);

// route_stations uses a composite key, so it gets explicit routes
// instead of apiResource (which assumes a single {id}).
Route::get('route-stations', [RouteStationController::class, 'index']);
Route::post('route-stations', [RouteStationController::class, 'store']);
Route::delete('route-stations', [RouteStationController::class, 'destroy']);


