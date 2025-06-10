<?php

namespace App\Telegram\Helpers;

use Illuminate\Support\Facades\Http;

class TelegramChecker
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
    }

    // Foydalanuvchining kanalga obuna ekanligini tekshirish
    public function isUserSubscribed(string $channelLink, int $userId): bool
    {
        // Kanaldan chat_id olish (masalan: @kanalnomi yoki -100123456789)
        $chatId = $this->extractChatId($channelLink);

        if (!$chatId) return false;

        $url = "https://api.telegram.org/bot{$this->botToken}/getChatMember"; 

        try {
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['result']['status'] ?? null;
                return in_array($status, ['member', 'administrator', 'creator']);
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    // Kanal linkidan chat_id olish
    private function extractChatId(string $channelLink): string|false
    {
        // Agar username bo'lsa: https://t.me/kanalnomi 
        if (str_contains($channelLink, 't.me/')) {
            $parts = explode('/', $channelLink);
            $username = end($parts);
            return '@' . $username;
        }

        // To'g'ridan-to'g'ri chat_id (masalan: -100123456789)
        if (is_numeric($channelLink)) {
            return $channelLink;
        }

        return false;
    }
}