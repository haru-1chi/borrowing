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
        Schema::create('borrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('borrow_days')->nullable();
            $table->integer('borrow_product_number')->nullable();
            $table->string('borrow_status')->nullable()->default('กำลังยืม');
            $table->timestamp('return_date')->nullable();
            $table->timestamps();//เพิ่มฟิลด์วันยืม วันทำการคืนได้เลย แยกจากที่สร้างแบบ auto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrows');
    }
};
