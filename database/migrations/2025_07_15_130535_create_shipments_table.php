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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المرسل

            // بلد ومدينة الإرسال والاستلام
            $table->string('from_country');
            $table->string('from_city');
            $table->string('to_country');
            $table->string('to_city');

            // بيانات الشحنة
            $table->float('weight'); // وزن الشحنة بالكيلو
            $table->string('category'); // الكاتيجوري (ملابس، إلكترونيات...)
            $table->text('description')->nullable(); // وصف اختياري
            $table->text('special_instructions')->nullable(); // تعليمات خاصة

            // فترة التوصيل
            $table->date('delivery_from_date')->nullable();
            $table->date('delivery_to_date')->nullable();

            // السعر المعروض
            $table->decimal('offered_price', 10, 2)->nullable();

            // حالة الشحنة
            $table->boolean('is_booked')->default(false); // هل الشحنة محجوزة
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
