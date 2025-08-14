<?php

namespace App\Http\Controllers;

use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TravelRequestController extends Controller
{
    // المسافر يضيف سفرة
    public function store(Request $request)
    {
        // تحقق من صحة البيانات
        $request->validate([
            'from_country' => 'required|string',
            'to_country' => 'required|string',
            'travel_date' => 'required|date',
            'max_weight' => 'required|numeric|min:1',
        ]);

        // إنشاء السفر
        $travel = TravelRequest::create([
            'user_id'      => Auth::id(),
            'from_country' => $request->from_country,
            'to_country'   => $request->to_country,
            'travel_date'  => $request->travel_date,
            'max_weight'   => $request->max_weight,
        ]);

        return response()->json([
            'message' => 'تم إضافة السفرة بنجاح',
            'travel' => $travel,
        ]);
    }

    public function acceptedShipments($travel_id)
{
    $travel = TravelRequest::with(['shipmentRequests' => function ($query) {
        $query->where('status', 'accepted')->with('shipment');
    }])->findOrFail($travel_id);

    return response()->json([
        'message' => 'الشحنات المقبولة',
        'data' => $travel->shipmentRequests,
    ]);
}


}
