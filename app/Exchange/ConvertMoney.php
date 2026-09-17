<?php

namespace App\Exchange;

use App\Enums\Currency;
use Illuminate\Config\Repository as Config;

/**
 * Единственное место, где деньги меняют валюту. Цену товара назначают в его
 * валюте, а корзина, заказ и платёж живут в валюте расчётов: эквайринг
 * принимает рубли, и сумма заказа должна быть той, что списывают.
 */
class ConvertMoney
{
    public function __construct(
        private readonly CbrRates $rates,
        private readonly Config $config,
    ) {}

    /**
     * @throws ExchangeRateUnavailable
     */
    public function __invoke(int $minor, Currency $from): int
    {
        return (int) round($minor * $this->rateOf($from));
    }

    /**
     * @throws ExchangeRateUnavailable
     */
    public function rateOf(Currency $from): float
    {
        if ($from === $this->settlement()) {
            return 1.0;
        }

        $rate = $this->rates->rate($from);

        if ($rate === null || $rate <= 0) {
            throw ExchangeRateUnavailable::for($from);
        }

        return $rate;
    }

    public function settlement(): Currency
    {
        return Currency::from($this->config->string('shop.currency'));
    }
}
