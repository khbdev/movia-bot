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
    public function handle(Request $request)
    {
        $update = $request->all();

        // Callback query handler
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $callbackData = data_get($callback, 'data', '');
            $callbackChatId = data_get($callback, 'message.chat.id');

            // Удаление канала
            if (str_starts_with($callbackData, 'delete_channel_')) {
                $channelId = intval(str_replace('delete_channel_', '', $callbackData));
                (new AdminService())->deleteChannel($channelId);
                TelegramSender::sendMessage($callbackChatId, "Канал удален!");
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Канал удален',
                    'show_alert' => false,
                ]);
                return response()->json(['ok' => true]);
            }

            // Удаление фильма
            if (str_starts_with($callbackData, 'delete_movie_')) {
                $movieId = intval(str_replace('delete_movie_', '', $callbackData));
                (new AdminService())->deleteMovie($movieId);
                TelegramSender::sendMessage($callbackChatId, "Фильм удален!");
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Фильм удален',
                    'show_alert' => false,
                ]);
                return response()->json(['ok' => true]);
            }

            // Проверка подписки
            if ($callbackData === 'check_subscription') {
                $subscriptionService = new SubscriptionService();
                if ($subscriptionService->checkUserSubscriptions($callbackChatId)) {
                    TelegramSender::sendMessage($callbackChatId, "✅ Вы подписаны на все каналы. Теперь введите код фильма.");
                } else {
                    TelegramSender::sendMessage($callbackChatId, "❌ Вы еще не подписаны на некоторые каналы.");
                }
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Проверено',
                    'show_alert' => false,
                ]);
                return response()->json(['ok' => true]);
            }
        }

        // Message handler
        $message = data_get($update, 'message');
        if (!$message) return response()->json(['ok' => true]);

        $text = data_get($message, 'text', '');
        $chatId = data_get($message, 'chat.id');

        // Create or get user
        $userService = new UserService();
        $user = $userService->createOrGetUser([
            'telegram_id' => $chatId,
            'username' => data_get($message, 'from.username'),
            'first_name' => data_get($message, 'from.first_name'),
            'last_name' => data_get($message, 'from.last_name'),
        ]);

        // /start
        if (str_starts_with($text, '/start')) {
            if ($user->role === 'admin') {
                $keyboard = [['Пользователи', 'Каналы'], ['Фильмы', 'Сообщение']];
                TelegramSender::sendMessage($chatId, "👑 Здравствуйте, администратор {$user->first_name}!", $keyboard);
            } else {
                $subscriptionService = new SubscriptionService();
                if ($subscriptionService->checkUserSubscriptions($chatId)) {
                    TelegramSender::sendMessage($chatId, "🤖 Здравствуйте, {$user->first_name}! Вы подписаны. Введите код фильма.");
                } else {
                    $buttons = $subscriptionService->getSubscriptionButtons();
                    TelegramSender::sendMessage($chatId, "⚠️ Пожалуйста, подпишитесь на каналы:", null, null, $buttons);
                }
            }
            return response()->json(['ok' => true]);
        }

        // User movie code input
        if ($user->role !== 'admin') {
            if (preg_match('/^[A-Z0-9_]+$/', $text)) {
                $subscriptionService = new SubscriptionService();
                if (!$subscriptionService->checkUserSubscriptions($chatId)) {
                    TelegramSender::sendMessage($chatId, "❌ Вы не подписаны на все каналы.");
                    $buttons = $subscriptionService->getSubscriptionButtons();
                    TelegramSender::sendMessage($chatId, "⚠️ Подпишитесь на следующие каналы:", null, null, $buttons);
                    return response()->json(['ok' => true]);
                }
                $movieService = new MovieService();
                $rawPost = $movieService->findMovieByCode($text);
                if ($rawPost && isset($rawPost['message_id'], $rawPost['chat']['id'])) {
                    TelegramSender::forwardMessage($chatId, $rawPost['chat']['id'], $rawPost['message_id']);
                } else {
                    TelegramSender::sendMessage($chatId, "❌ Код фильма не найден.");
                }
                return response()->json(['ok' => true]);
            }
            TelegramSender::sendMessage($chatId, "⚠️ Используйте только команду /start.");
            return response()->json(['ok' => true]);
        }

        // Admin panel
        switch ($text) {
            case 'Пользователи':
                $users = (new AdminService())->getAllUsers();
                if ($users->isEmpty()) {
                    TelegramSender::sendMessage($chatId, "❌ Пользователей нет.");
                    break;
                }
                $messageParts = [];
                $current = "<b>📋 Список пользователей</b>
━━━━━━━━━━━━━━━━━━━━━━
";
                foreach ($users as $index => $u) {
                    $block = "<b>🔹 Пользователь №" . ($index + 1) . "</b>
";
                    $block .= "👤 <b>Имя:</b> {$u->first_name} {$u->last_name}
";
                    $block .= "🆔 <b>ID:</b> <code>{$u->telegram_id}</code>
";
                    $block .= "💬 <b>Username:</b> " . ($u->username ? "@{$u->username}" : '—') . "
";
                    $block .= "📅 <b>Регистрация:</b> {$u->registered_at}
";
                    $block .= "🛡 <b>Роль:</b> {$u->role}
━━━━━━━━━━━━━━━━━━━━━━
";
                    if (strlen($current . $block) > 4000) {
                        $messageParts[] = $current;
                        $current = '';
                    }
                    $current .= $block;
                }
                if (!empty($current)) {
                    $current .= "
📌 <b>Всего пользователей:</b> " . count($users);
                    $messageParts[] = $current;
                }
                foreach ($messageParts as $part) {
                    TelegramSender::sendMessage($chatId, $part, null, 'HTML');
                }
                break;

            case 'Каналы':
                TelegramSender::sendMessage($chatId, 'Раздел каналов – выберите действие:', [
                    ['Добавить канал', 'Список каналов'],
                    ['❌ Назад'],
                ]);
                break;

            case 'Добавить канал':
                Cache::put("channel:{$chatId}:state", 'awaiting_name', 300);
                TelegramSender::sendMessage($chatId, 'Имя нового канала:');
                break;

            case 'Список каналов':
                $channels = (new AdminService())->getAllChannels();
                if ($channels->isEmpty()) {
                    TelegramSender::sendMessage($chatId, "Список каналов пуст.");
                    break;
                }
                foreach ($channels as $channel) {
                    $inline = [[['text' => '❌ Удалить', 'callback_data' => "delete_channel_{$channel->id}"]]];
                    TelegramSender::sendMessage($chatId, "📺 $channel->name
🔗 $channel->link", null, null, $inline);
                }
                break;

            case 'Фильмы':
                TelegramSender::sendMessage($chatId, "🎬 Раздел фильмов – выберите действие:", [
                    ['Добавить фильм', 'Список фильмов'],
                    ['❌ Назад'],
                ]);
                break;

            case 'Добавить фильм':
                Cache::put("movie:{$chatId}:state", 'awaiting_name', 600);
                TelegramSender::sendMessage($chatId, 'Название фильма:');
                break;

            case 'Список фильмов':
                $movies = (new AdminService())->getAllMovies();
                if ($movies->isEmpty()) {
                    TelegramSender::sendMessage($chatId, "Пока нет фильмов.");
                    break;
                }
                foreach ($movies as $movie) {
                    $inline = [[['text' => '❌ Удалить', 'callback_data' => "delete_movie_{$movie->id}"]]];
                    $msg = "<b>🎬 {$movie->name}</b>
<b>🔢 Код:</b> {$movie->code}
<b>📝 Forward:</b> (post saqlangan)";
                    TelegramSender::sendMessage($chatId, $msg, null, 'HTML', $inline);
                }
                break;

            case 'Сообщение':
                Cache::put("admin:{$chatId}:state", 'awaiting_broadcast_message', 600);
                TelegramSender::sendMessage($chatId, '📣 Введите сообщение для рассылки:');
                break;

            case '❌ Назад':
                TelegramSender::sendMessage($chatId, 'Выберите действие:', [
                    ['Пользователи', 'Каналы'],
                    ['Фильмы', 'Сообщение'],
                ]);
                break;

            default:
                // Handle adding channel - awaiting name and then link
                if (Cache::get("channel:{$chatId}:state") === 'awaiting_name') {
                    Cache::put("channel:{$chatId}:name", $text, 300);
                    Cache::put("channel:{$chatId}:state", 'awaiting_link', 300);
                    TelegramSender::sendMessage($chatId, '🔗 Теперь введите ссылку на канал:');
                } elseif (Cache::get("channel:{$chatId}:state") === 'awaiting_link') {
                    $name = Cache::pull("channel:{$chatId}:name");
                    $link = $text;
                    Cache::forget("channel:{$chatId}:state");
                    (new AdminService())->addChannel(['name' => $name, 'link' => $link]);
                    TelegramSender::sendMessage($chatId, "✅ Канал \"$name\" успешно добавлен!");
                }

                // Handle adding movie
                elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_code') {
                    Cache::put("movie:{$chatId}:code", $text, 600);
                    Cache::put("movie:{$chatId}:state", 'awaiting_forward', 600);
                    TelegramSender::sendMessage($chatId, '📥 Теперь отправьте пост из канала (forward qiling):');
                } elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_name') {
                    Cache::put("movie:{$chatId}:name", $text, 600);
                    Cache::put("movie:{$chatId}:state", 'awaiting_code', 600);
                    TelegramSender::sendMessage($chatId, '🔢 Теперь введите код фильма:');
                } elseif (Cache::get("movie:{$chatId}:state") === 'awaiting_forward' && isset($message['forward_from_chat'])) {
                    $rawPost = json_encode($message, JSON_UNESCAPED_UNICODE);
                    $name = Cache::pull("movie:{$chatId}:name");
                    $code = Cache::pull("movie:{$chatId}:code");
                    (new AdminService())->addMovie([
                        'name' => $name,
                        'code' => $code,
                        'raw_post' => $rawPost,
                    ]);
                    Cache::forget("movie:{$chatId}:state");
                    TelegramSender::sendMessage($chatId, "✅ Фильм \"$name\" успешно добавлен!");
                }

                // Broadcast message
                elseif (Cache::get("admin:{$chatId}:state") === 'awaiting_broadcast_message') {
                    Cache::forget("admin:{$chatId}:state");
                    $users = (new AdminService())->getAllUsers();
                    foreach ($users as $user) {
                        TelegramSender::sendMessage($user->telegram_id, $text);
                    }
                    TelegramSender::sendMessage($chatId, "✅ Сообщение отправлено всем!");
                }

                break;
        }

        return response()->json(['ok' => true]);
    }

    public static function getTelegram()
    {
        return app('telegram');
    }
}