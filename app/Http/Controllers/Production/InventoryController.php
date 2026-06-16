<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Inventory\StoreInventoryRequest;
use App\Services\Production\Inventory\InventoryService;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX (list all inventories)
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $inventories = Inventory::query()
            ->with('items.item')
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($inventories);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE (create inventory / جرد)
    |--------------------------------------------------------------------------
    */
    public function store(StoreInventoryRequest $request)
    {
        $result = $this->inventoryService->createInventory(
            $request->validated()
        );

        return response()->json([
            'message' => 'Inventory created successfully',
            'data' => $result
        ]);
    }

    public function update(Request $request, $id)
    {
        $result = Inventory::query()->find($id)->update([
            'status' => $request['status'],
        ]);


        return response()->json([
            'message' => 'Inventory status updated successfully',
            'data' => Inventory::query()->find($id)->load('items.item')
        ]);
    }
}
