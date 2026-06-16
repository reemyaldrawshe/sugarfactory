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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('production_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->integer('quantity'); // المطلوب إنتاجه
            $table->integer('produced_quantity')->default(0); // المنتج فعلياً

            $table->string('status')->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
