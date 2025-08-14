<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TravelerController extends Controller
{
    // عرض الشحنات المقبولة للمسافر
    public function acceptedShipments()
{
    $userId = Auth::id();

    $requests = \App\Models\ShipmentTravelRequest::whereHas('travel', function ($q) use ($userId) {
        $q->where('user_id', $userId);
    })->where('status', 'accepted')->with('shipment')->get();

    // رجّع فقط الشحنات
    $shipments = $requests->pluck('shipment');

    return response()->json([
        'message' => 'الشحنات التي تم قبولها',
        'data' => $shipments
    ]);
}
public function myNotifications()
{
    return response()->json([
        'notifications' => Auth::user()->notifications,
    ]);
}

}
