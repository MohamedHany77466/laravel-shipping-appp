<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_travel_request_id',
        'user_id',
        'status',
        'location',
        'note',
        'lat',
        'lng',
    ];

    public function shipmentTravelRequest()
    {
        return $this->belongsTo(ShipmentTravelRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
