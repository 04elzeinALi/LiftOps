<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['driver_id','trip_id','check_in','check_out','status'])]

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

        public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
