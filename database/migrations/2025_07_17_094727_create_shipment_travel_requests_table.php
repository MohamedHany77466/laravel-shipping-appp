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
        Schema::create('shipment_travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('travel_id')->constrained('travel_requests')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->boolean('is_paid')->default(false);
            $table->decimal('offered_price', 10, 2)->nullable(); //  السعر اللي يقدمه المسافر
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_travel_requests');
    }
};
