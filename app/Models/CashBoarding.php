<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// A one-off cash rider a driver boards without an account or travel card —
// see the migration's docblock for why this is a separate table from
// Boarding rather than a nullable-everything version of it.
#[Fillable(['trip_id', 'driver_id', 'customer_name', 'from_station_id', 'to_station_id', 'amount', 'boarded_at'])]
class CashBoarding extends Model
{
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function fromStation()
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    public function toStation()
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }
}
