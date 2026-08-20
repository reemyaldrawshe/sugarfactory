<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <--- أضف هذا السطر هنا
return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول رئيسي لطلب الجرد
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // رمز الطلب مثل: AUD-2026-0001
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable(); // سبب الجرد أو ملاحظات عامة
            $table->text('rejection_reason')->nullable(); // سبب الرفض إن وجد
            
            $table->unsignedBigInteger('created_by'); // أمين المستودع الذي قام بالجرد
            $table->unsignedBigInteger('approved_by')->nullable(); // الأدمن الذي وافق/رفض
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });

        // 2. جدول تفاصيل المواد والدفعات المجرودة
        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained('inventory_audits')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('item_id')->constrained('items');
            
            // معرف الدفعة (shipment_item_id)
            $table->unsignedBigInteger('shipment_item_id'); 
            // 💡 توحيد دقة الكميات إلى (16, 6) لاستيعاب الفروقات والكسور الدقيقة في الجرد
            $table->decimal('old_quantity', 16, 6);     // الكمية بالنظام وقت الجرد
            $table->decimal('actual_quantity', 16, 6);  // الكمية الفعلية المقاسة
            $table->decimal('difference', 16, 6);       // الفرق (actual - old)
            
            // 💡 نسبة التطابق % (5, 2 تسمح بـ 100.00%)
            $table->decimal('match_percentage', 5, 2);
            $table->text('notes')->nullable(); // ملاحظات خاصة بالدفعة
            $table->timestamps();

            $table->foreign('shipment_item_id')->references('id')->on('shipment_items');
        });

        // 3. تعديل عمود type في item_tracking_logs لإضافة 'جرد'
        DB::statement("ALTER TABLE item_tracking_logs MODIFY COLUMN type ENUM('صرف', 'توريد', 'اتلاف', 'بيع وتوزيع', 'جرد') NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
    }
};