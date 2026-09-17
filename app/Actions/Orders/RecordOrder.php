<?php

namespace App\Actions\Orders;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ProductNotAvailable;
use App\Exchange\ConvertMoney;
use App\Exchange\ExchangeRateUnavailable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a set of product quantities into an order. Shared by storefront
 * checkout and by an administrator entering an order by hand, so both go
 * through the same stock accounting.
 */
class RecordOrder
{
    public function __construct(
        private readonly Config $config,
        private readonly ConvertMoney $convert,
    ) {}

    /**
     * @param  array<int, int>  $quantities  product id => quantity
     * @param  array<string, mixed>  $details
     *
     * @throws ExchangeRateUnavailable
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(Customer $customer, array $quantities, array $details): Order
    {
        return DB::transaction(function () use ($customer, $quantities, $details): Order {
            $products = $this->lockProducts(array_keys($quantities));

            $lines = collect($quantities)->map(
                fn (int $quantity, int $productId): array => $this->buildLine($products[$productId], $quantity),
            );

            $order = Order::query()->create([
                ...$details,
                'number' => $this->generateNumber(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::AwaitingPayment,
                'payment_status' => PaymentStatus::Pending,
                'currency' => $this->convert->settlement(),
                'total_minor' => $lines->sum('total_minor') + (int) ($details['delivery_cost_minor'] ?? 0),
            ]);

            $lines->each(function (array $line) use ($order, $products): void {
                $order->items()->create($line);
                $products[$line['product_id']]->decrement('stock', $line['quantity']);
            });

            return $order->load('items.product.primaryImage');
        });
    }

    /**
     * Locks the ordered products for the length of the transaction so two
     * simultaneous checkouts cannot both sell the last unit.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    private function lockProducts(array $productIds): Collection
    {
        return Product::query()
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Цена назначена в валюте товара, а заказ живёт в валюте расчётов: её и
     * спишет эквайринг. Курс и исходная цена остаются в позиции, иначе через
     * неделю сумму заказа будет нечем объяснить.
     *
     * @return array<string, mixed>
     *
     * @throws ExchangeRateUnavailable
     * @throws ProductNotAvailable
     */
    private function buildLine(Product $product, int $quantity): array
    {
        if (! $product->status->isVisibleInCatalog() || $quantity > $product->stock) {
            throw ProductNotAvailable::forProduct($product);
        }

        /** @var Currency $currency */
        $currency = $product->currency;
        $unitPrice = ($this->convert)($product->price_minor, $currency);

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'unit_price_minor' => $unitPrice,
            'quantity' => $quantity,
            'total_minor' => $unitPrice * $quantity,
            'original_currency' => $currency,
            'original_unit_price_minor' => $product->price_minor,
            'exchange_rate' => $this->convert->rateOf($currency),
        ];
    }

    private function generateNumber(): string
    {
        $prefix = $this->config->string('shop.order_number_prefix');

        do {
            $number = sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), Str::upper(Str::random(6)));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
