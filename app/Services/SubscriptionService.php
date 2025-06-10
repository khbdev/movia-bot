<?php

namespace App\Services;

use App\Repositories\ChannelRepository;
use App\Telegram\Helpers\TelegramChecker;

class SubscriptionService
{
    protected ChannelRepository $channelRepo;
    protected TelegramChecker $checker;

    public function __construct()
    {
        $this->channelRepo = new ChannelRepository();
        $this->checker = new TelegramChecker();
    }

    // Foydalanuvchining barcha kanallarga obuna ekanligini tekshirish
    public function checkUserSubscriptions(int $userId): bool
    {
        $channels = $this->channelRepo->getAllChannels();

        foreach ($channels as $channel) {
            if (!$this->checker->isUserSubscribed($channel->link, $userId)) {
                return false;
            }
        }

        return true;
    }

    // Barcha kanallarni olib, inline tugmalarni yaratish
    public function getSubscriptionButtons(): array
    {
        $buttons = [];
        $channels = $this->channelRepo->getAllChannels();

        foreach ($channels as $index => $channel) {
            $buttons[] = [[
                'text' => ($index + 1) . '-kanal',
                'url' => $channel->link
            ]];
        }

        // Tekshirish tugmasi
        $buttons[] = [[
            'text' => '✅ Tekshirish',
            'callback_data' => 'check_subscription'
        ]];

        return $buttons;
    }
}