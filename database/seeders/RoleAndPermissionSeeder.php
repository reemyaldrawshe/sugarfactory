<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $warehouseRole = Role::query()->firstOrCreate(['name' => 'warehouse']);
        $testerRole = Role::query()->firstOrCreate(['name' => 'tester']);
        $financeRole = Role::query()->firstOrCreate(['name' => 'finance']);
        $salesRole = Role::query()->firstOrCreate(['name' => 'sales']);

        // Define permissions
        $permissions = [
            'admin.logout',
            'admin.show', 'admin.update', 'admin.delete',
            'warehouse.logout',
            'tester.logout',
            'finance.logout',
            'sales.logout',
            'item.store', 'item.update','item.destroy','item.index','item.show',
            'role_permission.store', 'role_permission.update', 'role_permission.destroy', 'role_permission.show', 'role_permission.index',
            'role_permission.permissions', 'role_permission.permissions_grouped', 'role_permission.assign_permissions', 'role_permission.remove_permissions',
            'role_permission.available_permissions', 'role_permission.can_delete', 'role_permission.statistics',

            'user.store', 'user.update', 'user.destroy', 'user.show', 'user.index',

            'section.store', 'section.update', 'section.destroy', 'section.show', 'section.index',

            'bom.store', 'bom.destroy',

            'shipment.index',
            'shipment.show',

            // admin
            'shipment.admin.approve',

            // warehouse
            'shipment.warehouse.create',
            'shipment.warehouse.confirm_receipt',
            'shipment.warehouse.send_to_lab',
            'shipment.warehouse.confirm_final',

            // purchase
            'shipment.purchase.view',
            'shipment.sales.update',
            'shipment.purchase.mark_received',

            // tester (lab)
            'shipment.tester.view',
            'shipment.tester.upload_result',
            'shipment.tester.approve',
            'shipment.tester.reject',

            // finance
            'shipment.finance.view',

        ];
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign permissions to roles
        $adminRole->syncPermissions([
            'admin.logout',
            'admin.show', 'admin.update', 'admin.delete',// Auth
            'user.store', 'user.update', 'user.destroy', 'user.show', 'user.index',

            'role_permission.store', 'role_permission.update', 'role_permission.destroy', 'role_permission.show', 'role_permission.index',
            'role_permission.permissions', 'role_permission.permissions_grouped', 'role_permission.assign_permissions', 'role_permission.remove_permissions',
            'role_permission.available_permissions', 'role_permission.can_delete', 'role_permission.statistics',

            'section.store', 'section.update', 'section.destroy', 'section.show', 'section.index',

            'item.store', 'item.update','item.destroy','item.index','item.show',

            'bom.store', 'bom.destroy',

            'shipment.index',
            'shipment.show',
            'shipment.admin.approve',


        ]);

        $warehouseRole->syncPermissions([
            'warehouse.logout',
            'item.store', 'item.update', 'item.destroy', 'item.index', 'item.show',
            'shipment.index',
            'shipment.show',
            'shipment.warehouse.create',
            'shipment.warehouse.confirm_receipt',
            'shipment.warehouse.send_to_lab',
            'shipment.warehouse.confirm_final',
        ]);

        $testerRole->syncPermissions([
            'tester.logout',
            'shipment.index',
            'shipment.show',
            'shipment.tester.view',
            'shipment.tester.upload_result',
            'shipment.tester.approve',
            'shipment.tester.reject',
        ]);

        $financeRole->syncPermissions([
            'finance.logout',
            'shipment.index',
            'shipment.show',
            'shipment.finance.view',
        ]);

        $salesRole->syncPermissions([
            'sales.logout',
            'shipment.index',
            'shipment.show',
            'shipment.purchase.view',
            'shipment.sales.update',
            'shipment.purchase.mark_received',

        ]);
        /********************************************************************************/

        // Create users and assign roles
        $adminUser = User::query()->create([
            'name' => 'admin abo admin',
            'email' => 'admin@sugarfactory.com',
            'password' => bcrypt('admin'),
            'lang' => 'ar',
        ]);
        $adminUser->assignRole($adminRole);
        $permissions = $adminRole->permissions()->pluck('name')->toArray();
        $adminUser->givePermissionTo($permissions);

        /********************************************************************************/

        $warehouseUser = User::query()->create([
            'name' => 'Warehouse Mo',
            'email' => 'warehouse@sugarfactory.com',
            'lang' => 'ar',
            'gender' => 'female',
            'password' => bcrypt('warehouse'),
        ]);
        try {
            $media = $warehouseUser->addMedia(public_path('/seeder/default_'.$warehouseUser['gender'].'_profile.jpg'))
                ->preservingOriginal()
                ->toMediaCollection('user');
            $warehouseUser['profile_photo'] = $media->getUrl();
            $warehouseUser->save();
        } catch (FileDoesNotExist $e) {
            Log::warning('file does not exist: ' . $e->getMessage());
            Log::error($e);
        } catch (FileIsTooBig $e) {
            Log::warning('file is too big: ' . $e->getMessage());
            Log::error($e);
        }

        $warehouseUser->assignRole($warehouseRole);
        $permissions = $warehouseRole->permissions()->pluck('name')->toArray();
        $warehouseUser->givePermissionTo($permissions);

        /********************************************************************************/

        $testerUser = User::query()->create([
            'name' => 'Tester Mo',
            'email' => 'tester@sugarfactory.com',
            'lang' => 'ar',
            'gender' => 'female',
            'password' => bcrypt('tester'),
        ]);
        try {
            $media = $testerUser->addMedia(public_path('/seeder/default_'.$testerUser['gender'].'_profile.jpg'))
                ->preservingOriginal()
                ->toMediaCollection('user');
            $testerUser['profile_photo'] = $media->getUrl();
            $testerUser->save();
        } catch (FileDoesNotExist $e) {
            Log::warning('file does not exist: ' . $e->getMessage());
            Log::error($e);
        } catch (FileIsTooBig $e) {
            Log::warning('file is too big: ' . $e->getMessage());
            Log::error($e);
        }

        $testerUser->assignRole($testerRole);
        $permissions = $testerRole->permissions()->pluck('name')->toArray();
        $testerUser->givePermissionTo($permissions);

        /********************************************************************************/

        $financeUser = User::query()->create([
            'name' => 'finance Mo',
            'email' => 'finance@sugarfactory.com',
            'lang' => 'ar',
            'gender' => 'female',
            'password' => bcrypt('finance'),
        ]);
        try {
            $media = $financeUser->addMedia(public_path('/seeder/default_'.$financeUser['gender'].'_profile.jpg'))
                ->preservingOriginal()
                ->toMediaCollection('user');
            $financeUser['profile_photo'] = $media->getUrl();
            $financeUser->save();
        } catch (FileDoesNotExist $e) {
            Log::warning('file does not exist: ' . $e->getMessage());
            Log::error($e);
        } catch (FileIsTooBig $e) {
            Log::warning('file is too big: ' . $e->getMessage());
            Log::error($e);
        }

        $financeUser->assignRole($financeRole);
        $permissions = $financeRole->permissions()->pluck('name')->toArray();
        $financeUser->givePermissionTo($permissions);

        /********************************************************************************/

        $salesUser = User::query()->create([
            'name' => 'sales Mo',
            'email' => 'sales@sugarfactory.com',
            'lang' => 'ar',
            'gender' => 'female',
            'password' => bcrypt('sales'),
        ]);
        try {
            $media = $salesUser->addMedia(public_path('/seeder/default_'.$salesUser['gender'].'_profile.jpg'))
                ->preservingOriginal()
                ->toMediaCollection('user');
            $salesUser['profile_photo'] = $media->getUrl();
            $salesUser->save();
        } catch (FileDoesNotExist $e) {
            Log::warning('file does not exist: ' . $e->getMessage());
            Log::error($e);
        } catch (FileIsTooBig $e) {
            Log::warning('file is too big: ' . $e->getMessage());
            Log::error($e);
        }

        $salesUser->assignRole($salesRole);
        $permissions = $salesRole->permissions()->pluck('name')->toArray();
        $salesUser->givePermissionTo($permissions);


    }


}
