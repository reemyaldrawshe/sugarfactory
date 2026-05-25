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
        Schema::create('item_tracking_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['صرف', 'توريد', 'اتلاف']); // صرف=production, توريد=shipment, اتلاف=demolish

            // Tracked record info
            $table->unsignedBigInteger('trackable_id');
            $table->string('trackable_type'); // ProductionOrder, ShipmentItem, DemolishOrder
            $table->string('status'); // Different status per type

            // Item info
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->decimal('quantity', 10, 2);

            // Shipment info (optional for non-shipment types)
            $table->unsignedBigInteger('shipment_id')->nullable();

            // Sender info
            $table->string('sent_from_role');
            $table->string('sent_from_user_name');
            $table->unsignedBigInteger('sent_from_user_id');

            // Receiver info
            $table->string('sent_to_role');
            $table->string('sent_to_user_name');
            $table->unsignedBigInteger('sent_to_user_id');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['trackable_id', 'trackable_type']);
            $table->index('item_id');
            $table->index('shipment_id');
            $table->index('type');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_tracking_logs');
    }
};
