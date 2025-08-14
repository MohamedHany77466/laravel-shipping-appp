<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentTravelRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'travel_id',
        'status',
        'offered_price',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
    public function travel()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_id');
    }
    public function trackingEvents()
{
    return $this->hasMany(\App\Models\ShipmentTrackingEvent::class);
}

    
}




