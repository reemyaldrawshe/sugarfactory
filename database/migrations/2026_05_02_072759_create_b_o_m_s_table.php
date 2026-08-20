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
        Schema::create('b_o_m_s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('final_item_id')
                ->constrained('items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('basic_item_id')
                ->constrained('items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
// 💡 تعديل الحقول لتستوعب الكسور الدقيقة جداً (حتى 6 خانات عشرية)
    $table->decimal('basic_item_quantity', 16, 6)->default(1.000000);
    $table->decimal('final_item_quantity', 16, 6)->default(1.000000);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_o_m_s');
    }
};
