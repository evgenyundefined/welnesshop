<?php

namespace App\Notifications;

use Illuminate\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A chat message is a convenience, never a condition of the order going
 * through: send() swallows a failure into the log, because the buyer has
 * already been told their order was accepted.
 *
 * That silence is right for an order and useless for setting the bot up, so
 * deliver() hands the reason back instead and telegram:test says it out loud.
 */
class TelegramChat
{
    public function __construct(
        private readonly Config $config,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    public function hasToken(): bool
    {
        return $this->token() !== '';
    }

    public function isConfigured(): bool
    {
        return $this->hasToken() && $this->chatId() !== '';
    }

    public function send(string $text): void
    {
        $reason = $this->deliver($text);

        if ($reason !== '') {
            $this->log->warning('Уведомление в Telegram не отправлено', ['reason' => $reason]);
        }
    }

    /** @return string Empty when the message was delivered, the reason otherwise. */
    public function deliver(string $text): string
    {
        if (! $this->hasToken()) {
            return 'не задан TELEGRAM_BOT_TOKEN';
        }

        if ($this->chatId() === '') {
            return 'не задан TELEGRAM_CHAT_ID';
        }

        return $this->call('sendMessage', [
            'chat_id' => $this->chatId(),
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ])['error'] ?? '';
    }

    /**
     * Chats the bot has heard from, so the id can be read off instead of
     * hunted for in a browser. Telegram only keeps recent updates, so an empty
     * list usually means nobody has written to the bot yet.
     *
     * @return array{error: string, chats: list<array{id: string, title: string}>}
     */
    public function discoverChats(): array
    {
        if (! $this->hasToken()) {
            return ['error' => 'не задан TELEGRAM_BOT_TOKEN', 'chats' => []];
        }

        $result = $this->call('getUpdates', ['limit' => 100]);

        if (isset($result['error'])) {
            return ['error' => (string) $result['error'], 'chats' => []];
        }

        $chats = [];

        foreach ($result['result'] ?? [] as $update) {
            foreach (['message', 'channel_post', 'my_chat_member'] as $key) {
                $chat = $update[$key]['chat'] ?? null;

                if ($chat === null) {
                    continue;
                }

                $chats[(string) $chat['id']] = [
                    'id' => (string) $chat['id'],
                    'title' => $this->chatTitle($chat),
                ];
            }
        }

        return ['error' => '', 'chats' => array_values($chats)];
    }

    /** @param array<string, mixed> $chat */
    private function chatTitle(array $chat): string
    {
        $name = trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? ''));

        return (string) ($chat['title'] ?? ($name !== '' ? $name : ($chat['username'] ?? $chat['type'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        $proxy = trim((string) $this->config->get('services.telegram.proxy'));

        try {
            $response = $this->http
                ->timeout($this->config->integer('services.telegram.timeout'))
                ->when($proxy !== '', fn ($request) => $request->withOptions(['proxy' => $proxy]))
                ->post("{$this->apiUrl()}/bot{$this->token()}/{$method}", $payload);
        } catch (Throwable $e) {
            return ['error' => 'Telegram недоступен: '.$e->getMessage()];
        }

        $body = $response->json();

        if ($response->failed() || ($body['ok'] ?? false) !== true) {
            return ['error' => sprintf(
                'Telegram ответил %d: %s',
                $response->status(),
                $body['description'] ?? 'без объяснения',
            )];
        }

        return $body;
    }

    private function apiUrl(): string
    {
        return $this->config->string('services.telegram.api_url');
    }

    private function token(): string
    {
        return trim((string) $this->config->get('services.telegram.bot_token'));
    }

    private function chatId(): string
    {
        return trim((string) $this->config->get('services.telegram.chat_id'));
    }
}
