<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentTravelRequest;
use App\Models\ShipmentTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShipmentDetailsController extends Controller
{
    // 🟢 Endpoint: تفاصيل شحنة + العرض المقبول + بيانات المسافر + Timeline
    public function show($shipmentId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 🟢 جلب الشحنة
        $shipment = Shipment::with('user')->find($shipmentId);
        if (!$shipment) {
            return response()->json(['message' => 'الشحنة غير موجودة'], 404);
        }

        // 🟢 جلب الطلب المقبول/المسلّم على الشحنة
        $req = ShipmentTravelRequest::with(['shipment.user', 'travel.user'])
            ->where('shipment_id', $shipment->id)
            ->whereIn('status', ['accepted', 'delivered'])
            ->orderByDesc('updated_at')
            ->first();

        if (!$req) {
            // لا يوجد عرض مقبول (لسه في مرحلة عروض فقط)
            return response()->json([
                'message' => 'لا يوجد عرض مقبول لهذه الشحنة حتى الآن',
                'shipment' => $shipment
            ], 200);
        }

        // 🛡️ صلاحيات العرض: صاحب الشحنة أو صاحب السفرة فقط
        $isShipmentOwner = ($req->shipment->user_id === $user->id);
        $isTravelerOwner = ($req->travel->user_id === $user->id);
        if (!$isShipmentOwner && !$isTravelerOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // 🟢 جلب أحداث التتبع
        $events = ShipmentTrackingEvent::where('shipment_travel_request_id', $req->id)
            ->with('user:id,name,type')
            ->orderBy('created_at', 'asc')
            ->get();

        // 🟢 بناء الـ Timeline بنفس منطق TimelineController
        $timeline = $this->buildTimeline($req, $events);

        // 🟢 رد موحّد شامل
        return response()->json([
            'shipment' => [
                'id' => $shipment->id,
                'from' => $shipment->from_city . ', ' . $shipment->from_country,
                'to'   => $shipment->to_city   . ', ' . $shipment->to_country,
                'weight' => $shipment->weight,
                'category' => $shipment->category,
                'description' => $shipment->description,
                'special_instructions' => $shipment->special_instructions,
                'delivery_from_date' => $shipment->delivery_from_date,
                'delivery_to_date'   => $shipment->delivery_to_date,
                'offered_price' => $shipment->offered_price,
                'status' => $shipment->status,       // 🟢 (new status column)
                'is_booked' => $shipment->is_booked, // يبقى زي ما اتفقنا
            ],
            'accepted_request' => [
                'id' => $req->id,
                'status' => $req->status, // accepted / delivered
                'agreed_price' => $req->offered_price ?? $shipment->offered_price,
                'travel' => [
                    'id' => $req->travel->id,
                    'from_country' => $req->travel->from_country,
                    'to_country'   => $req->travel->to_country,
                    'travel_date'  => $req->travel->travel_date,
                    'max_weight'   => $req->travel->max_weight,
                ],
                'traveler' => [
                    'id' => $req->travel->user->id,
                    'name' => $req->travel->user->name,
                    'type' => $req->travel->user->type, // traveler
                ],
                'sender' => [
                    'id' => $req->shipment->user->id,
                    'name' => $req->shipment->user->name,
                    'type' => $req->shipment->user->type, // sender
                ],
            ],
            'timeline' => $timeline,
            'progress_percentage' => $this->calculateProgress($timeline),
            'next_action' => $this->getNextAction($timeline, $user->id, $isShipmentOwner),
        ], 200);
    }

    // =========================
    // 🟢 نفس منطق TimelineController (مُدمَج هنا)
    // =========================

    private function buildTimeline($req, $events)
    {
        $timeline = [];

        // 1) تم قبول العرض
        $timeline[] = [
            'step' => 1,
            'title' => 'تم قبول العرض',
            'status' => 'completed',
            'icon' => '✅',
            'date' => $req->updated_at,
            'user' => $req->shipment->user->name . ' (المرسل)',
            'description' => 'تم قبول عرض ' . $req->travel->user->name,
            'details' => 'السعر المتفق عليه: ' . ($req->offered_price ?? $req->shipment->offered_price)
        ];

        // 2) الدفع
        $paymentEvent = $events->where('status', 'payment_received')->first();
        $timeline[] = [
            'step' => 2,
            'title' => $paymentEvent ? 'تم تأكيد الدفع' : 'في انتظار الدفع',
            'status' => $paymentEvent ? 'completed' : 'pending',
            'icon' => $paymentEvent ? '✅' : '⏳',
            'date' => $paymentEvent?->created_at,
            'user' => $paymentEvent ? $paymentEvent->user->name . ' (المرسل)' : null,
            'description' => $paymentEvent ? ($paymentEvent->note ?? 'تم تأكيد الدفع بنجاح') : 'يرجى من المرسل تأكيد الدفع',
            'details' => $paymentEvent
                ? 'الموقع: ' . ($paymentEvent->location ?? 'غير محدد')
                : 'المبلغ: ' . ($req->offered_price ?? $req->shipment->offered_price)
        ];

        // 3) تسليم الشحنة للمسافر
        $pickupEvent = $events->where('status', 'picked_up')->first();
        $timeline[] = [
            'step' => 3,
            'title' => $pickupEvent ? 'تم تسليم الشحنة للمسافر' : 'تسليم الشحنة للمسافر',
            'status' => $pickupEvent ? 'completed' : ($paymentEvent ? 'pending' : 'disabled'),
            'icon' => $pickupEvent ? '✅' : ($paymentEvent ? '⏳' : '⭕'),
            'date' => $pickupEvent?->created_at,
            'user' => $pickupEvent ? $pickupEvent->user->name . ' (المسافر)' : null,
            'description' => $pickupEvent ? ($pickupEvent->note ?? 'تم استلام الشحنة') : 'في انتظار استلام المسافر للشحنة',
            'details' => $pickupEvent ? 'الموقع: ' . ($pickupEvent->location ?? 'غير محدد') : 'حدد موعد الاستلام مع المسافر'
        ];

        // 4) في الطريق (اختياري)
        $transitEvent = $events->where('status', 'in_transit')->first();
        $timeline[] = [
            'step' => 4,
            'title' => $transitEvent ? 'الشحنة في الطريق' : 'في الطريق',
            'status' => $transitEvent ? 'completed' : ($pickupEvent ? 'optional' : 'disabled'),
            'icon' => $transitEvent ? '✅' : ($pickupEvent ? '🚗' : '⭕'),
            'date' => $transitEvent?->created_at,
            'user' => $transitEvent ? $transitEvent->user->name . ' (المسافر)' : null,
            'description' => $transitEvent ? ($transitEvent->note ?? 'الشحنة في الطريق') : 'تحديث اختياري من المسافر',
            'details' => $transitEvent ? 'الموقع: ' . ($transitEvent->location ?? 'غير محدد') : 'سيتم التحديث عند توفره'
        ];

        // 5) تم التسليم للمشتري
        $deliveredEvent = $events->where('status', 'delivered')->first();
        $timeline[] = [
            'step' => 5,
            'title' => $deliveredEvent ? 'تم تسليم الشحنة للمشتري' : 'تسليم الشحنة للمشتري',
            'status' => $deliveredEvent ? 'completed' : ($pickupEvent ? 'pending' : 'disabled'),
            'icon' => $deliveredEvent ? '✅' : ($pickupEvent ? '⏳' : '⭕'),
            'date' => $deliveredEvent?->created_at,
            'user' => $deliveredEvent ? $deliveredEvent->user->name . ' (المسافر)' : null,
            'description' => $deliveredEvent ? ($deliveredEvent->note ?? 'تم التسليم بنجاح') : 'في انتظار التسليم من المسافر',
            'details' => $deliveredEvent ? 'الموقع: ' . ($deliveredEvent->location ?? 'غير محدد') : 'تواصل مع المسافر للتسليم'
        ];

        // 6) تقييم المسافر (متاح بعد التسليم)
        $timeline[] = [
            'step' => 6,
            'title' => 'تقييم المسافر',
            'status' => $deliveredEvent ? 'pending' : 'disabled',
            'icon' => $deliveredEvent ? '⭐' : '⭕',
            'date' => null,
            'user' => null,
            'description' => $deliveredEvent ? 'يمكنك الآن تقييم المسافر' : 'التقييم بعد التسليم',
            'details' => $deliveredEvent ? 'ساعد الآخرين بتقييمك' : '—'
        ];

        return $timeline;
    }

    private function calculateProgress($timeline)
    {
        $completedSteps = collect($timeline)->where('status', 'completed')->count();
        $totalSteps = 5; // التقييم خارج الحساب
        return round(($completedSteps / $totalSteps) * 100);
    }

    private function getNextAction($timeline, $userId, $isShipmentOwner)
    {
        $pendingStep = collect($timeline)->where('status', 'pending')->first();
        if (!$pendingStep) return null;

        if ($pendingStep['step'] == 2) { // الدفع
            return $isShipmentOwner ? [
                'action' => 'confirm_payment',
                'title' => 'تأكيد الدفع',
                'description' => 'يرجى تأكيد دفع المبلغ المتفق عليه'
            ] : [
                'action' => 'wait',
                'title' => 'في انتظار المرسل',
                'description' => 'بانتظار تأكيد الدفع من المرسل'
            ];
        }

        // باقي الخطوات على المسافر
        return !$isShipmentOwner ? [
            'action' => 'update_status',
            'title' => $pendingStep['title'],
            'description' => $pendingStep['description']
        ] : [
            'action' => 'wait',
            'title' => 'في انتظار المسافر',
            'description' => 'بانتظار تحديث حالة الشحنة من المسافر'
        ];
    }
}
