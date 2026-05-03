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
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_required');
            $table->integer('quantity_received')->default(0);
            $table->decimal('price', 10, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('invoice_image')->nullable();
            $table->string('lab_test_file')->nullable();
            $table->text('note')->nullable();
            $table->json('price_history')->nullable(); // Track price changes
            $table->json('quantity_history')->nullable(); // Track quantity changes
            $table->timestamps();

            $table->unique(['shipment_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
