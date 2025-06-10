<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\UserService;
use App\Telegram\Helpers\TelegramSender;

class SendBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function handle(): void
    {
        $userService = new UserService();
        $users = $userService->getAllUsers();

        foreach ($users as $user) {
            if ($user->telegram_id) {
                TelegramSender::sendMessage((int)$user->telegram_id, $this->message);
            }
        }
    }
}