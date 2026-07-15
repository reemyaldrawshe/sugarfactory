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
        // جدول صرف المواد من المخزون
        Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('item_id') // المادة الخام
                ->constrained('items')
                ->cascadeOnDelete();

            $table->foreignId('shipment_item_id')
            ->nullable()
              ->constrained('shipment_items')
              ->cascadeOnDelete()
              ;

               // 💡 التعديل الثاني: استخدام decimal للسماح بالكميات التي تحتوي على فواصل عشرية (مثال: 87.1)
            $table->decimal('required_quantity', 10, 2); 
            $table->decimal('consumed_quantity', 10, 2)->default(0);
               $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_materials');
    }
};
