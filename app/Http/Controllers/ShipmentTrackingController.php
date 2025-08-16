<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentTravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShipmentTrackingController extends Controller
{
    /**
     * 1. المرسل يؤكد الدفع
     */
    public function confirmPayment(Request $request, $shipmentTravelRequestId)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'sender') {
            return response()->json(['message' => 'مسموح للمرسلين فقط'], 403);
        }

        $shipmentRequest = ShipmentTravelRequest::with(['shipment', 'travel'])
            ->findOrFail($shipmentTravelRequestId);

        // التحقق من ملكية الشحنة
        if ($shipmentRequest->shipment->user_id !== $user->id) {
            return response()->json(['message' => 'هذه الشحنة لا تخصك'], 403);
        }

        // التحقق من الحالة الحالية
        if ($shipmentRequest->status !== 'accepted') {
            return response()->json(['message' => 'يجب أن يكون الطلب مقبولاً أولاً'], 400);
        }

        // تحديث الحالة إلى "تم الدفع"
        $shipmentRequest->update([
            'status' => 'paid',
            'paid_at' => now()
        ]);

        $shipmentRequest->shipment->update([
            'status' => 'paid'
        ]);

        // إنشاء QR Code للشحنة
        $qrCode = $this->generateQRCode($shipmentRequest);

        return response()->json([
            'message' => 'تم تأكيد الدفع بنجاح',
            'status' => 'paid',
            'qr_code' => $qrCode,
            'next_step' => 'في انتظار استلام المسافر للشحنة'
        ]);
    }

    /**
     * 2. المسافر يؤكد استلام الشحنة
     */
    public function confirmPickup(Request $request, $shipmentTravelRequestId)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'traveler') {
            return response()->json(['message' => 'مسموح للمسافرين فقط'], 403);
        }

        $shipmentRequest = ShipmentTravelRequest::with(['shipment', 'travel'])
            ->findOrFail($shipmentTravelRequestId);

        // التحقق من ملكية الرحلة
        if ($shipmentRequest->travel->user_id !== $user->id) {
            return response()->json(['message' => 'هذه الرحلة لا تخصك'], 403);
        }

        // التحقق من الحالة الحالية
        if ($shipmentRequest->status !== 'paid') {
            return response()->json(['message' => 'يجب تأكيد الدفع أولاً'], 400);
        }

        // تحديث الحالة إلى "تم الاستلام"
        $shipmentRequest->update([
            'status' => 'picked_up',
            'picked_up_at' => now()
        ]);

        $shipmentRequest->shipment->update([
            'status' => 'picked_up'
        ]);

        return response()->json([
            'message' => 'تم تأكيد استلام الشحنة بنجاح',
            'status' => 'picked_up',
            'next_step' => 'ستتحول الشحنة تلقائياً إلى "في الطريق" في تاريخ السفر'
        ]);
    }

    /**
     * 3. تحويل الشحنة تلقائياً إلى "في الطريق" في تاريخ السفر
     * (يتم استدعاؤها عبر Cron Job أو Task Scheduler)
     */
    public function autoTransitShipments()
    {
        $today = now()->toDateString();

        $shipmentsToTransit = ShipmentTravelRequest::with(['shipment', 'travel'])
            ->where('status', 'picked_up')
            ->whereHas('travel', function($query) use ($today) {
                $query->whereDate('travel_date', '<=', $today);
            })
            ->get();

        $updatedCount = 0;

        foreach ($shipmentsToTransit as $shipmentRequest) {
            $shipmentRequest->update([
                'status' => 'in_transit',
                'in_transit_at' => now()
            ]);

            $shipmentRequest->shipment->update([
                'status' => 'in_transit'
            ]);

            $updatedCount++;
        }

        return response()->json([
            'message' => "تم تحديث {$updatedCount} شحنة إلى حالة 'في الطريق'",
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * 4. المسافر يسكن QR Code لتأكيد التسليم
     */
    public function confirmDelivery(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'traveler') {
            return response()->json(['message' => 'مسموح للمسافرين فقط'], 403);
        }

        $request->validate([
            'qr_code' => 'required|string'
        ]);

        // البحث عن الشحنة بـ QR Code
        $shipment = Shipment::where('qr_code', $request->qr_code)->first();

        if (!$shipment) {
            return response()->json(['message' => 'QR Code غير صحيح'], 404);
        }

        $shipmentRequest = ShipmentTravelRequest::with(['travel'])
            ->where('shipment_id', $shipment->id)
            ->where('status', 'in_transit')
            ->first();

        if (!$shipmentRequest) {
            return response()->json(['message' => 'لا يوجد طلب نشط لهذه الشحنة'], 404);
        }

        // التحقق من ملكية الرحلة
        if ($shipmentRequest->travel->user_id !== $user->id) {
            return response()->json(['message' => 'هذه الرحلة لا تخصك'], 403);
        }

        // تحديث الحالة إلى "تم التسليم"
        $shipmentRequest->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        $shipment->update([
            'status' => 'delivered'
        ]);

        return response()->json([
            'message' => 'تم تأكيد تسليم الشحنة بنجاح',
            'status' => 'delivered',
            'next_step' => 'يمكن للمرسل الآن تقييم المسافر'
        ]);
    }

    /**
     * 5. المرسل يؤكد اكتمال العملية (اختياري)
     */
    public function markAsCompleted(Request $request, $shipmentTravelRequestId)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'sender') {
            return response()->json(['message' => 'مسموح للمرسلين فقط'], 403);
        }

        $shipmentRequest = ShipmentTravelRequest::with(['shipment'])
            ->findOrFail($shipmentTravelRequestId);

        // التحقق من ملكية الشحنة
        if ($shipmentRequest->shipment->user_id !== $user->id) {
            return response()->json(['message' => 'هذه الشحنة لا تخصك'], 403);
        }

        // التحقق من الحالة الحالية
        if ($shipmentRequest->status !== 'delivered') {
            return response()->json(['message' => 'يجب أن تكون الشحنة مُسلمة أولاً'], 400);
        }

        // تحديث الحالة إلى "مكتملة"
        $shipmentRequest->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        $shipmentRequest->shipment->update([
            'status' => 'completed'
        ]);

        return response()->json([
            'message' => 'تم تأكيد اكتمال العملية بنجاح',
            'status' => 'completed'
        ]);
    }

    /**
     * عرض حالة الشحنة الحالية
     */
    public function getShipmentStatus($shipmentTravelRequestId)
    {
        $user = Auth::user();
        
        $shipmentRequest = ShipmentTravelRequest::with(['shipment.user', 'travel.user'])
            ->findOrFail($shipmentTravelRequestId);

        // التحقق من الصلاحيات
        $isShipmentOwner = ($shipmentRequest->shipment->user_id === $user->id);
        $isTravelerOwner = ($shipmentRequest->travel->user_id === $user->id);

        if (!$isShipmentOwner && !$isTravelerOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $timeline = $this->buildTimeline($shipmentRequest);

        return response()->json([
            'shipment_info' => [
                'id' => $shipmentRequest->shipment->id,
                'description' => $shipmentRequest->shipment->description,
                'from' => $shipmentRequest->shipment->from_city . ', ' . $shipmentRequest->shipment->from_country,
                'to' => $shipmentRequest->shipment->to_city . ', ' . $shipmentRequest->shipment->to_country,
                'sender' => $shipmentRequest->shipment->user->name,
                'traveler' => $shipmentRequest->travel->user->name,
                'travel_date' => $shipmentRequest->travel->travel_date,
                'agreed_price' => $shipmentRequest->offered_price ?? $shipmentRequest->shipment->offered_price,
            ],
            'current_status' => $shipmentRequest->status,
            'timeline' => $timeline,
            'qr_code' => $shipmentRequest->shipment->qr_code,
            'next_action' => $this->getNextAction($shipmentRequest, $user->id, $isShipmentOwner)
        ]);
    }

    /**
     * إنشاء QR Code للشحنة
     */
    private function generateQRCode($shipmentRequest)
    {
        $qrCode = 'SHIP_' . $shipmentRequest->shipment->id . '_' . Str::random(10);
        
        $shipmentRequest->shipment->update([
            'qr_code' => $qrCode,
            'qr_generated_at' => now()
        ]);

        return $qrCode;
    }

    /**
     * بناء Timeline للشحنة
     */
    private function buildTimeline($shipmentRequest)
    {
        return [
            [
                'step' => 1,
                'title' => 'تم قبول العرض',
                'status' => 'completed',
                'date' => $shipmentRequest->accepted_at,
                'description' => 'تم قبول العرض من الطرفين'
            ],
            [
                'step' => 2,
                'title' => 'تم الدفع',
                'status' => $shipmentRequest->status === 'paid' || $this->isStatusAfter($shipmentRequest->status, 'paid') ? 'completed' : 'pending',
                'date' => $shipmentRequest->paid_at,
                'description' => $shipmentRequest->paid_at ? 'تم تأكيد الدفع' : 'في انتظار تأكيد الدفع'
            ],
            [
                'step' => 3,
                'title' => 'تم استلام الشحنة',
                'status' => $shipmentRequest->status === 'picked_up' || $this->isStatusAfter($shipmentRequest->status, 'picked_up') ? 'completed' : 'pending',
                'date' => $shipmentRequest->picked_up_at,
                'description' => $shipmentRequest->picked_up_at ? 'تم استلام الشحنة من المسافر' : 'في انتظار استلام المسافر'
            ],
            [
                'step' => 4,
                'title' => 'في الطريق',
                'status' => $shipmentRequest->status === 'in_transit' || $this->isStatusAfter($shipmentRequest->status, 'in_transit') ? 'completed' : 'pending',
                'date' => $shipmentRequest->in_transit_at,
                'description' => $shipmentRequest->in_transit_at ? 'الشحنة في الطريق' : 'ستبدأ الرحلة في ' . $shipmentRequest->travel->travel_date
            ],
            [
                'step' => 5,
                'title' => 'تم التسليم',
                'status' => $shipmentRequest->status === 'delivered' || $this->isStatusAfter($shipmentRequest->status, 'delivered') ? 'completed' : 'pending',
                'date' => $shipmentRequest->delivered_at,
                'description' => $shipmentRequest->delivered_at ? 'تم تسليم الشحنة بنجاح' : 'في انتظار التسليم'
            ],
            [
                'step' => 6,
                'title' => 'مكتملة',
                'status' => $shipmentRequest->status === 'completed' ? 'completed' : 'optional',
                'date' => $shipmentRequest->completed_at,
                'description' => $shipmentRequest->completed_at ? 'تم اكتمال العملية' : 'يمكن تقييم المسافر'
            ]
        ];
    }

    /**
     * التحقق من ترتيب الحالات
     */
    private function isStatusAfter($currentStatus, $checkStatus)
    {
        $statusOrder = ['pending', 'accepted', 'paid', 'picked_up', 'in_transit', 'delivered', 'completed'];
        
        $currentIndex = array_search($currentStatus, $statusOrder);
        $checkIndex = array_search($checkStatus, $statusOrder);
        
        return $currentIndex > $checkIndex;
    }

    /**
     * تحديد الإجراء التالي المطلوب
     */
    private function getNextAction($shipmentRequest, $userId, $isShipmentOwner)
    {
        switch ($shipmentRequest->status) {
            case 'accepted':
                return $isShipmentOwner ? [
                    'action' => 'confirm_payment',
                    'title' => 'تأكيد الدفع',
                    'description' => 'يرجى تأكيد دفع المبلغ المتفق عليه'
                ] : [
                    'action' => 'wait',
                    'title' => 'في انتظار المرسل',
                    'description' => 'في انتظار تأكيد الدفع من المرسل'
                ];

            case 'paid':
                return !$isShipmentOwner ? [
                    'action' => 'confirm_pickup',
                    'title' => 'تأكيد الاستلام',
                    'description' => 'يرجى تأكيد استلام الشحنة'
                ] : [
                    'action' => 'wait',
                    'title' => 'في انتظار المسافر',
                    'description' => 'في انتظار استلام المسافر للشحنة'
                ];

            case 'picked_up':
                return [
                    'action' => 'wait',
                    'title' => 'في انتظار تاريخ السفر',
                    'description' => 'ستتحول تلقائياً إلى "في الطريق" في ' . $shipmentRequest->travel->travel_date
                ];

            case 'in_transit':
                return !$isShipmentOwner ? [
                    'action' => 'scan_qr',
                    'title' => 'مسح QR Code',
                    'description' => 'امسح الكود لتأكيد التسليم'
                ] : [
                    'action' => 'wait',
                    'title' => 'في انتظار التسليم',
                    'description' => 'في انتظار تسليم الشحنة من المسافر'
                ];

            case 'delivered':
                return $isShipmentOwner ? [
                    'action' => 'rate_traveler',
                    'title' => 'تقييم المسافر',
                    'description' => 'يمكنك الآن تقييم المسافر (اختياري)'
                ] : [
                    'action' => 'completed',
                    'title' => 'مكتملة',
                    'description' => 'تم تسليم الشحنة بنجاح'
                ];

            default:
                return null;
        }
    }
}