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
           
        $table->foreignId('shipment_id')
            ->nullable()
            ->constrained()
            ->cascadeOnDelete();
        $table->foreignId('production_order_id')
        ->nullable()
        ->constrained('production_orders')
        ->cascadeOnDelete();    
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
        $table->decimal('quantity_required', 16, 6);
    $table->decimal('quantity_received', 16, 6)->default(0.000000);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
$table->decimal('quantity_reserved', 16, 6)->default(0.000000);
            $table->date('expiry_date')->nullable();
            $table->string('invoice_image')->nullable();
            $table->string('lab_test_file')->nullable();
            $table->text('note')->nullable();
            $table->json('price_history')->nullable(); // Track price changes
            $table->json('quantity_history')->nullable(); // Track quantity changes
            $table->timestamps();

      $table->unique(['shipment_id', 'production_order_id', 'item_id'], 'unique_shipment_item_batch');        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
