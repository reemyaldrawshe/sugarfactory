<?php

use App\Enums\ProductionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('الموظف الذي أنشأ طلب البيع');
            $table->string('customer_name')->nullable()->comment('اسم العميل أو الجهة المستلمة');
            $table->string('status')->default(ProductionStatusEnum::DIST_PENDING->value);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_orders');
    }
};