<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\TravelRequest;
use App\Models\ShipmentTravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * لوحة تحكم المرسل
     */
    public function senderDashboard()
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'sender') {
            return response()->json(['message' => 'مسموح للمرسلين فقط'], 403);
        }

        // إحصائيات المرسل
        $stats = [
            'total_shipments' => Shipment::where('user_id', $user->id)->count(),
            'active_shipments' => Shipment::where('user_id', $user->id)
                ->where('status', 'open')
                ->where('is_booked', false)
                ->count(),
            'booked_shipments' => Shipment::where('user_id', $user->id)
                ->where('is_booked', true)
                ->count(),
            'delivered_shipments' => Shipment::where('user_id', $user->id)
                ->where('status', 'delivered')
                ->count(),
            'pending_requests' => ShipmentTravelRequest::whereHas('shipment', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'pending')->count(),
            'total_spent' => ShipmentTravelRequest::whereHas('shipment', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'accepted')->sum('offered_price'),
        ];

        // آخر الشحنات
        $recent_shipments = Shipment::where('user_id', $user->id)
            ->with(['requests' => function($q) {
                $q->where('status', 'pending')->with('travel.user:id,name');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // الطلبات المعلقة
        $pending_requests = ShipmentTravelRequest::whereHas('shipment', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->where('status', 'pending')
        ->with(['shipment:id,description,from_city,to_city', 'travel.user:id,name'])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

        return response()->json([
            'message' => 'لوحة تحكم المرسل',
            'stats' => $stats,
            'recent_shipments' => $recent_shipments,
            'pending_requests' => $pending_requests,
        ]);
    }

    /**
     * لوحة تحكم المسافر
     */
    public function travelerDashboard()
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'traveler') {
            return response()->json(['message' => 'مسموح للمسافرين فقط'], 403);
        }

        // إحصائيات المسافر
        $stats = [
            'total_travels' => TravelRequest::where('user_id', $user->id)->count(),
            'active_travels' => TravelRequest::where('user_id', $user->id)
                ->where('travel_date', '>=', now())
                ->count(),
            'completed_travels' => TravelRequest::where('user_id', $user->id)
                ->where('travel_date', '<', now())
                ->count(),
            'accepted_shipments' => ShipmentTravelRequest::whereHas('travel', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'accepted')->count(),
            'delivered_shipments' => ShipmentTravelRequest::whereHas('travel', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'delivered')->count(),
            'total_earned' => ShipmentTravelRequest::whereHas('travel', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'accepted')->sum('offered_price'),
        ];

        // آخر الرحلات
        $recent_travels = TravelRequest::where('user_id', $user->id)
            ->with(['shipmentRequests' => function($q) {
                $q->where('status', 'pending')->with('shipment.user:id,name');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // الطلبات الجديدة
        $new_requests = ShipmentTravelRequest::whereHas('travel', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->where('status', 'pending')
        ->with(['shipment.user:id,name', 'shipment:id,user_id,description,from_city,to_city,offered_price'])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

        return response()->json([
            'message' => 'لوحة تحكم المسافر',
            'stats' => $stats,
            'recent_travels' => $recent_travels,
            'new_requests' => $new_requests,
        ]);
    }

    /**
     * الإحصائيات العامة للمستخدم
     */
    public function userStats()
    {
        $user = Auth::user();
        
        $baseStats = [
            'account_created' => $user->created_at,
            'account_status' => $user->account_status,
            'verification_status' => [
                'email_verified' => $user->email_verified,
                'phone_verified' => $user->phone_verified,
                'identity_verified' => $user->identity_verified,
            ],
            'profile_completion' => $this->calculateProfileCompletion($user),
        ];

        if ($user->type === 'sender') {
            return array_merge($baseStats, $this->getSenderStats($user));
        } else {
            return array_merge($baseStats, $this->getTravelerStats($user));
        }
    }

    private function calculateProfileCompletion($user)
    {
        $fields = ['first_name', 'last_name', 'email', 'phone_number', 'date_of_birth', 'gender'];
        $completed = 0;
        
        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $completed++;
            }
        }
        
        if ($user->email_verified) $completed++;
        if ($user->phone_verified) $completed++;
        if (!empty($user->profile_picture_url)) $completed++;
        
        return round(($completed / 9) * 100);
    }

    private function getSenderStats($user)
    {
        return [
            'shipments_count' => Shipment::where('user_id', $user->id)->count(),
            'active_shipments' => Shipment::where('user_id', $user->id)->where('status', 'open')->count(),
            'total_requests_received' => ShipmentTravelRequest::whereHas('shipment', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),
        ];
    }

    private function getTravelerStats($user)
    {
        return [
            'travels_count' => TravelRequest::where('user_id', $user->id)->count(),
            'active_travels' => TravelRequest::where('user_id', $user->id)->where('travel_date', '>=', now())->count(),
            'requests_sent' => ShipmentTravelRequest::whereHas('travel', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),
        ];
    }
}