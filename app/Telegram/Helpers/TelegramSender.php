<?php

namespace App\Telegram\Helpers;

use Telegram\Bot\Api;

class TelegramSender
{
    protected static ?Api $telegram = null;

    protected static function getTelegram(): Api
    {
        if (self::$telegram === null) {
            self::$telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        }
        return self::$telegram;
    }

    /**
     * Oddiy xabar yuborish
     */
    public static function sendMessage(int $chatId, string $text, array $keyboard = null, string $parseMode = null, array $inlineKeyboard = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        if ($inlineKeyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        } elseif ($keyboard) {
            $params['reply_markup'] = json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]);
        }

        self::getTelegram()->sendMessage($params);
    }

    /**
     * Postni forward qilish
     */
    public static function forwardMessage(int $toChatId, int $fromChatId, int $messageId): void
    {
        self::getTelegram()->forwardMessage([
            'chat_id' => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ]);
    }
}