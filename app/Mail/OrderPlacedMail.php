<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The buyer's own copy of what was ordered. Queued: a slow mail server must
 * not hold up the response that tells them the order went through.
 */
class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Заказ {$this->order->number} принят");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order', with: [
            'heading' => "Заказ {$this->order->number} принят",
            'intro' => 'Ваш заказ принят. С вами свяжется менеджер для подтверждения заказа и согласования деталей.',
            'facts' => array_filter([
                'Номер' => $this->order->number,
                'Получатель' => $this->order->contact_name,
                'Телефон' => $this->order->contact_phone,
                'Доставка' => $this->order->delivery_method->label(),
                'Адрес' => $this->order->shipping_address,
            ]),
            'money' => fn (int $minor): string => Money::format($minor, $this->order->currency),
            'footer' => 'Письмо отправлено автоматически, отвечать на него не нужно.',
        ]);
    }
}
