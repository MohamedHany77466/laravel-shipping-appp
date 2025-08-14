<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * الحقول اللي مسموح بالملأ الجماعي
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'type',                 // sender / traveler
        'phone_number',         // رقم الهاتف
        'date_of_birth',        // تاريخ الميلاد
        'gender',               // ذكر/أنثى
        'profile_picture_url',  // صورة البروفايل
        'account_status',       // active / suspended / pending_verification
        'email_verified',       // boolean
        'phone_verified',       // boolean
        'identity_verified',    // boolean
    ];

    /**
     * الحقول المخفية في الـ JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * التحويلات التلقائية للأنواع
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'identity_verified' => 'boolean',
            'date_of_birth' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * الحصول على الاسم الكامل
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * التحقق من حالة الحساب
     */
    public function isActive()
    {
        return $this->account_status === 'active';
    }

    /**
     * التحقق من اكتمال التحقق
     */
    public function isFullyVerified()
    {
        return $this->email_verified && $this->phone_verified && $this->identity_verified;
    }

    /**
     * الشحنات الخاصة بالمرسل
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * الرحلات الخاصة بالمسافر
     */
    public function travelRequests()
    {
        return $this->hasMany(TravelRequest::class);
    }

    /**
     * الشحنات اللي تم قبولها للمسافر
     */
    public function acceptedShipments()
    {
        return $this->hasManyThrough(
            \App\Models\Shipment::class,
            \App\Models\ShipmentTravelRequest::class,
            'travel_id',    // المفتاح في ShipmentTravelRequest اللي بيربط بـ Travel
            'id',           // المفتاح في Shipment
            'id',           // مفتاح المستخدم في User
            'shipment_id'   // المفتاح في ShipmentTravelRequest اللي بيربط بـ Shipment
        )->where('status', 'accepted');
    }

    /**
     * كل الطلبات اللي جات على شحنات هذا المرسل
     */
    public function myShipmentRequests()
    {
        return $this->hasManyThrough(
            \App\Models\ShipmentTravelRequest::class,
            \App\Models\Shipment::class,
            'user_id',       // Shipment.user_id => المرسل
            'shipment_id',   // ShipmentTravelRequest.shipment_id => الطلب
            'id',            // User.id
            'id'             // Shipment.id
        );
    }

    /**
     * الطلبات المرتبطة بشحنات المستخدم
     */
    public function shipmentRequests()
    {
        return $this->hasManyThrough(
            \App\Models\ShipmentTravelRequest::class,
            \App\Models\Shipment::class,
            'user_id',    // من User إلى Shipment
            'shipment_id', // من Shipment إلى ShipmentTravelRequest
            'id',
            'id'
        );
    }

    public function trackingEvents()
{
    return $this->hasMany(\App\Models\ShipmentTrackingEvent::class);
}

}
