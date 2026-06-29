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
            $table->string('supplier')->nullable();
            $table->string('supplier_number')->nullable();
            $table->date('received_at');
            $table->string('status');
            $table->decimal('total_price', 15, 2)->default(0); // أضف هذا السطر
            $table->foreignId('warehouse_id')->constrained('users');
            $table->foreignId('admin_approved_by')->nullable()->constrained('users');
            $table->timestamp('admin_approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('purchase_updated_by')->nullable()->constrained('users');
            $table->timestamp('purchase_updated_at')->nullable();
            $table->foreignId('warehouse_confirmed_by')->nullable()->constrained('users');
            $table->timestamp('warehouse_confirmed_at')->nullable();
            $table->foreignId('sent_to_lab_by')->nullable()->constrained('users');
            $table->timestamp('sent_to_lab_at')->nullable();
            $table->foreignId('lab_approved_by')->nullable()->constrained('users');
            $table->timestamp('lab_approved_at')->nullable();
            $table->text('lab_rejection_reason')->nullable();
            $table->foreignId('final_confirmed_by')->nullable()->constrained('users');
            $table->timestamp('final_confirmed_at')->nullable();
            $table->json('invoice_images')->nullable(); // هنا سيتم حفظ مصفوفة روابط الصور
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('received_at');
            $table->index(['status', 'created_at']);
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
