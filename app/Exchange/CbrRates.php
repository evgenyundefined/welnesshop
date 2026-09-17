<?php

namespace App\Exchange;

use App\Enums\Currency;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Курсы ЦБ. Публикуются раз в сутки, поэтому и кэш суточный: дёргать их на
 * каждый показ карточки незачем.
 *
 * Последний удавшийся курс хранится отдельно и надолго. Если ЦБ сегодня не
 * ответил, магазин продолжает работать по вчерашнему курсу — это честнее, чем
 * закрыть витрину, и безопаснее, чем выдумать число.
 */
class CbrRates
{
    private const DAILY_KEY = 'cbr.rates.today';

    private const LAST_KNOWN_KEY = 'cbr.rates.last-known';

    private const LAST_KNOWN_TTL_DAYS = 30;

    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly Http $http,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * Сколько единиц валюты расчётов стоит одна единица заданной валюты.
     * Для самой валюты расчётов — единица, без обращения к ЦБ.
     */
    public function rate(Currency $currency): ?float
    {
        if ($currency === $this->settlement()) {
            return 1.0;
        }

        return $this->rates()[$currency->value] ?? null;
    }

    /** @return array<string, float> */
    public function rates(): array
    {
        $today = $this->cache->get(self::DAILY_KEY);

        if (is_array($today)) {
            return $today;
        }

        $fetched = $this->fetch();

        if ($fetched === []) {
            // Вчерашний курс лучше пустой витрины; про подмену пишем в лог,
            // чтобы это не осталось незамеченным.
            $this->log->warning('ЦБ не отдал курсы, используется последний известный');

            return $this->cache->get(self::LAST_KNOWN_KEY) ?? [];
        }

        $this->cache->put(self::DAILY_KEY, $fetched, now()->addDay());
        $this->cache->put(self::LAST_KNOWN_KEY, $fetched, now()->addDays(self::LAST_KNOWN_TTL_DAYS));

        return $fetched;
    }

    /** @return array<string, float> */
    private function fetch(): array
    {
        try {
            $response = $this->http
                ->timeout($this->config->integer('services.cbr.timeout'))
                ->get($this->config->string('services.cbr.url'));

            if ($response->failed()) {
                $this->log->warning('ЦБ ответил ошибкой', ['status' => $response->status()]);

                return [];
            }

            return $this->parse($response->body());
        } catch (Throwable $e) {
            $this->log->warning('ЦБ недоступен', ['exception' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * ЦБ отдаёт XML в windows-1251, с запятой в качестве разделителя и с
     * номиналом: сто йен стоят столько-то рублей, а не одна.
     *
     * @return array<string, float>
     */
    private function parse(string $body): array
    {
        $xml = @simplexml_load_string($this->toUtf8($body));

        if ($xml === false) {
            $this->log->warning('Не удалось разобрать ответ ЦБ');

            return [];
        }

        $rates = [];

        foreach ($xml->Valute as $valute) {
            $nominal = (int) $valute->Nominal;
            $value = (float) str_replace([' ', ','], ['', '.'], (string) $valute->Value);

            if ($nominal < 1 || $value <= 0) {
                continue;
            }

            $rates[(string) $valute->CharCode] = $value / $nominal;
        }

        return $rates;
    }

    private function toUtf8(string $body): string
    {
        $converted = mb_convert_encoding($body, 'UTF-8', 'Windows-1251');

        return preg_replace('/encoding="[^"]+"/i', 'encoding="UTF-8"', $converted) ?? $converted;
    }

    private function settlement(): Currency
    {
        return Currency::from($this->config->string('shop.currency'));
    }
}
