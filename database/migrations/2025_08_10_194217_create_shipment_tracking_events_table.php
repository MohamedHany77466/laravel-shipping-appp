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
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_travel_request_id')
                  ->constrained('shipment_travel_requests')
                  ->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', [
                'payment_received',   // تم استلام الدفع
                'picked_up',          // تم استلام الشحنة من المرسل
                'in_transit',         // الشحنة في الطريق
                'out_for_delivery',   // الشحنة خارجة للتسليم
                'delivered',          // تم التسليم
                'problem'             // مشكلة بالشحنة
            ]);
            $table->string('location')->nullable();  // الموقع النصي
            $table->text('note')->nullable();        // ملاحظات إضافية
            $table->decimal('lat', 10, 7)->nullable(); // إحداثيات GPS (اختياري)
            $table->decimal('lng', 10, 7)->nullable(); // إحداثيات GPS (اختياري)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
