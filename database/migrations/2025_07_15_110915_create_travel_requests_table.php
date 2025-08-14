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
    Schema::create('travel_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المسافر
        $table->string('from_country');
        $table->string('to_country');
        $table->date('travel_date');
        $table->float('max_weight'); // أقصى وزن يقدر ياخده
        $table->timestamps();
    });
}

};
