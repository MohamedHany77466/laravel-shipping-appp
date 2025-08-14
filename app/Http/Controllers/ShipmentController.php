<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ShipmentTravelRequest;

class ShipmentController extends Controller
{
    // إضافة شحنة مع تفاصيل كاملة
    public function store(Request $request)
    {
        $user = Auth::user();

        // تحقق أن المستخدم مرسل
        if (!$user || $user->type !== 'sender') {
            return response()->json(['message' => 'مسموح فقط للمُرسلين بإضافة شحنات'], 403);
        }

        try {
            // تحقق من البيانات مع validation محسن
            $validated = $request->validate([
                'from_country' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
                'from_city' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
                'to_country' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
                'to_city' => 'required|string|min:2|max:100|regex:/^[\p{L}\s\-]+$/u',
                'weight' => 'required|numeric|min:0.1|max:50',
                'category' => 'required|string|max:100|in:electronics,clothing,books,documents,food,gifts,medical,other',
                'description' => 'nullable|string|max:1000',
                'special_instructions' => 'nullable|string|max:500',
                'delivery_from_date' => 'required|date|after_or_equal:today',
                'delivery_to_date' => 'required|date|after_or_equal:delivery_from_date',
                'offered_price' => 'required|numeric|min:1|max:10000',
            ]);
            
            // إنشاء الشحنة
            $shipment = Shipment::create([
                'user_id' => $user->id,
                'from_country' => $validated['from_country'],
                'from_city' => $validated['from_city'],
                'to_country' => $validated['to_country'],
                'to_city' => $validated['to_city'],
                'weight' => $validated['weight'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
                'special_instructions' => $validated['special_instructions'] ?? null,
                'delivery_from_date' => $validated['delivery_from_date'],
                'delivery_to_date' => $validated['delivery_to_date'],
                'offered_price' => $validated['offered_price'],
                'status' => 'open',
            ]);

            return response()->json([
                'message' => 'تمت إضافة الشحنة بنجاح',
                'data' => $shipment->load('user:id,name,type')
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'بيانات غير صحيحة',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء إضافة الشحنة',
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في الخادم'
            ], 500);
        }
    }

    // المرسل يشوف الشحنات المقبولة
    public function myAcceptedShipments()
    {
        $userId = Auth::id();

        $shipments = Shipment::where('user_id', $userId)
            ->whereHas('shipmentTravelRequests', function ($query) {
                $query->where('status', 'accepted');
            })
            ->with(['shipmentTravelRequests' => function ($query) {
                $query->where('status', 'accepted')->with('travel');
            }])
            ->get();

        return response()->json([
            'message' => 'قائمة الشحنات المقبولة',
            'data'    => $shipments,
        ]);
    }

    // عرض كل الطلبات المقدمة على شحنات المرسل
    public function myShipmentRequests()
    {
        $user = Auth::user();

        $requests = ShipmentTravelRequest::with(['shipment', 'travel'])
            ->whereHas('shipment', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        return response()->json([
            'message' => 'كل الطلبات المقدمة على شحناتك',
            'data' => $requests
        ]);
    }
    public function updateShipment(Request $request, $id)
{
    $user = Auth::user();

    //  تأكد إن المستخدم مرسل
    if ($user->type !== 'sender') {
        return response()->json(['message' => 'مسموح فقط للمرسلين بتعديل الشحنات'], 403);
    }

    //  نجيب الشحنة
    $shipment = Shipment::where('id', $id)->where('user_id', $user->id)->first();

    if (!$shipment) {
        return response()->json(['message' => 'الشحنة غير موجودة أو لا تخصك'], 404);
    }

    //  تحقق من الطلبات المرتبطة بالشحنة
    $hasActiveRequests = ShipmentTravelRequest::where('shipment_id', $shipment->id)
        ->where('status', '!=', 'rejected')
        ->exists();

    if ($hasActiveRequests) {
        return response()->json(['message' => 'لا يمكن تعديل الشحنة لأن لديها طلبات نشطة'], 403);
    }

    // التحقق من البيانات الجديدة
      $validated = $request->validate([
        'from_country' => 'sometimes|string',
        'from_city' => 'sometimes|string',
        'to_country' => 'sometimes|string',
        'to_city' => 'sometimes|string',
        'weight' => 'sometimes|numeric|min:0.1',
        'category' => 'sometimes|string',
        'description' => 'sometimes|nullable|string',
        'special_instructions' => 'sometimes|nullable|string',
        'delivery_from_date' => 'sometimes|date',
        'delivery_to_date' => 'sometimes|date|after_or_equal:delivery_from_date',
        'offered_price' => 'sometimes|numeric|min:0',
    ]);

    $shipment->update($validated);

    return response()->json([
        'message' => 'تم تعديل الشحنة بنجاح',
        'data' => $shipment
    ]);
}

public function deleteShipment($id)
{
    $user = Auth::user();

    //  تأكد إن المستخدم مرسل
    if ($user->type !== 'sender') {
        return response()->json(['message' => 'مسموح فقط للمرسلين بحذف الشحنات'], 403);
    }

    //  نجيب الشحنة
    $shipment = Shipment::where('id', $id)->where('user_id', $user->id)->first();

    if (!$shipment) {
        return response()->json(['message' => 'الشحنة غير موجودة أو لا تخصك'], 404);
    }

    //  تحقق من الطلبات المرتبطة بالشحنة
    $hasActiveRequests = ShipmentTravelRequest::where('shipment_id', $shipment->id)
        ->where('status', '!=', 'rejected')
        ->exists();

    if ($hasActiveRequests) {
        return response()->json(['message' => 'لا يمكن حذف الشحنة لأن لديها طلبات نشطة'], 403);
    }  

    //  حذف الشحنة
    $shipment->delete();

    return response()->json(['message' => 'تم حذف الشحنة بنجاح']);
}

}