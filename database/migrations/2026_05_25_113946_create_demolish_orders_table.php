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
        Schema::create('demolish_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('shipment_id')->references('id')->on('shipments');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demolish_orders');
    }
};
