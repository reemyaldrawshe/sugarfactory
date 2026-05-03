<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function list($request, array $filters = [])
    {
        $query = User::with(['roles'])
            ->select(['id', 'name', 'email','gender', 'created_at', 'lang'])
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(isset($filters['gender']), fn($q) => $q->where('gender', $filters['gender']))
            ->when(isset($filters['role']), function ($q) use ($filters) {
                $q->whereHas('roles', fn($roleQ) => $roleQ->where('name', $filters['role']));
            })
            ->when(isset($filters['lang']), fn($q) => $q->where('lang', $filters['lang']))
            ->orderByDesc('id');

        $perPage = $filters['per_page'] ?? 15;
        $users = $query->paginate($perPage);

        // Format the collection
        $formatted = $users->getCollection()->map(function ($user) {
            $roleNames = $user->roles()->pluck('name')->implode(', ');
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roleNames,
                'gender' => $user->gender,
                'lang' => $user->lang,
                'join_date' => $user->created_at->format('Y-m-d'),
            ];
        });

        // Swap original collection with formatted
        $users->setCollection($formatted);

        return $users;
    }

    public function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'gender' => $data['gender'],
            'lang' => $data['lang'] ?? 'en',
            'password' => Hash::make($data['password']),
        ]);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }
        $roleNames = $user->roles()->pluck('name')->implode(', ');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roleNames,
            'gender' => $user->gender,
            'lang' => $user->lang,
            'join_date' => $user->created_at->format('Y-m-d'),
        ];
    }

    public function update(User $user, array $data)
    {
        $user->update(array_diff_key($data, ['password' => '', 'roles' => '']));

        // Update password if provided
        if (isset($data['password']) && $data['password']) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        // Sync roles if provided
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $roleNames = $user->roles()->pluck('name')->implode(', ');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roleNames,
            'gender' => $user->gender,
            'lang' => $user->lang,
            'join_date' => $user->created_at->format('Y-m-d'),
        ];
    }

    public function delete(User $user): bool
    {
        // Check if user is trying to delete themselves
        if (Auth::id() === $user->id) {
            throw new \Exception('You cannot delete your own account');
        }

        // Remove roles
        $user->roles()->detach();

        // Delete user
        return $user->delete();
    }

    public function show(User $user)
    {
        $roleNames = $user->roles()->pluck('name')->implode(', ');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roleNames,
            'gender' => $user->gender,
            'lang' => $user->lang,
            'join_date' => $user->created_at->format('Y-m-d'),
        ];
    }
}
