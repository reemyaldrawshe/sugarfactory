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
            $table->enum('type', ['production', 'sales'])->default('production');
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                 ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('production_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
                // 💡 التعديل: إضافة موظف المبيعات (nullable لأنه قد يكون طلب إنتاج)
            $table->foreignId('sales_id')
                ->nullable()

                ->constrained('users')
                ->cascadeOnDelete();
$table->decimal('quantity', 16, 6)->nullable(); // الكمية المطلوبة
            $table->decimal('produced_quantity', 16, 6)->default(0.000000); // المنتج فعلياً
            $table->decimal('deviation', 16, 6)->default(0.000000); // الفرق/الانحراف
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
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
