<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;

/**
 * Placeholder until a real acquirer is bound to PaymentGateway: it keeps the
 * order reserved and awaiting payment, and says so instead of pretending.
 */
final readonly class PendingPaymentGateway implements PaymentGateway
{
    public const string PROVIDER = 'none';

    public function createPayment(Order $order): PaymentIntent
    {
        return new PaymentIntent(
            status: PaymentStatus::Pending,
            provider: self::PROVIDER,
            message: 'Платёжный провайдер ещё не подключён. Заказ зарезервирован и ожидает оплаты.',
        );
    }
}
