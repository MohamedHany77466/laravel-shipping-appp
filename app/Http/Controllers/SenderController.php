<?php

namespace App\Http\Controllers;

use App\Notifications\ShipmentAccepted;
use App\Notifications\ShipmentRejected;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\ShipmentTravelRequest;
use Illuminate\Support\Facades\Auth;

class SenderController extends Controller
{
    //  عرض كل الشحنات الخاصة بالمرسل
    public function myShipments()
    {
        $shipments = Shipment::where('user_id', Auth::id())->get();

        return response()->json([
            'message' => 'شحناتك',
            'data' => $shipments
        ]);
    }

    //  عرض الطلبات المرتبطة بشحنة محددة (مع التحقق من ملكيتها)
    public function shipmentRequests($shipmentId)
{
    // تحقق أن الشحنة تخص المرسل الحالي
    $shipment = Shipment::where('id', $shipmentId)
        ->where('user_id', Auth::id())
        ->first();

    if (!$shipment) {
        return response()->json(['message' => 'هذه الشحنة لا تخصك'], 403);
    }

   

    $requests = ShipmentTravelRequest::with(['travel.user'])
        ->where('shipment_id', $shipmentId)
        ->get(['id','shipment_id','travel_id','status','offered_price','created_at']); //  السعر موجود

    return response()->json([
        'message' => 'كل الطلبات المقدمة على شحنتك',
        'shipment' => $shipment, //  نعرض الشحنة نفسها
        'requests' => $requests  //  كل الطلبات مع السعر
    ]);
}


    //  قبول أو رفض طلب (مع التحقق من ملكية الشحنة)
   
public function updateRequestStatus($id, Request $request)
{
    $request->validate([
        'status' => 'required|in:accepted,rejected'
    ]);

    // نجيب الطلب مع الشحنة والسفرة
    $shipmentRequest = ShipmentTravelRequest::with(['shipment', 'travel.user'])->findOrFail($id);

    // تحقق من ملكية الشحنة
    if ($shipmentRequest->shipment->user_id !== Auth::id()) {
        return response()->json(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء'], 403);
    }

    $shipment = $shipmentRequest->shipment;
    $oldStatus = $shipmentRequest->status;

    // تحديث حالة الطلب
    $shipmentRequest->status = $request->status;
    $shipmentRequest->save();

    // لو قبل الطلب
    if ($request->status === 'accepted') {
        // رفض باقي الطلبات على نفس الشحنة
        ShipmentTravelRequest::where('shipment_id', $shipment->id)
            ->where('id', '!=', $shipmentRequest->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        // تحديد إن الشحنة محجوزة
        $shipment->is_booked = true;
         $shipment->status = 'awaiting_payment';
        $shipment->save();

        // إشعار المسافر
        $shipmentRequest->travel->user->notify(new ShipmentAccepted(
             $shipmentRequest->offered_price ?? $shipment->offered_price
        ));
    }
    if ($shipment->is_booked && $oldStatus !== 'accepted') {
    return response()->json(['message' => 'هذه الشحنة محجوزة بالفعل'], 400);
    }
    // لو الطلب اترفض
    if ($request->status === 'rejected') {
        // إشعار المسافر
        $shipmentRequest->travel->user->notify(new ShipmentRejected(
             $shipmentRequest->offered_price ?? $shipment->offered_price
        ));

        // فحص: هل باقي الطلبات كلها Rejected؟
        $allRejected = !ShipmentTravelRequest::where('shipment_id', $shipment->id)
            ->where('status', 'accepted')
            ->exists();

        // لو كله مرفوض → رجع الشحنة متاحة
        if ($allRejected) {
            $shipment->is_booked = false;
            $shipment->status = 'open'; 
            $shipment->save();
        }
    }

    return response()->json([
        'message' => 'تم تحديث حالة الطلب بنجاح',
        'data' => $shipmentRequest
    ]);
}




}


