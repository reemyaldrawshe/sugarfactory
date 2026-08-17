<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * تحديث FCM Token للمستخدم المسجل دخوله
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث FCM Token بنجاح',
            'data' => [
                'user_id' => $request->user()->id,
                'fcm_token' => $request->user()->fcm_token,
            ]
        ], 200);
    }
}