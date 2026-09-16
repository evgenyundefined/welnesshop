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
 * The shop's own copy, carrying what a manager needs to make the call: who
 * ordered, how to reach them and what they took.
 */
class NewOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Новый заказ {$this->order->number} — ".Money::format($this->order->total_minor, $this->order->currency),
            replyTo: [$this->order->contact_email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order', with: [
            'heading' => "Новый заказ {$this->order->number}",
            'intro' => 'Покупатель оформил заказ на витрине. Свяжитесь с ним для подтверждения.',
            'facts' => array_filter([
                'Покупатель' => $this->order->contact_name,
                'Телефон' => $this->order->contact_phone,
                'E-mail' => $this->order->contact_email,
                'Оплата' => $this->order->payment_method?->label(),
                'Доставка' => $this->order->delivery_method->label(),
                'Адрес' => $this->order->shipping_address,
            ]),
            'money' => fn (int $minor): string => Money::format($minor, $this->order->currency),
            'footer' => 'Заказ виден в админке, раздел «Заказы».',
        ]);
    }
}
