<?php

namespace App\Http\Controllers;

use App\Models\ShipmentTrackingEvent;
use App\Models\ShipmentTravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimelineController extends Controller
{
    public function getShipmentTimeline($shipmentTravelRequestId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $req = ShipmentTravelRequest::with(['shipment.user', 'travel.user'])
            ->findOrFail($shipmentTravelRequestId);

        $isShipmentOwner = ($req->shipment->user_id === $user->id);
        $isTravelerOwner = ($req->travel->user_id === $user->id);

        if (!$isShipmentOwner && !$isTravelerOwner) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        if ($req->status !== 'accepted') {
            return response()->json(['message' => 'هذا الطلب غير مقبول بعد'], 400);
        }

        $events = ShipmentTrackingEvent::where('shipment_travel_request_id', $shipmentTravelRequestId)
            ->with('user:id,name,type')
            ->orderBy('created_at', 'asc')
            ->get()
            ->keyBy('status'); // 🟢 الأحداث مربوطة بالحالة مباشرة

        $timeline = $this->buildTimeline($req, $events);

        return response()->json([
            'shipment_info' => [
                'id' => $req->shipment->id,
                'description' => $req->shipment->description,
                'from' => $req->shipment->from_city . ', ' . $req->shipment->from_country,
                'to' => $req->shipment->to_city . ', ' . $req->shipment->to_country,
                'sender' => $req->shipment->user->name,
                'traveler' => $req->travel->user->name,
                'agreed_price' => $req->offered_price ?? $req->shipment->offered_price,
                'current_status' => $this->getCurrentStatus($events, $req),
            ],
            'timeline' => $timeline,
            'progress_percentage' => $this->calculateProgress($timeline),
            'next_action' => $this->getNextAction($timeline, $user->id, $isShipmentOwner),
        ], 200);
    }

    private function buildTimeline($req, $events)
    {
        return [
            [
                'step' => 1,
                'title' => 'تم قبول العرض',
                'status' => 'completed',
                'date' => $req->updated_at,
                'user' => $req->shipment->user->name . ' (المرسل)',
                'description' => 'تم قبول عرض ' . $req->travel->user->name,
                'details' => 'السعر المتفق عليه: ' . ($req->offered_price ?? $req->shipment->offered_price) . ' جنيه'
            ],
            [
                'step' => 2,
                'title' => isset($events['payment_received']) ? 'تم تأكيد الدفع' : 'في انتظار الدفع',
                'status' => isset($events['payment_received']) ? 'completed' : 'pending',
                'date' => $events['payment_received']->created_at ?? null,
                'user' => $events['payment_received']->user->name ?? null,
                'description' => isset($events['payment_received']) ?
                    ($events['payment_received']->note ?? 'تم تأكيد الدفع بنجاح') :
                    'يرجى من المرسل تأكيد الدفع',
                'details' => isset($events['payment_received']) ?
                    'الموقع: ' . ($events['payment_received']->location ?? 'غير محدد') :
                    'المبلغ: ' . ($req->offered_price ?? $req->shipment->offered_price) . ' جنيه'
            ],
            [
                'step' => 3,
                'title' => isset($events['picked_up']) ? 'تم تسليم الشحنة للمسافر' : 'تسليم الشحنة للمسافر',
                'status' => isset($events['picked_up']) ? 'completed' : (isset($events['payment_received']) ? 'pending' : 'disabled'),
                'date' => $events['picked_up']->created_at ?? null,
                'user' => $events['picked_up']->user->name ?? null,
                'description' => isset($events['picked_up']) ?
                    ($events['picked_up']->note ?? 'تم استلام الشحنة بنجاح') :
                    'في انتظار استلام المسافر للشحنة',
                'details' => isset($events['picked_up']) ?
                    'الموقع: ' . ($events['picked_up']->location ?? 'غير محدد') :
                    'يرجى التواصل مع المسافر لتحديد موعد الاستلام'
            ],
            [
                'step' => 4,
                'title' => isset($events['in_transit']) ? 'الشحنة في الطريق' : 'في الطريق',
                'status' => isset($events['in_transit']) ? 'completed' : (isset($events['picked_up']) ? 'optional' : 'disabled'),
                'date' => $events['in_transit']->created_at ?? null,
                'user' => $events['in_transit']->user->name ?? null,
                'description' => isset($events['in_transit']) ?
                    ($events['in_transit']->note ?? 'الشحنة في الطريق') :
                    'تحديث اختياري من المسافر',
                'details' => isset($events['in_transit']) ?
                    'الموقع: ' . ($events['in_transit']->location ?? 'غير محدد') :
                    'سيتم تحديث الموقع عند توفره'
            ],
            [
                'step' => 5,
                'title' => isset($events['delivered']) ? 'تم تسليم الشحنة للمشتري' : 'تسليم الشحنة للمشتري',
                'status' => isset($events['delivered']) ? 'completed' : (isset($events['picked_up']) ? 'pending' : 'disabled'),
                'date' => $events['delivered']->created_at ?? null,
                'user' => $events['delivered']->user->name ?? null,
                'description' => isset($events['delivered']) ?
                    ($events['delivered']->note ?? 'تم التسليم بنجاح للمشتري') :
                    'في انتظار التسليم من المسافر',
                'details' => isset($events['delivered']) ?
                    'الموقع: ' . ($events['delivered']->location ?? 'غير محدد') :
                    'يرجى التواصل مع المسافر لتحديد موعد التسليم'
            ],
            [
                'step' => 6,
                'title' => 'تقييم المسافر',
                'status' => isset($events['delivered']) ? 'pending' : 'disabled',
                'date' => null,
                'user' => null,
                'description' => isset($events['delivered']) ?
                    'يمكنك الآن تقييم المسافر' :
                    'سيتم تفعيله بعد التسليم',
                'details' => isset($events['delivered']) ?
                    'ساعد الآخرين بتقييمك للمسافر' :
                    'التقييم متاح بعد اكتمال التسليم'
            ]
        ];
    }

    private function calculateProgress($timeline)
    {
        $completedSteps = collect($timeline)->where('status', 'completed')->count();
        $totalSteps = collect($timeline)->whereNotIn('step', [6])->count();
        return round(($completedSteps / $totalSteps) * 100);
    }

    private function getCurrentStatus($events, $req)
    {
        if ($events->isNotEmpty()) {
            return $events->last()->status;
        }
        return $req->shipment->status;
    }

    private function getNextAction($timeline, $userId, $isShipmentOwner)
    {
        $pendingStep = collect($timeline)->where('status', 'pending')->first();

        if (!$pendingStep) {
            return null;
        }

        if ($pendingStep['step'] == 2) { // الدفع
            return $isShipmentOwner ? [
                'action' => 'confirm_payment',
                'title' => 'تأكيد الدفع',
                'description' => 'يرجى تأكيد دفع المبلغ المتفق عليه'
            ] : [
                'action' => 'wait',
                'title' => 'في انتظار المرسل',
                'description' => 'في انتظار تأكيد الدفع من المرسل'
            ];
        }

        if ($pendingStep['step'] >= 3) { // باقي الخطوات للمسافر
            return !$isShipmentOwner ? [
                'action' => 'update_status',
                'title' => $pendingStep['title'],
                'description' => $pendingStep['description']
            ] : [
                'action' => 'wait',
                'title' => 'في انتظار المسافر',
                'description' => 'في انتظار تحديث من المسافر'
            ];
        }

        return null;
    }
}
