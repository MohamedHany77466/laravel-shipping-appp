<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * البحث في الشحنات للمسافرين
     */
    public function searchShipments(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'traveler') {
            return response()->json(['message' => 'مسموح للمسافرين فقط'], 403);
        }

        $validated = $request->validate([
            'from_country' => 'nullable|string|max:100',
            'to_country' => 'nullable|string|max:100',
            'from_city' => 'nullable|string|max:100',
            'to_city' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'max_weight' => 'nullable|numeric|min:0.1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'delivery_from' => 'nullable|date',
            'delivery_to' => 'nullable|date',
            'sort_by' => 'nullable|in:price_asc,price_desc,date_asc,date_desc,weight_asc,weight_desc',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Shipment::with('user:id,name,type')
            ->where('is_booked', false)
            ->where('status', 'open');

        // تطبيق الفلاتر
        if (!empty($validated['from_country'])) {
            $query->where('from_country', 'like', '%' . $validated['from_country'] . '%');
        }

        if (!empty($validated['to_country'])) {
            $query->where('to_country', 'like', '%' . $validated['to_country'] . '%');
        }

        if (!empty($validated['from_city'])) {
            $query->where('from_city', 'like', '%' . $validated['from_city'] . '%');
        }

        if (!empty($validated['to_city'])) {
            $query->where('to_city', 'like', '%' . $validated['to_city'] . '%');
        }

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (!empty($validated['max_weight'])) {
            $query->where('weight', '<=', $validated['max_weight']);
        }

        if (!empty($validated['min_price'])) {
            $query->where('offered_price', '>=', $validated['min_price']);
        }

        if (!empty($validated['max_price'])) {
            $query->where('offered_price', '<=', $validated['max_price']);
        }

        if (!empty($validated['delivery_from'])) {
            $query->where('delivery_to_date', '>=', $validated['delivery_from']);
        }

        if (!empty($validated['delivery_to'])) {
            $query->where('delivery_from_date', '<=', $validated['delivery_to']);
        }

        // ترتيب النتائج
        switch ($validated['sort_by'] ?? 'date_desc') {
            case 'price_asc':
                $query->orderBy('offered_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('offered_price', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'weight_asc':
                $query->orderBy('weight', 'asc');
                break;
            case 'weight_desc':
                $query->orderBy('weight', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $validated['per_page'] ?? 15;
        $shipments = $query->paginate($perPage);

        return response()->json([
            'message' => 'نتائج البحث',
            'data' => $shipments->items(),
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'last_page' => $shipments->lastPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
            ]
        ]);
    }

    /**
     * البحث في الرحلات للمرسلين
     */
    public function searchTravels(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || $user->type !== 'sender') {
            return response()->json(['message' => 'مسموح للمرسلين فقط'], 403);
        }

        $validated = $request->validate([
            'from_country' => 'nullable|string|max:100',
            'to_country' => 'nullable|string|max:100',
            'min_weight' => 'nullable|numeric|min:0.1',
            'travel_from' => 'nullable|date',
            'travel_to' => 'nullable|date',
            'sort_by' => 'nullable|in:date_asc,date_desc,weight_asc,weight_desc',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = TravelRequest::with('user:id,name,type')
            ->where('travel_date', '>=', now());

        // تطبيق الفلاتر
        if (!empty($validated['from_country'])) {
            $query->where('from_country', 'like', '%' . $validated['from_country'] . '%');
        }

        if (!empty($validated['to_country'])) {
            $query->where('to_country', 'like', '%' . $validated['to_country'] . '%');
        }

        if (!empty($validated['min_weight'])) {
            $query->where('max_weight', '>=', $validated['min_weight']);
        }

        if (!empty($validated['travel_from'])) {
            $query->where('travel_date', '>=', $validated['travel_from']);
        }

        if (!empty($validated['travel_to'])) {
            $query->where('travel_date', '<=', $validated['travel_to']);
        }

        // ترتيب النتائج
        switch ($validated['sort_by'] ?? 'date_asc') {
            case 'date_desc':
                $query->orderBy('travel_date', 'desc');
                break;
            case 'weight_asc':
                $query->orderBy('max_weight', 'asc');
                break;
            case 'weight_desc':
                $query->orderBy('max_weight', 'desc');
                break;
            default:
                $query->orderBy('travel_date', 'asc');
        }

        $perPage = $validated['per_page'] ?? 15;
        $travels = $query->paginate($perPage);

        return response()->json([
            'message' => 'نتائج البحث',
            'data' => $travels->items(),
            'pagination' => [
                'current_page' => $travels->currentPage(),
                'last_page' => $travels->lastPage(),
                'per_page' => $travels->perPage(),
                'total' => $travels->total(),
            ]
        ]);
    }
}