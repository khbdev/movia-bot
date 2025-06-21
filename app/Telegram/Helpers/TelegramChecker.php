<?php

namespace App\Telegram\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChecker
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');

        if (!$this->botToken) {
            Log::error("Telegram bot token topilmadi.");
            throw new \Exception("Telegram bot token topilmadi.");
        }
    }

    /**
     * Foydalanuvchining kanalga obuna ekanligini tekshirish
     */
    public function isUserSubscribed(string $channelLinkOrId, int $userId): bool
    {
        // Chat ID olish
        $chatId = $this->extractChatId($channelLinkOrId);

        if (!$chatId) {
            Log::warning("Noto'g'ri kanal havolasi yoki chat_id: " . $channelLinkOrId);
            return false;
        }

        // getChatMember API so'rovi
        $url = "https://api.telegram.org/bot{$this->botToken}/getChatMember"; 

        try {
            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['result']['status'])) {
                    $status = $data['result']['status'];
                    $validStatuses = ['member', 'administrator', 'creator'];
                    return in_array($status, $validStatuses);
                }

                Log::info("Foydalanuvchi holati aniqlanmadi: " . json_encode($data));
                return false;
            }

            // Xato bo'lsa logga yozish
            $error = $response->json();
            Log::error("Telegram API xatosi: " . json_encode($error));

            return false;
        } catch (\Exception $e) {
            Log::error("So'rovda xato yuz berdi: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kanaldan chat_id ni ajratib olish
     */
    private function extractChatId(string $input): string|false
    {
        // Agar link bo'lsa, username ni olish
        if (str_contains($input, 't.me/')) {
            $parts = explode('/', $input);
            $username = end($parts);
            return '@' . $username;
        }

        // To'g'ridan-to'g'ri chat_id (masalan: -100123456789)
        if (preg_match('/^-?\d+$/', $input)) {
            return $input;
        }

        return false;
    }
}