<?php

namespace App\Services\Admin;

use App\Models\BOM;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class BOMService
{
    public function updateOrCreate(array $data)
    {
        return DB::transaction(function () use ($data) {

            $finalItemId = $data['final_item_id'];

            // existing basic item ids in DB
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
            foreach ($data['items'] as $item) {

                BOM::updateOrCreate(
                    [
                        'final_item_id' => $finalItemId,
                        'basic_item_id' => $item['basic_item_id'],
                    ],
                    [
                        'basic_item_quantity' => $item['basic_item_quantity'],
                    ]
                );
            }

            return Item::query()->find($finalItemId);
        });
    }
    public function delete($bom): bool
    {
        return $bom->delete();
    }
}
