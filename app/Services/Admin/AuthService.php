<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
   public function login(array $data)
{
    $user = User::query()->where('email', $data['email'])->firstOrFail();

    if (!Hash::check($data['password'], $user->password)) {
        return 400;
    }

    $token = $user->createToken('api_token')->plainTextToken;

    return [
        'user' => $user,
        'roles' => $user->getRoleNames(), // 🔥 مهم
        'token' => $token,
    ];
}

    public function logout(){
        return auth()->user()->currentAccessToken()->delete();
    }

}
