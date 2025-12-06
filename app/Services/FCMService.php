<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FCMService
{
    protected $messaging;

    public function __construct()
    {
        // This initializes Firebase using the JSON file in your .env
        $factory = (new Factory)->withServiceAccount(env('FIREBASE_CREDENTIALS'));
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send a notification to a specific user device.
     * 
     * @param string $deviceToken (From users table)
     * @param string $title
     * @param string $body
     * @param array $data (Optional custom data)
     */
    public function sendToUser($deviceToken, $title, $body, $data = [])
    {
        if (!$deviceToken) {
            return false; // User has no token
        }

        $notification = Notification::create($title, $body);

        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification($notification)
            ->withData($data);

        try {
            $this->messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            // Log error if needed: \Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * Send to multiple users (Bulk).
     */
    public function sendToMultiple(array $deviceTokens, $title, $body)
    {
        $notification = Notification::create($title, $body);
        $message = CloudMessage::new()->withNotification($notification);

        try {
            $this->messaging->sendMulticast($message, $deviceTokens);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
