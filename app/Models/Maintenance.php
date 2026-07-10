<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[Fillable(['bus_id','maintenance_type','maintenance_status','description','cost','scheduled_at','completed_at'])]
class Maintenance extends Model
{
    use HasFactory;

    protected $table = 'maintenance';

        public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
    
}
