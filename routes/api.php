<?php

use App\Http\Controllers\Admin\BOMController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Controllers\Warehouse\ItemController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Warehouse\AuthController as WarehouseAuthController;
use App\Http\Controllers\Sales\AuthController as SalesAuthController;
use App\Http\Controllers\Finance\AuthController as FinanceAuthController;
use App\Http\Controllers\Tester\AuthController as TesterAuthController;
use App\Http\Controllers\Admin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Warehouse\ShipmentController as WarehouseShipmentController;
use App\Http\Controllers\Sales\ShipmentController as SalesShipmentController;
use App\Http\Controllers\Tester\ShipmentController as TesterShipmentController;
use App\Http\Controllers\Finance\ShipmentController as FinanceShipmentController;
use App\Http\Controllers\Production\ProductionOrderController as ProductionController;
use App\Http\Controllers\Production\AuthController as ProductionAuthController;

Route::prefix('admin')
    ->controller(AdminAuthController::class)
    ->middleware(SetLocaleMiddleware::class)
    ->group(function (){
        Route::post('login', 'login');
    });


Route::prefix('warehouse')
    ->controller(WarehouseAuthController::class)
    ->middleware(SetLocaleMiddleware::class)
    ->group(function (){
        Route::post('login', 'login');
    });

Route::prefix('sales')
    ->controller(SalesAuthController::class)
    ->middleware(SetLocaleMiddleware::class)
    ->group(function (){
        Route::post('login', 'login');
    });

Route::prefix('finance')
    ->controller(FinanceAuthController::class)
    ->middleware(SetLocaleMiddleware::class)
    ->group(function (){
        Route::post('login', 'login');
    });

Route::prefix('tester')
    ->controller(TesterAuthController::class)
    ->middleware(SetLocaleMiddleware::class)
    ->group(function (){
        Route::post('login', 'login');
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {
        Route::controller(AdminAuthController::class)
            ->group(function () {
                Route::post('logout', 'logout')
                    ->name('admin.logout')
                    ->middleware('can:admin.logout');
            });

        Route::controller(UserController::class)
            ->prefix('users')
            ->group(function () {
                // Store user (Create)
                Route::post('/', 'store')
                    ->name('user.store')
                    ->middleware('can:user.store');

                // Update user details
                Route::post('/{user}', 'update')
                    ->name('user.update')
                    ->middleware('can:user.update');

                // Delete a user
                Route::delete('/{user}', 'destroy')
                    ->name('user.destroy')
                    ->middleware('can:user.destroy');

                // Show a user's details
                Route::get('/{user}', 'show')
                    ->name('user.show')
                    ->middleware('can:user.show');

                // List all users
                Route::get('/', 'index')
                    ->name('user.index')
                    ->middleware('can:user.index');
            });


        Route::controller(RolePermissionController::class)
            ->prefix('roles-permissions')
            ->group(function () {
                // List all roles with permissions
                Route::get('/', 'index')
                    ->name('role_permission.index')
                    ->middleware('can:role_permission.index');

                // Get all permissions
                Route::get('/permissions', 'getPermissions')
                    ->name('role_permission.permissions')
                    ->middleware('can:role_permission.permissions');

                // Create new role
                Route::post('/', 'store')
                    ->name('role_permission.store')
                    ->middleware('can:role_permission.store');

                // Update role
                Route::post('/{role}', 'update')
                    ->name('role_permission.update')
                    ->middleware('can:role_permission.update');

                // Delete role
                Route::delete('/{role}', 'destroy')
                    ->name('role_permission.destroy')
                    ->middleware('can:role_permission.destroy');

                // Check if role can be deleted
                Route::get('/{role}/can-delete', 'canDelete')
                    ->name('role_permission.can_delete')
                    ->middleware('can:role_permission.can_delete');

                // Get roles statistics
                Route::get('/statistics', 'statistics')
                    ->name('role_permission.statistics')
                    ->middleware('can:role_permission.statistics');
            });

        Route::controller(SectionController::class)
            ->prefix('sections')
            ->group(function () {
                // Store section (Create)
                Route::post('/', 'store')
                    ->name('section.store')
                    ->middleware('can:section.store');

                // Update section details
                Route::post('/{section}', 'update')
                    ->name('section.update')
                    ->middleware('can:section.update');

                // Delete a section
                Route::delete('/{section}', 'destroy')
                    ->name('section.destroy')
                    ->middleware('can:section.destroy');

                // Show a section's details
                Route::get('/{section}', 'show')
                    ->name('section.show')
                    ->middleware('can:section.show');

                // List all sections
                Route::get('/', 'index')
                    ->name('section.index')
                    ->middleware('can:section.index');
            });


        Route::controller(BOMController::class)
            ->prefix('boms')
            ->group(function () {
                // Store or update bom
                Route::post('/', 'store')
                    ->name('bom.store')
                    ->middleware('can:bom.store');

                // Delete a bom
                Route::delete('/{bom}', 'destroy')
                    ->name('bom.destroy')
                    ->middleware('can:bom.destroy');

            });

        Route::controller(AdminShipmentController::class)
            ->prefix('shipments')
            ->group(function () {
                Route::get('', [AdminShipmentController::class, 'index']);
                Route::post('/{id}/approve', [AdminShipmentController::class, 'approve'])
                    ->middleware('can:shipment.admin.approve');
            });
    });

Route::prefix('warehouse')
    ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {

        Route::controller(AdminAuthController::class)
            ->group(function () {
                Route::post('logout', 'logout')
                    ->name('warehouse.logout')
                    ->middleware('can:warehouse.logout');

            });

        Route::controller(ItemController::class)
            ->prefix('items')
            ->group(function () {

                Route::post('/', 'store')
                    ->name('item.store')
                   ->middleware('can:item.store');

                Route::get('/', 'index')
                    ->name('item.index')
                    ->middleware('can:item.index');

                Route::get('/{item}', 'show')
                    ->name('item.show')
                    ->middleware('can:item.show');

                Route::post('/{item}', 'update')
                    ->name('item.update')
                    ->middleware('can:item.update');

                Route::delete('/{item}', 'destroy')
                    ->name('item.destroy')
                    ->middleware('can:item.destroy');

            });

        // 🚚 Shipments
        Route::controller(WarehouseShipmentController::class)
            ->prefix('shipments')
            ->group(function () {

                Route::apiResource('', WarehouseShipmentController::class)->only(['index', 'store', 'show']);

                Route::post('/{id}/confirm-receipt', [WarehouseShipmentController::class, 'confirmReceipt'])
                    ->middleware('can:shipment.warehouse.confirm_receipt');

                Route::post('/{id}/send-to-lab', [WarehouseShipmentController::class, 'sendToLab'])
                    ->middleware('can:shipment.warehouse.send_to_lab');

                Route::post('/{id}/final-confirm', [WarehouseShipmentController::class, 'finalConfirm'])
                    ->middleware('can:shipment.warehouse.confirm_final');
            });
    });

Route::prefix('tester')
    ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {

        Route::controller(TesterAuthController::class)
            ->group(function () {
                Route::post('logout', 'logout')
                    ->name('tester.logout')
                    ->middleware('can:tester.logout');

            });

        Route::controller(TesterShipmentController::class)
            ->prefix('shipments')
            ->group(function () {

                Route::get('', [TesterShipmentController::class, 'index']);
                Route::post('/result', [TesterShipmentController::class, 'uploadResult'])
                    ->middleware('can:shipment.tester.upload_result');
                Route::post('/{id}/approve', [TesterShipmentController::class, 'approve'])
                    ->middleware('can:shipment.tester.approve');
                Route::post('/{id}/reject', [TesterShipmentController::class, 'reject'])
                    ->middleware('can:shipment.tester.reject');
            });

    });

Route::prefix('finance')
    ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {

        Route::controller(FinanceAuthController::class)
            ->group(function () {
                Route::post('logout', 'logout')
                    ->name('finance.logout')
                    ->middleware('can:finance.logout');

            });
        Route::controller(FinanceShipmentController::class)
            ->prefix('shipments')
            ->group(function () {
                Route::get('shipments', [FinanceShipmentController::class, 'index'])
                    ->middleware('can:shipment.finance.view');
            });
    });

Route::prefix('sales')
    ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {

        Route::controller(SalesAuthController::class)
            ->group(function () {
                Route::post('logout', 'logout')
                    ->name('sales.logout')
                    ->middleware('can:sales.logout');

            });

        Route::controller(SalesShipmentController::class)
            ->prefix('shipments')
            ->group(function () {

                Route::get('', [SalesShipmentController::class, 'index']);
                Route::post('', [SalesShipmentController::class, 'update'])
                    ->middleware('can:shipment.sales.update');
            });
    });



    Route::post('/login', [ProductionAuthController::class, 'login']);
    
Route::prefix('production')
     ->middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->group(function () {

         Route::post('/logout', [ProductionAuthController::class, 'logout'])
            ->middleware('permission:production.logout');

            
             Route::post('/orders/{id}/approve', [ProductionController::class, 'managerDecision'])
            ->middleware('permission:production.manager.approve');


        Route::post('/orders', [ProductionController::class, 'store'])
            ->middleware('permission:production.create');

            Route::post('/orders/{id}/warehouse-approve', 
    [ProductionController::class, 'warehouseApprove']
)->middleware('permission:production.order.warehouse.approve');

Route::post('/orders/{id}/start', [ProductionController::class, 'start'])
    ->middleware('permission:production.order.start');

    Route::post('/orders/{id}/pause', [ProductionController::class, 'pause'])
    ->middleware('permission:production.order.pause');


    Route::get('/orders/{id}/preview', [ProductionController::class, 'preview'])
    ->middleware('permission:production.order.view');

Route::post('/orders/{id}/resume', [ProductionController::class, 'resume'])
    ->middleware('permission:production.order.resume');

Route::post('/orders/{id}/complete', [ProductionController::class, 'complete'])
    ->middleware('permission:production.order.finish');

   Route::get(
    'production-orders/material-requests',
    [ProductionController::class, 'materialRequests']
)->middleware('permission:production.material.requests.view');


Route::post(
    'production-orders/{id}/send-to-production',
    [ProductionController::class, 'sendToProduction']  
)->middleware('permission:production.order.warehouse.approve');

Route::get(
    'production-orders',
    [ProductionController::class,
    'allProductionOrders']
);

    });
