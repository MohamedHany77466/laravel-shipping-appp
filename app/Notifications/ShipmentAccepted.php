<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentAccepted extends Notification
{
    use Queueable;
      protected $price;

    public function __construct($price)
    {
        $this->price = $price;
    }

    public function via($notifiable)
    {
        return ['database']; // أو mail لبريد
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'تم قبول طلبك!',
            'message' => 'تم قبول طلبك لنقل الشحنة بالسعر: ' . $this->price . ' $',
        ];
    }
}

