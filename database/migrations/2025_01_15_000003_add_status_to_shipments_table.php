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
        Schema::table('shipments', function (Blueprint $table) {
            // تحديث حقل status ليشمل الحالات الجديدة
            $table->enum('status', [
                'open',           // مفتوحة للعروض
                'accepted',       // تم قبول عرض
                'paid',           // تم الدفع
                'picked_up',      // تم استلام الشحنة من المسافر
                'in_transit',     // في الطريق
                'delivered',      // تم التسليم
                'completed',      // مكتملة
                'cancelled'       // ملغية
            ])->default('open')->change();
            
            // إضافة QR code للشحنة
            $table->string('qr_code')->nullable()->after('status');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['qr_code', 'qr_generated_at']);
            $table->string('status')->default('pending')->change();
        });
    }
};