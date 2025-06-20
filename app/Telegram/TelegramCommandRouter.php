<?php

namespace App\Telegram;

use App\Services\AdminService;
use App\Services\UserService;
use App\Services\SubscriptionService;
use App\Telegram\Helpers\TelegramSender;
use App\Telegram\Helpers\TelegramChecker;
use App\Jobs\SendBulkMessageJob;
use Illuminate\Support\Facades\data_get;

class TelegramCommandRouter
{
    protected array $update;

    public function __construct(array $update)
    {
        $this->update = $update;
    }

    public function dispatch()
    {
        // Callback query handler
        if (isset($this->update['callback_query'])) {
            $callback = $this->update['callback_query'];
            $callbackData = data_get($callback, 'data', '');
            $callbackChatId = data_get($callback, 'message.chat.id');

            // Удаление канала
            if (str_starts_with($callbackData, 'delete_channel_')) {
                $channelId = intval(str_replace('delete_channel_', '', $callbackData));
                $adminService = new AdminService();
                $adminService->deleteChannel($channelId);
                TelegramSender::sendMessage($callbackChatId, "Канал удален!");
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Канал удален',
                    'show_alert' => false,
                ]);
                return;
            }

            // Удаление фильма
            if (str_starts_with($callbackData, 'delete_movie_')) {
                $movieId = intval(str_replace('delete_movie_', '', $callbackData));
                $adminService = new AdminService();
                $adminService->deleteMovie($movieId);
                TelegramSender::sendMessage($callbackChatId, "Фильм удален!");
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Фильм удален',
                    'show_alert' => false,
                ]);
                return;
            }

            // Проверка подписки
            if ($callbackData === 'check_subscription') {
                $subscriptionService = new SubscriptionService();
                $userId = $callbackChatId;
                if ($subscriptionService->checkUserSubscriptions($userId)) {
                    TelegramSender::sendMessage($userId, "✅ Вы подписаны на все каналы. Теперь введите код фильма.");
                } else {
                    TelegramSender::sendMessage($userId, "❌ Вы еще не подписаны на некоторые каналы.");
                }
                self::getTelegram()->answerCallbackQuery([
                    'callback_query_id' => data_get($callback, 'id'),
                    'text' => 'Проверено',
                    'show_alert' => false,
                ]);
                return;
            }
        }

        // Обычное сообщение
        $message = data_get($this->update, 'message');
        if (!$message) return;

        $text = data_get($message, 'text', '');
        $chatId = data_get($message, 'chat.id');

        // Создание или получение пользователя
        $userService = new UserService();
        $user = $userService->createOrGetUser([
            'telegram_id' => $chatId,
            'username' => data_get($message, 'from.username'),
            'first_name' => data_get($message, 'from.first_name'),
            'last_name' => data_get($message, 'from.last_name'),
        ]);

        // Команда /start
        if (str_starts_with($text, '/start')) {
            if ($user->role === 'admin') {
                $messageText = "👑 Здравствуйте, администратор {$user->first_name}!";
                $keyboard = [
                    ['Пользователи', 'Каналы'],
                    ['Фильмы', 'Сообщение'],
                ];
                TelegramSender::sendMessage($chatId, $messageText, $keyboard);
            } else {
                $subscriptionService = new SubscriptionService();
                if ($subscriptionService->checkUserSubscriptions($chatId)) {
                    $messageText = "🤖 Здравствуйте, {$user->first_name}! Добро пожаловать!
Вы подписаны на все каналы. Введите код фильма.";
                    TelegramSender::sendMessage($chatId, $messageText);
                } else {
                    $buttons = $subscriptionService->getSubscriptionButtons();
                    $messageText = "⚠️ Пожалуйста, подпишитесь на следующие каналы:";
                    TelegramSender::sendMessage($chatId, $messageText, null, null, $buttons);
                }
            }
            return;
        }

        // Только для пользователей
   if ($user->role !== 'admin') {
    if (preg_match('/^[A-Z0-9_]+$/', $text)) {
        $subscriptionService = new SubscriptionService();
        if (!$subscriptionService->checkUserSubscriptions($chatId)) {
            TelegramSender::sendMessage($chatId, "❌ Вы еще не подписаны на некоторые каналы.");
            $buttons = $subscriptionService->getSubscriptionButtons();
            TelegramSender::sendMessage($chatId, "⚠️ Пожалуйста, подпишитесь на следующие каналы:", null, null, $buttons);
            return;
        }

        $movieService = new \App\Services\MovieService();
        $rawPost = $movieService->findMovieByCode($text);

        if ($rawPost && isset($rawPost['message_id'], $rawPost['chat']['id'])) {
            // Forward qilish
            TelegramSender::forwardMessage($chatId, $rawPost['chat']['id'], $rawPost['message_id']);
        } else {
            TelegramSender::sendMessage($chatId, "❌ Такой код фильма не найден или данные повреждены.");
        }
        return;
    }

    TelegramSender::sendMessage($chatId, "⚠️ Вы можете использовать только команду /start.");
    return;
}

if ($text === 'Пользователи') {
    $adminService = new AdminService();
    $users = $adminService->getAllUsers();

    if ($users->isEmpty()) {
        TelegramSender::sendMessage($chatId, "❌ Пользователей нет.");
        return;
    }

    $messages = []; // barcha xabar bo‘laklari shu yerda yig‘iladi
    $currentMessage = "<b>📋 Список пользователей</b>\n";
    $currentMessage .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $messageLimit = 4000; // xavfsizlik uchun 4096 emas, 4000 belgida to‘xtatamiz

    foreach ($users as $index => $user) {
        $userBlock  = "<b>🔹 Пользователь №" . ($index + 1) . "</b>\n";
        $userBlock .= "👤 <b>Имя:</b> {$user->first_name} {$user->last_name}\n";
        $userBlock .= "🆔 <b>ID:</b> <code>{$user->telegram_id}</code>\n";
        $userBlock .= "💬 <b>Username:</b> " . ($user->username ? "@{$user->username}" : '—') . "\n";
        $userBlock .= "📅 <b>Регистрация:</b> {$user->registered_at}\n";
        $userBlock .= "🛡 <b>Роль:</b> {$user->role}\n";
        $userBlock .= "━━━━━━━━━━━━━━━━━━━━━━\n";

        // agar qo‘shgandan keyin limitdan oshib ketsa, eski xabarni saqlab, yangisini boshlaymiz
        if (strlen($currentMessage . $userBlock) > $messageLimit) {
            $messages[] = $currentMessage;
            $currentMessage = "";
        }

        $currentMessage .= $userBlock;
    }

    // oxirgi bo‘lakni qo‘shamiz
    if (!empty($currentMessage)) {
        // umumiy foydalanuvchilar soni
        $currentMessage .= "\n📌 <b>Всего пользователей:</b> " . count($users);
        $messages[] = $currentMessage;
    }

    // barcha xabarlarni yuboramiz
    foreach ($messages as $msg) {
        TelegramSender::sendMessage($chatId, $msg, null, 'HTML');
    }

    return;
}




        // Админ разделы
        if ($text === 'Каналы') {
            $keyboard = [
                ['Добавить канал', 'Список каналов'],
                ['❌ Назад'],
            ];
            TelegramSender::sendMessage($chatId, 'Раздел каналов – что вы хотите сделать?', $keyboard);
            return;
        }

        if ($text === 'Добавить канал') {
            app('cache')->put("channel:{$chatId}:state", 'awaiting_name', 300);
            TelegramSender::sendMessage($chatId, 'Пожалуйста, отправьте имя нового канала:');
            return;
        }

        if (app('cache')->get("channel:{$chatId}:state") === 'awaiting_name') {
            app('cache')->put("channel:{$chatId}:name", $text, 300);
            app('cache')->put("channel:{$chatId}:state", 'awaiting_link', 300);
            TelegramSender::sendMessage($chatId, 'Хорошо! Теперь отправьте ссылку на канал:');
            return;
        }

        if (app('cache')->get("channel:{$chatId}:state") === 'awaiting_link') {
            $name = app('cache')->pull("channel:{$chatId}:name");
            $link = $text;
            app('cache')->forget("channel:{$chatId}:state");

            $adminService = new AdminService();
            $adminService->addChannel(['name' => $name, 'link' => $link]);

            TelegramSender::sendMessage($chatId, "✅ Канал \"$name\" успешно добавлен!");
            return;
        }

        if ($text === 'Список каналов') {
            $adminService = new AdminService();
            $channels = $adminService->getAllChannels();

            if ($channels->isEmpty()) {
                TelegramSender::sendMessage($chatId, "Список каналов пуст.");
            } else {
                foreach ($channels as $channel) {
                    $inline = [[[
                        'text' => '❌ Удалить',
                        'callback_data' => "delete_channel_{$channel->id}",
                    ]]];
                    $msg = "📺 $channel->name\n🔗 $channel->link";
                    TelegramSender::sendMessage($chatId, $msg, null, null, $inline);
                }
            }
            return;
        }

        // Фильмы меню
        if ($text === 'Фильмы') {
            $keyboard = [
                ['Добавить фильм', 'Список фильмов'],
                ['❌ Назад'],
            ];
            TelegramSender::sendMessage($chatId, "🎬 Раздел фильмов – выберите действие:", $keyboard);
            return;
        }

        if ($text === 'Добавить фильм') {
            app('cache')->put("movie:{$chatId}:state", 'awaiting_name', 600);
            TelegramSender::sendMessage($chatId, '🎬 Пожалуйста, отправьте название фильма:');
            return;
        }

        if (app('cache')->get("movie:{$chatId}:state") === 'awaiting_name') {
            app('cache')->put("movie:{$chatId}:name", $text, 600);
            app('cache')->put("movie:{$chatId}:state", 'awaiting_code', 600);
            TelegramSender::sendMessage($chatId, '🔢 Теперь введите код фильма (например: 111, 222, 456):');
            return;
        }

        if (app('cache')->get("movie:{$chatId}:state") === 'awaiting_code') {
            app('cache')->put("movie:{$chatId}:code", $text, 600);
            app('cache')->put("movie:{$chatId}:state", 'awaiting_forward', 600);
            TelegramSender::sendMessage($chatId, '📥 Теперь отправьте пост из канала (forward qiling):');
            return;
        }

        if (app('cache')->get("movie:{$chatId}:state") === 'awaiting_forward' && isset($this->update['message']['forward_from_chat'])) {
            $rawPost = json_encode($this->update['message'], JSON_UNESCAPED_UNICODE);
            $name = app('cache')->pull("movie:{$chatId}:name");
            $code = app('cache')->pull("movie:{$chatId}:code");

            (new AdminService())->addMovie([
                'name' => $name,
                'code' => $code,
                'raw_post' => $rawPost,
            ]);

            TelegramSender::sendMessage($chatId, "✅ Фильм \"$name\" успешно добавлен через forward!");
            app('cache')->forget("movie:{$chatId}:state");
            return;
        }

        if ($text === 'Список фильмов') {
            $movies = (new AdminService())->getAllMovies();

            if ($movies->isEmpty()) {
                TelegramSender::sendMessage($chatId, "Пока нет фильмов.");
                return;
            }

            foreach ($movies as $movie) {
                $inline = [[[
                    'text' => '❌ Удалить',
                    'callback_data' => "delete_movie_{$movie->id}",
                ]]];
                $msg = "<b>🎬 {$movie->name}</b>\n";
                $msg .= "<b>🔢 Код:</b> {$movie->code}\n";
                $msg .= "<b>📝 Forward:</b> (post saqlangan)";
                TelegramSender::sendMessage($chatId, $msg, null, 'HTML', $inline);
            }
        }

        if ($text === '❌ Назад') {
            $keyboard = [
                ['Пользователи', 'Каналы'],
                ['Фильмы', 'Сообщение'],
            ];
            TelegramSender::sendMessage($chatId, 'Выберите действие:', $keyboard);
        }
        

        // Рассылка сообщения
        if ($text === 'Сообщение') {
            app('cache')->put("admin:{$chatId}:state", 'awaiting_broadcast_message', 600);
            TelegramSender::sendMessage($chatId, '📣 Какое сообщение вы хотите разослать?');
            return;
        }

        if (app('cache')->get("admin:{$chatId}:state") === 'awaiting_broadcast_message') {
            $messageText = $text;
            app('cache')->forget("admin:{$chatId}:state");
            dispatch(new SendBulkMessageJob($messageText));
            TelegramSender::sendMessage($chatId, '✅ Сообщение отправлено всем!');
            return;
        }
    }

    public static function getTelegram()
    {
        return app('telegram'); // Laravel сервис
    }
}