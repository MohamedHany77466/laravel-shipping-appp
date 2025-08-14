<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_country',
        'from_city',
        'to_country',
        'to_city',
        'weight',
        'category',
        'description',
        'delivery_from_date',
        'delivery_to_date',
        'offered_price',
        'special_instructions',
        'is_booked', 
    ];

    // علاقة مع المرسل
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // الطلبات المرتبطة بالشحنة
    public function requests()
    {
        return $this->hasMany(ShipmentTravelRequest::class);
    }

    public function travelRequests()
    {
        return $this->hasMany(ShipmentTravelRequest::class);
    }

    public function shipmentTravelRequests()
    {
        return $this->hasMany(\App\Models\ShipmentTravelRequest::class);
    }
}
