<?php
// app/Enums/ShipmentStatus.php
namespace App\Enums;

enum ShipmentStatus: string
{
    case PENDING_ADMIN = 'pending_admin'; // بانتظار موافقة المدير
    case PENDING_PURCHASE = 'pending_purchase'; // بانتظار تأكيد المشتريات
    case READY_AT_WAREHOUSE = 'ready_at_warehouse'; // عند المستودع (طلبات جاهزة)
    case PENDING_LAB = 'pending_lab'; // بانتظار تقرير المخبر
    case APPROVED_LAB = 'approved_lab'; // تم قبولها من المخبر
    case REJECTED_LAB = 'rejected_lab'; // تم رفضها من المخبر
    case FINISHED = 'finished'; // منتهية
    case PAID = 'paid'; // منتهية


    public function label(): string
    {
        return match($this) {
            self::PENDING_ADMIN => 'بانتظار موافقة المدير',
            self::PENDING_PURCHASE => 'بانتظار تأكيد المشتريات',
            self::READY_AT_WAREHOUSE => 'عند المستودع (طلبات جاهزة)',
            self::PENDING_LAB => 'بانتظار تقرير المخبر',
            self::APPROVED_LAB => 'تم قبولها من المخبر',
            self::REJECTED_LAB => 'تم رفضها من المخبر',
            self::FINISHED => 'منتهية',
            self::PAID => 'مدفوعة',

        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDING_ADMIN => [self::PENDING_PURCHASE, self::REJECTED_LAB],
            self::PENDING_PURCHASE => [self::READY_AT_WAREHOUSE],
            self::READY_AT_WAREHOUSE => [self::PENDING_LAB],
            self::PENDING_LAB => [self::APPROVED_LAB, self::REJECTED_LAB],
            self::APPROVED_LAB => [self::FINISHED],
            self::REJECTED_LAB => [],
            self::FINISHED => [self::PAID],
            self::PAID => [],

        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    public static function getAvailableActions(string $role): array
    {
        return match($role) {
            'admin' => ['approve' => self::PENDING_PURCHASE],
            'sales' => ['update_prices' => self::READY_AT_WAREHOUSE],
            'warehouse' => ['confirm_receipt' => self::PENDING_LAB, 'final_confirm' => self::FINISHED],
            'tester' => ['approve' => self::APPROVED_LAB, 'reject' => self::REJECTED_LAB],
            'finance'=>['pay' => self::PAID],
            default => [],
        };
    }
}
