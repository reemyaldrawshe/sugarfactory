<?php

namespace App\Services\Admin;

use App\Models\BOM;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BOMService
{
    
// public function updateOrCreate(array $data)
    // {
    //     return DB::transaction(function () use ($data) {

    //         $finalItemId = $data['final_item_id'];

    //         // existing basic item ids in DB
    //         $existingIds = BOM::where('final_item_id', $finalItemId)
    //             ->pluck('basic_item_id')
    //             ->toArray();

    //         $incomingIds = collect($data['items'])
    //             ->pluck('basic_item_id')
    //             ->toArray();

    //         /*
    //         |--------------------------------------------------------------------------
    //         | 1. Delete removed items
    //         |--------------------------------------------------------------------------
    //         */
    //         $toDelete = array_diff($existingIds, $incomingIds);

    //         if (!empty($toDelete)) {
    //             BOM::where('final_item_id', $finalItemId)
    //                 ->whereIn('basic_item_id', $toDelete)
    //                 ->delete();
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | 2. Update or Create
    //         |--------------------------------------------------------------------------
    //         */
    //         foreach ($data['items'] as $item) {

    //             BOM::updateOrCreate(
    //                 [
    //                     'final_item_id' => $finalItemId,
    //                     'basic_item_id' => $item['basic_item_id'],
    //                 ],
    //                 [
    //                     'basic_item_quantity' => $item['basic_item_quantity'],
    //                 ]
    //             );
    //         }

    //   return Item::find($finalItemId);
    //     });
    // }

    public function updateOrCreate(array $data)
    {
        return DB::transaction(function () use ($data) {

            $finalItemId = $data['final_item_id'];
            $finalItemQuantity = $data['final_item_quantity']; // الكمية الناتجة

            $existingIds = BOM::where('final_item_id', $finalItemId)
                ->pluck('basic_item_id')
                ->toArray();

            $incomingIds = collect($data['items'])
                ->pluck('basic_item_id')
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | 1. Delete removed items
            |--------------------------------------------------------------------------
            */
            $toDelete = array_diff($existingIds, $incomingIds);

            if (!empty($toDelete)) {
                BOM::where('final_item_id', $finalItemId)
                    ->whereIn('basic_item_id', $toDelete)
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Update or Create
            |--------------------------------------------------------------------------
            */
            // هنا الإضافة المهمة: نقوم بتصفير جميع المواد لتصبح غير أساسية أولاً
            // كإجراء أمان إضافي في قاعدة البيانات (حتى لو أرسل المستخدم مادة أساسية جديدة وتغيرت القديمة)
            BOM::where('final_item_id', $finalItemId)->update(['is_primary' => false]);

            foreach ($data['items'] as $item) {
                
                $isPrimary = filter_var($item['is_primary'], FILTER_VALIDATE_BOOLEAN);

                BOM::updateOrCreate(
                    [
                        'final_item_id' => $finalItemId,
                        'basic_item_id' => $item['basic_item_id'],
                    ],
                    [
                        'basic_item_quantity' => $item['basic_item_quantity'],
                        'final_item_quantity' => $finalItemQuantity, // حفظ الكمية المنتجة المتوقعة
                        'is_primary' => $isPrimary, // حفظ هل هي أساسية أم لا
                    ]
                );
            }

            return Item::find($finalItemId);
        });
    }
    public function delete($bom): bool
    {
        // 1. التحقق إذا كانت المادة المراد حذفها هي الأساسية
    if ($bom->is_primary) {
        
        // 2. نتحقق إذا في مواد ثانوية أخرى متعلقة بنفس المنتج النهائي (final_item_id)
        $hasOtherItems = BOM::where('final_item_id', $bom->final_item_id)
            ->where('id', '!=', $bom->id)
            ->exists();

        // 3. إذا في مواد تانية، نمنع الحذف ونرمي استثناء (Exception)
        if ($hasOtherItems) {
            throw ValidationException::withMessages([
                'bom' => 'لا يمكن حذف المادة الأساسية طالما أن هناك مواد ثانوية أخرى في القائمة. يرجى تعيين مادة أخرى كـ أساسية أولاً أو حذف المواد الثانوية.'
            ]);
        }
    }
        return $bom->delete();
    }
}
