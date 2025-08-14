<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\TravelRequestController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentTravelRequestController;
use App\Http\Controllers\SenderController;
use App\Http\Controllers\TravelerController;
use App\Http\Controllers\ShipmentTrackingEventController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\DashboardController;


// ==================
// Auth
// ==================
Route::middleware('throttle.custom:auth,5,1')->group(function () {
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// بيانات المستخدم الحالي
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ==================
// مسارات محمية بـ Sanctum
// ==================
Route::middleware(['auth:sanctum', 'throttle.custom:api,100,1'])->group(function () {

    // المسافر يضيف سفريات
    Route::post('/travel-requests', [TravelRequestController::class, 'store']);

    // المرسل يضيف شحنات
    Route::post('/shipments', [ShipmentController::class, 'store']);

    // المسافر يربط شحنة بسفرة
    Route::post('/shipment-travel-requests', [ShipmentTravelRequestController::class, 'store']);

    // عرض كل الطلبات المرتبطة بسفرة معينة
    Route::get('/travel/{travel_id}/requests', [ShipmentTravelRequestController::class, 'index']);

    // تحديث حالة الطلب (للمرسل)
    Route::post('/shipment-request/{id}/status', [ShipmentTravelRequestController::class, 'updateStatus']);

    // الشحنات المقبولة
    Route::get('/my-accepted-shipments', [ShipmentController::class, 'myAcceptedShipments']);
    Route::get('/travel/{id}/accepted-shipments', [TravelRequestController::class, 'acceptedShipments']);
    Route::get('/travels/accepted-shipments', [TravelerController::class, 'acceptedShipments']);

    // واجهة المرسل
    Route::get('/sender/shipments', [SenderController::class, 'myShipments']);
    Route::get('/sender/shipment/{id}/requests', [SenderController::class, 'shipmentRequests']);
    Route::post('/sender/request/{id}/status', [SenderController::class, 'updateRequestStatus']);
});
Route::middleware(['auth:sanctum', 'throttle.custom:api,30,1'])->get('/notifications', function (Request $request) {
    return $request->user()->notifications;
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/shipments/{id}', [ShipmentController::class, 'updateShipment']);
    Route::delete('/shipments/{id}', [ShipmentController::class, 'deleteShipment']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tracking-events/{shipment_travel_request_id}', [ShipmentTrackingEventController::class, 'index']);
    Route::post('/tracking-events', [ShipmentTrackingEventController::class, 'store']);
    Route::get('/timeline/{shipment_travel_request_id}', [TimelineController::class, 'getShipmentTimeline']);
    
    // البحث والفلترة
    Route::get('/search/shipments', [SearchController::class, 'searchShipments']);
    Route::get('/search/travels', [SearchController::class, 'searchTravels']);
    
    // لوحات التحكم
    Route::get('/dashboard/sender', [DashboardController::class, 'senderDashboard']);
    Route::get('/dashboard/traveler', [DashboardController::class, 'travelerDashboard']);
    Route::get('/dashboard/stats', [DashboardController::class, 'userStats']);
});
