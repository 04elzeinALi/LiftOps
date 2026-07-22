<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['travel_card_id','collected_by_driver_id','amount','payment_method','payment_status','paid_at'])]
class Payment extends Model
{
    use HasFactory;

    protected $casts = ['paid_at' => 'datetime'];

     public function travelCard()
    {
        return $this->belongsTo(TravelCard::class);
    }

    public function collectedByDriver()
    {
        return $this->belongsTo(Driver::class, 'collected_by_driver_id');
    }
}
