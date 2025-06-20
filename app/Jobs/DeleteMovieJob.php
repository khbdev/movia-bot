<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AdminService;
use App\Telegram\Helpers\TelegramSender;

class DeleteMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $movieId;

    public function __construct(int $chatId, int $movieId)
    {
        $this->chatId = $chatId;
        $this->movieId = $movieId;
    }

    public function handle(): void
    {
        $adminService = new AdminService();
        $adminService->deleteMovie($this->movieId);

        TelegramSender::sendMessage($this->chatId, "✅ Фильм успешно удален.");
    }
}