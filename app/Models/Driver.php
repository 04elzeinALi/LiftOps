<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;



#[Fillable(['user_id', 'first_name', 'last_name','phone_number','license_number','address','hire_date','status'])]


class Driver extends Model
{
    use HasFactory;
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
