<?php

namespace App\Jobs;

use App\Telegram\TelegramCommandRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $update;

    /**
     * Create a new job instance.
     */
    public function __construct(array $update)
    {
        $this->update = $update;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $router = new TelegramCommandRouter($this->update);
        $router->dispatch(); // Keyingi bosqichga yo‘naltiradi
    }
}
