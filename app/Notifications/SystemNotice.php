<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotice extends Notification
{
    use Queueable;

    public function __construct(public string $text, public string $url, public string $area = 'General', public bool $mail = false) {}

    public function via(object $notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['text' => $this->text, 'url' => $this->url, 'area' => $this->area];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->text)->line($this->text)->action('Open in 48Source', url($this->url));
    }
}
