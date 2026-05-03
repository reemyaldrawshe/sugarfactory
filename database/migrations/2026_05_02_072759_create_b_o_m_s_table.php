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
            $table->integer('basic_item_quantity')->default(0);
            $table->integer('final_item_quantity')->default(0);
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
