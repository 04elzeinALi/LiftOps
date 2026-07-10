<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['travel_card_id','amount','payment_method','payment_status','paid_at'])]
class Payment extends Model
{
    use HasFactory;

     public function travelCard()
    {
        return $this->belongsTo(TravelCard::class);
    }
}
