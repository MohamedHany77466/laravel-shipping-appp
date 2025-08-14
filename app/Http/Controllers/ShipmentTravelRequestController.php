<?php

namespace App\Http\Controllers;

use App\Models\ShipmentTravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShipmentTravelRequestController extends Controller
{
    // إرسال طلب ربط شحنة بسفرة
public function store(Request $request)
{
    $user = Auth::user(); // ✅ يرجع المستخدم الحالي من التوكن

    if (!$user) {
        return response()->json(['message' => 'غير مصرح'], 401);
    }

    //  مسموح للمسافرين فقط
    if ($user->type !== 'traveler') {
        return response()->json(['message' => 'مسموح للمسافرين فقط إرسال طلبات الشحن'], 403);
    }

    //  التحقق من البيانات
    $validated = $request->validate([
        'shipment_id' => 'required|exists:shipments,id',
        'travel_id'   => 'required|exists:travel_requests,id',
        'offered_price' => 'nullable|numeric|min:0', //  السعر اختياري
    ]);

    //  التحقق من ملكية السفرة
    $isOwner = \App\Models\TravelRequest::where('id', $validated['travel_id'])
        ->where('user_id', $user->id)
        ->exists();

    if (!$isOwner) {
        return response()->json(['message' => 'لا يمكنك استخدام سفرة لا تخصك'], 403);
    }

    //  جلب الشحنة للتحقق من حالتها
    $shipment = \App\Models\Shipment::find($validated['shipment_id']);

    //  لو الشحنة اتحجزت خلاص
    if ($shipment->is_booked) {
        return response()->json(['message' => 'تم حجز هذه الشحنة بالفعل ولا تقبل طلبات جديدة'], 400);
    }

    //  منع تكرار الطلبات
     //  السماح بإنشاء طلب جديد لو الطلب القديم مرفوض
    $exists = ShipmentTravelRequest::where('shipment_id', $validated['shipment_id'])
        ->where('travel_id', $validated['travel_id'])
        ->where('status', '!=', 'rejected') // نتجاهل المرفوض
        ->exists();

    if ($exists) {
        return response()->json(['message' => 'هذا الطلب موجود بالفعل أو جاري معالجته'], 409);
    }
    $offeredPrice = $validated['offered_price'] ?? $shipment->offered_price;
    //  إنشاء الطلب
    $requestEntry = ShipmentTravelRequest::create([
        'shipment_id' => $validated['shipment_id'],
        'travel_id'   => $validated['travel_id'],
        'offered_price'=> $offeredPrice,  
        'status'      => 'pending',
    ]);

    return response()->json([
        'message' => 'تم إرسال الطلب بنجاح',
        'data'    => $requestEntry
    ], 201);
}


    // عرض كل الطلبات المرتبطة بسفرة معينة
    public function index($travel_id)
    {
        $requests = ShipmentTravelRequest::with('shipment')
            ->where('travel_id', $travel_id)
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    // تحديث حالة الطلب (يستخدمه المسافر فقط)
    public function updateStatus(Request $request, $id)
    {
        $requestEntry = ShipmentTravelRequest::with('travel')->find($id);

        if (!$requestEntry) {
            return response()->json([
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        // التحقق من أن المستخدم الحالي هو صاحب الرحلة
        if ($requestEntry->travel->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'غير مصرح لك بتحديث هذا الطلب'
            ], 403);
        }

        // التحقق من حالة الطلب المطلوبة
        $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);
        if ($request->status === 'accepted') {
        $requestEntry->shipment->status = 'awaiting_payment';
        $requestEntry->shipment->save();
    }
        // تحديث الحالة
        $requestEntry->status = $request->status;
        $requestEntry->save();

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => $requestEntry
        ]);
    }
}
