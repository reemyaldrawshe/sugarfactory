<?php

namespace App\Services;

use App\Models\Notification as NotificationModel;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationService
{
    public function index()
    {
        return auth()->user()->notifications;
    }

    public function send($user, string $title, string $message, string $type = 'basic', array $extraData = [])
{
    $userId = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
    $fcmToken = is_array($user) ? ($user['fcm_token'] ?? null) : ($user->fcm_token ?? null);

    if (!$userId) {
        Log::error("NotificationService: User ID is missing.");
        return 0;
    }

    $notificationRecord = null;

    // 1. حفظ الإشعار لتقوم قاعدة البيانات بتوليد الرقم التلقائي (1, 2, 3...)
    try {
        $notificationRecord = NotificationModel::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'data'    => $extraData,
            'is_read' => 0,
        ]);
    } catch (\Throwable $e) {
        Log::error("Failed to save notification to database: " . $e->getMessage());
        return 0;
    }

    // 2. إرسال Firebase Push Notification باستخدام رقم الإشعار المحفوظ
    if (!empty($fcmToken)) {
        try {
            $serviceAccountPath = storage_path('app/Notification.json');
            if (!file_exists($serviceAccountPath)) {
                $serviceAccountPath = app_path('Notification.json');
            }

            if (file_exists($serviceAccountPath)) {
                $factory = (new Factory)->withServiceAccount($serviceAccountPath);
                $messaging = $factory->createMessaging();

                $notificationPayload = [
                    'title' => $title,
                    'body'  => $message,
                    'sound' => 'default',
                ];

                // نمرر رقم الإشعار التلقائي ورقم المستخدم
                $dataPayload = array_merge([
                    'id'              => (string) $notificationRecord->id, // رقم الإشعار (مثل "10")
                    'user_id'         => (string) $userId,                 // رقم المستخدم (مثل "1")
                    'type'            => $type,
                    'title'           => $title,
                    'message'         => $message,
                ], array_map('strval', $extraData));

                $cloudMessage = CloudMessage::withTarget('token', $fcmToken)
                    ->withNotification($notificationPayload)
                    ->withData($dataPayload);

                $messaging->send($cloudMessage);
            }
        } catch (\Throwable $e) {
            Log::error("Firebase Notification Error: " . $e->getMessage());
        }
    }

    return 1;
}

    public function markAsRead($notificationId): bool
    {
        $notification = auth()->user()->notifications()->find($notificationId);

        if ($notification) {
            $notification->update(['is_read' => 1]);
            return true;
        }
        return false;
    }

    public function destroy($id): bool
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification) {
            $notification->delete();
            return true;
        }
        return false;
    }
}