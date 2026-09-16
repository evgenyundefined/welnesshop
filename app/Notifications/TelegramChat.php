<?php

namespace App\Notifications;

use Illuminate\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A chat message is a convenience, never a condition of the order going
 * through: a failure is written to the log and swallowed, because the buyer
 * has already been told their order was accepted.
 */
class TelegramChat
{
    public function __construct(
        private readonly Config $config,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    public function isConfigured(): bool
    {
        return (string) $this->config->get('services.telegram.bot_token') !== ''
            && (string) $this->config->get('services.telegram.chat_id') !== '';
    }

    public function send(string $text): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $token = (string) $this->config->get('services.telegram.bot_token');

        try {
            $response = $this->http
                ->timeout($this->config->integer('services.telegram.timeout'))
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => (string) $this->config->get('services.telegram.chat_id'),
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                $this->log->warning('Telegram отклонил уведомление', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            $this->log->warning('Не удалось отправить уведомление в Telegram', ['exception' => $e->getMessage()]);
        }
    }
}
