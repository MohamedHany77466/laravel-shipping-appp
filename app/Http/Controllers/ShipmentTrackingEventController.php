<?php

namespace App\Http\Controllers;

use App\Models\ShipmentTrackingEvent;
use App\Models\ShipmentTravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ShipmentTrackingEventController extends Controller
{
    // جلب كل الأحداث لطلب شحن معين
    public function index($shipmentTravelRequestId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $req = ShipmentTravelRequest::with(['shipment', 'travel'])->findOrFail($shipmentTravelRequestId);

        $isShipmentOwner = ($req->shipment->user_id === $user->id);
        $isTravelerOwner = ($req->travel->user_id === $user->id);

        if (!$isShipmentOwner && !$isTravelerOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $events = ShipmentTrackingEvent::where('shipment_travel_request_id', $shipmentTravelRequestId)
            ->with('user:id,name,type')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $events], 200);
    }

    // إضافة حدث تتبع جديد
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $data = $request->validate([
            'shipment_travel_request_id' => 'required|exists:shipment_travel_requests,id',
            'status' => ['required', Rule::in([
                'payment_received',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'problem'
            ])],
            'location' => 'nullable|string',
            'note' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $req = ShipmentTravelRequest::with(['shipment', 'travel.user'])->findOrFail($data['shipment_travel_request_id']);

        $isShipmentOwner = ($req->shipment->user_id === $user->id);
        $isTravelerOwner = ($req->travel->user_id === $user->id);

        if (!$isShipmentOwner && !$isTravelerOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($req->status !== 'accepted') {
            return response()->json(['message' => 'لا يمكنك إضافة أحداث قبل قبول الطلب (accepted)'], 400);
        }

        // 🛑 منع تكرار نفس الحدث
        $existsSameEvent = ShipmentTrackingEvent::where('shipment_travel_request_id', $req->id)
            ->where('status', $data['status'])
            ->exists();
        if ($existsSameEvent) {
            return response()->json(['message' => 'هذا الحدث مسجل بالفعل'], 400);
        }

        // 🟢 ترتيب الأحداث المطلوب
        $eventOrder = [
            'payment_received' => 1,
            'picked_up' => 2,
            'in_transit' => 3,
            'out_for_delivery' => 4,
            'delivered' => 5
        ];

        $lastEvent = ShipmentTrackingEvent::where('shipment_travel_request_id', $req->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastEvent) {
            $lastStep = $eventOrder[$lastEvent->status] ?? 0;
            $newStep = $eventOrder[$data['status']] ?? 0;

            if ($newStep < $lastStep) {
                return response()->json(['message' => 'لا يمكن الرجوع لخطوة سابقة'], 400);
            }

            if ($newStep > $lastStep + 1 && !in_array($data['status'], ['in_transit', 'out_for_delivery'])) {
                return response()->json(['message' => 'يجب اتباع تسلسل الخطوات'], 400);
            }
        } else {
            if ($data['status'] !== 'payment_received') {
                return response()->json(['message' => 'أول خطوة يجب أن تكون تأكيد الدفع'], 400);
            }
        }

        // 🔒 صلاحيات كل حدث
        if ($data['status'] === 'payment_received' && !$isShipmentOwner) {
            return response()->json(['message' => 'فقط المرسل يمكنه تأكيد الدفع'], 403);
        }
        if ($data['status'] !== 'payment_received' && !$isTravelerOwner) {
            return response()->json(['message' => 'فقط المسافر يمكنه تحديث حالة الشحنة'], 403);
        }

        // 🛠 إنشاء الحدث
        $event = ShipmentTrackingEvent::create(array_merge($data, ['user_id' => $user->id]));

        // ⚙️ تحديث حالات الشحنة والطلب
        if ($event->status === 'payment_received') {
            $req->shipment->status = 'in_progress';
            $req->shipment->save();
        }
        if ($event->status === 'delivered') {
            $req->status = 'delivered';
            $req->save();
            $req->shipment->status = 'delivered';
            $req->shipment->save();
        }

        return response()->json(['message' => 'تم إضافة الحدث بنجاح', 'data' => $event], 201);
    }
}
