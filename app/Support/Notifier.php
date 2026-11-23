<?php

namespace App\Support;

use App\Models\FeatureFlag;
use App\Models\OutboxEmail;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SystemNotice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** In-app notifications. When email is switched on, each one is also written to the outbox and sent. */
final class Notifier
{
    /** @param iterable<User>|User $users */
    public static function send(iterable|User $users, string $text, string $url, string $area = 'General'): void
    {
        $users = collect($users instanceof User ? [$users] : $users)->filter(fn (User $u) => $u->status === 'active')->unique('id');
        Notification::send($users, new SystemNotice($text, $url, $area));
        if (FeatureFlag::isOn('email_notifications')) {
            foreach ($users->filter(fn (User $u) => $u->email_notices) as $u) {
                self::deliver(OutboxEmail::create(['user_id' => $u->id, 'to' => $u->email, 'subject' => mb_strimwidth($text, 0, 140, '...'), 'body' => $text, 'url' => $url, 'area' => $area]));
            }
        }
    }

    /** Send one outbox email now and record how it went. A mail failure never stops the business action. */
    public static function deliver(OutboxEmail $mail): OutboxEmail
    {
        try {
            $link = $mail->url ? "\n\nOpen in 48Source: " . url($mail->url) : '';
            Mail::raw($mail->body . $link, fn ($m) => $m->to($mail->to)->subject('[48Source] ' . $mail->subject));
            $mail->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $mail->update(['status' => 'failed', 'error' => mb_strimwidth($e->getMessage(), 0, 250, '...')]);
        }

        return $mail;
    }

    public static function supplier(Supplier|int $supplier, string $text, string $url, string $area = 'General'): void
    {
        $id = $supplier instanceof Supplier ? $supplier->id : $supplier;
        self::send(User::where('supplier_id', $id)->where('role', 'supplier')->get(), $text, $url, $area);
    }

    public static function admins(string $text, string $url, string $area = 'General'): void
    {
        self::send(User::where('role', 'admin')->get(), $text, $url, $area);
    }
}
