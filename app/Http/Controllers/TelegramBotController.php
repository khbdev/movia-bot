<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;
use App\Services\UserService;
use App\Services\SubscriptionService;
use App\Services\MovieService;
use App\Telegram\Helpers\TelegramSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\data_get;

class TelegramBotController extends Controller
{
    private $adminService;
    private $userService;  
    private $subscriptionService;
    private $movieService;
    
    public function __construct()
    {
        $this->adminService = new AdminService();
        $this->userService = new UserService();
        $this->subscriptionService = new SubscriptionService();
        $this->movieService = new MovieService();
    }

    public function handle(Request $request)
    {
        $update = $request->all();

        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        $message = data_get($update, 'message');
        if (!$message) return response()->json(['ok' => true]);

        return $this->handleMessage($message);
    }

    private function handleCallback($callback)
    {
        $data = data_get($callback, 'data', '');
        $chatId = data_get($callback, 'message.chat.id');
        $callbackId = data_get($callback, 'id');

        if (str_starts_with($data, 'delete_channel_')) {
            $channelId = (int) str_replace('delete_channel_', '', $data);
            $this->adminService->deleteChannel($channelId);
            TelegramSender::sendMessage($chatId, "Канал удален!");
        } elseif (str_starts_with($data, 'delete_movie_')) {
            $movieId = (int) str_replace('delete_movie_', '', $data);
            $this->adminService->deleteMovie($movieId);
            TelegramSender::sendMessage($chatId, "Фильм удален!");
        } elseif ($data === 'check_subscription') {
            $isSubscribed = $this->subscriptionService->checkUserSubscriptions($chatId);
            $message = $isSubscribed 
                ? "✅ Вы подписаны на все каналы. Теперь введите код фильма."
                : "❌ Вы еще не подписаны на некоторые каналы.";
            TelegramSender::sendMessage($chatId, $message);
        }

        self::getTelegram()->answerCallbackQuery([
            'callback_query_id' => $callbackId,
            'text' => 'Готово',
            'show_alert' => false,
        ]);

        return response()->json(['ok' => true]);
    }

    private function handleMessage($message)
    {
        $text = data_get($message, 'text', '');
        $chatId = data_get($message, 'chat.id');

        $user = $this->userService->createOrGetUser([
            'telegram_id' => $chatId,
            'username' => data_get($message, 'from.username'),
            'first_name' => data_get($message, 'from.first_name'),
            'last_name' => data_get($message, 'from.last_name'),
        ]);

        if (str_starts_with($text, '/start')) {
            return $this->handleStart($user, $chatId);
        }

        if ($user->role !== 'admin') {
            return $this->handleUserMessage($text, $chatId);
        }

        return $this->handleAdminMessage($text, $chatId, $message);
    }

    private function handleStart($user, $chatId)
    {
        if ($user->role === 'admin') {
            $keyboard = [['Пользователи', 'Каналы'], ['Фильмы', 'Сообщение']];
            TelegramSender::sendMessage($chatId, "👑 Здравствуйте, администратор {$user->first_name}!", $keyboard);
        } else {
            if ($this->subscriptionService->checkUserSubscriptions($chatId)) {
                TelegramSender::sendMessage($chatId, "🤖 Здравствуйте, {$user->first_name}! Вы подписаны. Введите код фильма.");
            } else {
                $buttons = $this->subscriptionService->getSubscriptionButtons();
                TelegramSender::sendMessage($chatId, "⚠️ Пожалуйста, подпишитесь на каналы:", null, null, $buttons);
            }
        }
        return response()->json(['ok' => true]);
    }

    private function handleUserMessage($text, $chatId)
    {
        if (!preg_match('/^[A-Z0-9_]+$/', $text)) {
            TelegramSender::sendMessage($chatId, "⚠️ Используйте только команду /start.");
            return response()->json(['ok' => true]);
        }

        // Проверка cache для фильма
        $cacheKey = "movie_data:{$text}";
        $movieData = Cache::get($cacheKey);

        if (!$movieData) {
            // Проверка подписки только если фильм не в cache
            if (!$this->subscriptionService->checkUserSubscriptions($chatId)) {
                TelegramSender::sendMessage($chatId, "❌ Вы не подписаны на все каналы.");
                $buttons = $this->subscriptionService->getSubscriptionButtons();
                TelegramSender::sendMessage($chatId, "⚠️ Подпишитесь на следующие каналы:", null, null, $buttons);
                return response()->json(['ok' => true]);
            }

            // Поиск фильма в БД
            $rawPost = $this->movieService->findMovieByCode($text);
            if (!$rawPost || !isset($rawPost['message_id'], $rawPost['chat']['id'])) {
                TelegramSender::sendMessage($chatId, "❌ Код фильма не найден.");
                return response()->json(['ok' => true]);
            }

            // Сохранение в cache на 24 часа
            $movieData = [
                'chat_id' => $rawPost['chat']['id'],
                'message_id' => $rawPost['message_id']
            ];
            Cache::put($cacheKey, $movieData, 1440); // 24 часа
        }

        // Отправка фильма из cache
        TelegramSender::forwardMessage($chatId, $movieData['chat_id'], $movieData['message_id']);
        return response()->json(['ok' => true]);
    }

    private function handleAdminMessage($text, $chatId, $message)
    {
        switch ($text) {
            case 'Пользователи':
                $this->showUsers($chatId);
                break;

            case 'Каналы':
                TelegramSender::sendMessage($chatId, 'Раздел каналов – выберите действие:', [
                    ['Добавить канал', 'Список каналов'], ['❌ Назад']
                ]);
                break;

            case 'Добавить канал':
                Cache::put("channel:{$chatId}:state", 'awaiting_name', 300);
                TelegramSender::sendMessage($chatId, 'Имя нового канала:');
                break;

            case 'Список каналов':
                $this->showChannels($chatId);
                break;

            case 'Фильмы':
                TelegramSender::sendMessage($chatId, "🎬 Раздел фильмов – выберите действие:", [
                    ['Добавить фильм', 'Список фильмов'], ['❌ Назад']
                ]);
                break;

            case 'Добавить фильм':
                Cache::put("movie:{$chatId}:state", 'awaiting_name', 600);
                TelegramSender::sendMessage($chatId, 'Название фильма:');
                break;

            case 'Список фильмов':
                $this->showMovies($chatId);
                break;

            case 'Сообщение':
                Cache::put("admin:{$chatId}:state", 'awaiting_broadcast_message', 600);
                TelegramSender::sendMessage($chatId, '📣 Введите сообщение для рассылки:');
                break;

            case '❌ Назад':
                TelegramSender::sendMessage($chatId, 'Выберите действие:', [
                    ['Пользователи', 'Каналы'], ['Фильмы', 'Сообщение']
                ]);
                break;

            default:
                $this->handleAdminStates($text, $chatId, $message);
                break;
        }

        return response()->json(['ok' => true]);
    }

    private function showUsers($chatId)
    {
        $users = $this->adminService->getAllUsers();
        if ($users->isEmpty()) {
            TelegramSender::sendMessage($chatId, "❌ Пользователей нет.");
            return;
        }

        $messageParts = [];
        $current = "<b>📋 Список пользователей</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        
        foreach ($users as $index => $u) {
            $block = "<b>🔹 Пользователь №" . ($index + 1) . "</b>\n";
            $block .= "👤 <b>Имя:</b> {$u->first_name} {$u->last_name}\n";
            $block .= "🆔 <b>ID:</b> <code>{$u->telegram_id}</code>\n";
            $block .= "💬 <b>Username:</b> " . ($u->username ? "@{$u->username}" : '—') . "\n";
            $block .= "📅 <b>Регистрация:</b> {$u->registered_at}\n";
            $block .= "🛡 <b>Роль:</b> {$u->role}\n━━━━━━━━━━━━━━━━━━━━━━\n";
            
            if (strlen($current . $block) > 4000) {
                $messageParts[] = $current;
                $current = '';
            }
            $current .= $block;
        }
        
        if (!empty($current)) {
            $current .= "\n📌 <b>Всего пользователей:</b> " . count($users);
            $messageParts[] = $current;
        }
        
        foreach ($messageParts as $part) {
            TelegramSender::sendMessage($chatId, $part, null, 'HTML');
        }
    }

    private function showChannels($chatId)
    {
        $channels = $this->adminService->getAllChannels();
        if ($channels->isEmpty()) {
            TelegramSender::sendMessage($chatId, "Список каналов пуст.");
            return;
        }

        foreach ($channels as $channel) {
            $inline = [[['text' => '❌ Удалить', 'callback_data' => "delete_channel_{$channel->id}"]]];
            TelegramSender::sendMessage($chatId, "📺 $channel->name\n🔗 $channel->link", null, null, $inline);
        }
    }

    private function showMovies($chatId)
    {
        $movies = $this->adminService->getAllMovies();
        if ($movies->isEmpty()) {
            TelegramSender::sendMessage($chatId, "Пока нет фильмов.");
            return;
        }

        foreach ($movies as $movie) {
            $inline = [[['text' => '❌ Удалить', 'callback_data' => "delete_movie_{$movie->id}"]]];
            $msg = "<b>🎬 {$movie->name}</b>\n<b>🔢 Код:</b> {$movie->code}\n<b>📝 Forward:</b> (post saqlangan)";
            TelegramSender::sendMessage($chatId, $msg, null, 'HTML', $inline);
        }
    }

    private function handleAdminStates($text, $chatId, $message)
    {
        // Канал
        if (Cache::get("channel:{$chatId}:state") === 'awaiting_name') {
            Cache::put("channel:{$chatId}:name", $text, 300);
            Cache::put("channel:{$chatId}:state", 'awaiting_link', 300);
            TelegramSender::sendMessage($chatId, '🔗 Теперь введите ссылку на канал:');
        } elseif (Cache::get("channel:{$chatId}:state") === 'awaiting_link') {
            $name = Cache::pull("channel:{$chatId}:name");
            Cache::forget("channel:{$chatId}:state");
            $this->adminService->addChannel(['name' => $name, 'link' => $text]);
            TelegramSender::sendMessage($chatId, "✅ Канал \"$name\" успешно добавлен!");
        }
        
        // Фильм
        elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_name') {
            Cache::put("movie:{$chatId}:name", $text, 600);
            Cache::put("movie:{$chatId}:state", 'awaiting_code', 600);
            TelegramSender::sendMessage($chatId, '🔢 Теперь введите код фильма:');
        } elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_code') {
            Cache::put("movie:{$chatId}:code", $text, 600);
            Cache::put("movie:{$chatId}:state", 'awaiting_forward', 600);
            TelegramSender::sendMessage($chatId, '📥 Теперь отправьте пост из канала (forward qiling):');
        } elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_forward' && isset($message['forward_from_chat'])) {
            $name = Cache::pull("movie:{$chatId}:name");
            $code = Cache::pull("movie:{$chatId}:code");
            $rawPost = json_encode($message, JSON_UNESCAPED_UNICODE);
            
            $this->adminService->addMovie([
                'name' => $name,
                'code' => $code,
                'raw_post' => $rawPost,
            ]);
            
            Cache::forget("movie:{$chatId}:state");
            TelegramSender::sendMessage($chatId, "✅ Фильм \"$name\" успешно добавлен!");
        }
        
        // Рассылка
        elseif (Cache::get("admin:{$chatId}:state") === 'awaiting_broadcast_message') {
            Cache::forget("admin:{$chatId}:state");
            $users = $this->adminService->getAllUsers();
            foreach ($users as $user) {
                TelegramSender::sendMessage($user->telegram_id, $text);
            }
            TelegramSender::sendMessage($chatId, "✅ Сообщение отправлено всем!");
        }
    }

    public static function getTelegram()
    {
        return app('telegram');
    }
}