<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_country',
        'to_country',
        'travel_date',
        'max_weight',
    ];

public function user()
{
    return $this->belongsTo(User::class);
}

public function shipmentRequests()
{
    return $this->hasMany(ShipmentTravelRequest::class, 'travel_id');
}

}
