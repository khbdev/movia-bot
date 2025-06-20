<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AdminService;
use App\Telegram\Helpers\TelegramSender;

class DeleteChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $channelId;

    public function __construct(int $chatId, int $channelId)
    {
        $this->chatId = $chatId;
        $this->channelId = $channelId;
    }

    public function handle(): void
    {
        $adminService = new AdminService();
        $adminService->deleteChannel($this->channelId);

        TelegramSender::sendMessage($this->chatId, "✅ Канал успешно удален.");
    }
}