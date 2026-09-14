<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ApplyPaymentResult;
use App\Models\Order;
use App\Payments\YooKassaGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class YooKassaWebhookController extends Controller
{
    /**
     * ЮKassa signs nothing, so the body is treated as a rumour: it names a
     * payment, and everything acted upon is then read back from the acquirer
     * over an authenticated call. A forged notification moves nothing.
     */
    public function __invoke(Request $request, YooKassaGateway $gateway, ApplyPaymentResult $applyPaymentResult): Response
    {
        $id = $request->string('object.id')->toString();

        if ($id === '') {
            return response()->noContent();
        }

        $payment = $gateway->fetchPayment($id);
        $number = $payment['metadata']['order_number'] ?? null;

        $order = Order::query()
            ->when(is_string($number), fn ($query) => $query->where('number', $number))
            ->when(! is_string($number), fn ($query) => $query->where('payment_external_id', $id))
            ->first();

        if ($order !== null) {
            $applyPaymentResult($order, $gateway->intentFrom($payment, $order));
        }

        // Anything but a 200 makes ЮKassa retry for a day, so an unknown order
        // is acknowledged rather than argued with.
        return response()->noContent();
    }
}
