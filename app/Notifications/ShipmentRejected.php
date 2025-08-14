<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentRejected extends Notification
{
    use Queueable;

    protected $price;

    public function __construct($price)
    {
        $this->price = $price;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'تم رفض طلب الشحن',
            'body' => 'تم رفض طلبك لنقل الشحنة بالسعر: ' . $this->price . ' $',
        ];
    }
}
