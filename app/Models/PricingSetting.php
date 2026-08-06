<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['long_trip_km', 'short_trip_fare', 'long_trip_fare'])]
class PricingSetting extends Model
{
    protected $table = 'pricing_settings';

    /**
     * The one row this table holds. Created with the same defaults the
     * seeding migration inserts if it's ever missing (e.g. a fresh DB that
     * skipped the seed row, or the row was deleted), so callers never have
     * to null-check this.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'long_trip_km' => 40,
            'short_trip_fare' => 2.00,
            'long_trip_fare' => 3.00,
        ]);
    }
}
