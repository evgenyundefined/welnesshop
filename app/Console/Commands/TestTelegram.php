<?php

namespace App\Console\Commands;

use App\Notifications\TelegramChat;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;

class TestTelegram extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Проверить бота: отправить тестовое сообщение и сказать, что именно не так';

    public function handle(TelegramChat $telegram, Config $config): int
    {
        if (! $telegram->hasToken()) {
            $this->error('Не задан TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        if (! $telegram->isConfigured()) {
            $this->warn('Не задан TELEGRAM_CHAT_ID — уведомления никуда не уходят.');

            return $this->suggestChats($telegram);
        }

        $chatId = $config->get('services.telegram.chat_id');
        $reason = $telegram->deliver('Проверка связи: уведомления о заказах будут приходить сюда.');

        if ($reason !== '') {
            $this->error("Сообщение в чат {$chatId} не доставлено: {$reason}");

            // A timeout is not a misconfiguration and no amount of retyping the
            // chat id will fix it; say so instead of sending them looking.
            if (str_contains($reason, 'недоступен')) {
                $this->line('До Telegram нет сети с этого сервера — ни один chat_id этого не исправит.');
                $this->line('  Вариант 1: прокси в TELEGRAM_PROXY, например socks5://user:pass@host:1080');
                $this->line('  Вариант 2: зеркало Bot API в TELEGRAM_API_URL');
                $this->line('Письма о заказах это не затрагивает — они идут своим путём.');

                return self::FAILURE;
            }

            return $this->suggestChats($telegram);
        }

        $this->info("Сообщение отправлено в чат {$chatId}. Проверьте Telegram.");

        return self::SUCCESS;
    }

    /** Failure is more useful with the ids to choose from than without them. */
    private function suggestChats(TelegramChat $telegram): int
    {
        ['error' => $error, 'chats' => $chats] = $telegram->discoverChats();

        if ($error !== '') {
            $this->error($error);

            return self::FAILURE;
        }

        if ($chats === []) {
            $this->line('Бот пока не получал сообщений. Напишите ему в личку или добавьте в группу и напишите там, затем повторите.');

            return self::FAILURE;
        }

        $this->line('Чаты, о которых бот знает — впишите нужный id в TELEGRAM_CHAT_ID:');
        $this->table(['id', 'чат'], array_map(static fn (array $chat): array => [$chat['id'], $chat['title']], $chats));

        return self::FAILURE;
    }
}
