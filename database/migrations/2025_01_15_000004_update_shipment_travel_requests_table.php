<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipment_travel_requests', function (Blueprint $table) {
            // تحديث حالات الطلب
            $table->enum('status', [
                'pending',        // في انتظار الرد
                'accepted',       // تم القبول من الطرفين
                'paid',           // تم الدفع
                'picked_up',      // تم استلام الشحنة
                'in_transit',     // في الطريق
                'delivered',      // تم التسليم
                'completed',      // مكتمل
                'rejected',       // مرفوض
                'cancelled'       // ملغي
            ])->default('pending')->change();
            
            // إضافة تواريخ مهمة
            $table->timestamp('accepted_at')->nullable()->after('offered_price');
            $table->timestamp('paid_at')->nullable()->after('accepted_at');
            $table->timestamp('picked_up_at')->nullable()->after('paid_at');
            $table->timestamp('in_transit_at')->nullable()->after('picked_up_at');
            $table->timestamp('delivered_at')->nullable()->after('in_transit_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_travel_requests', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_at', 'paid_at', 'picked_up_at', 
                'in_transit_at', 'delivered_at', 'completed_at'
            ]);
            $table->string('status')->default('pending')->change();
        });
    }
};