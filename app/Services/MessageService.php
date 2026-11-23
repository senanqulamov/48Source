<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Conversations between the buyer organisation and one supplier, optionally about a record. */
class MessageService
{
    public function start(User $user, Supplier $supplier, string $subject, string $body, ?Model $about = null): Conversation
    {
        if ($user->role === 'supplier' && $user->supplier_id !== $supplier->id) {
            throw new BusinessRule('You can only write on behalf of your own company.');
        }
        if (! $user->isSourcing() && $user->role !== 'supplier') {
            throw new BusinessRule('Your role cannot send messages.');
        }
        if (trim($subject) === '' || trim($body) === '') {
            throw new BusinessRule('A message needs a subject and a text.');
        }

        return DB::transaction(function () use ($user, $supplier, $subject, $body, $about) {
            $c = Conversation::create(['supplier_id' => $supplier->id, 'created_by' => $user->id, 'subject' => trim($subject), 'subject_ref_type' => $about?->getMorphClass(), 'subject_ref_id' => $about?->getKey()]);
            $this->post($c, $user, $body);

            return $c->fresh();
        });
    }

    public function post(Conversation $c, User $user, string $body): void
    {
        if (! Conversation::visibleTo($user)->whereKey($c->id)->exists()) {
            throw new BusinessRule('You are not part of this conversation.');
        }
        if (trim($body) === '') {
            throw new BusinessRule('Write a message first.');
        }
        $c->messages()->create(['user_id' => $user->id, 'body' => trim($body)]);
        $c->update(['last_message_at' => now()]);
        $this->markRead($c, $user);
        $url = route('messages.index', ['c' => $c->id]);
        if ($user->isSourcing()) {
            Notifier::supplier($c->supplier_id, "New message from the buyer: {$c->subject}", $url, 'Message');
        } else {
            $buyers = User::whereIn('id', $c->messages()->pluck('user_id')->push($c->created_by)->unique())->whereIn('role', ['admin', 'buyer'])->where('status', 'active')->get();
            if ($buyers->isEmpty()) {
                Notifier::admins("New message from {$c->supplier->name}: {$c->subject}", $url, 'Message');
            } else {
                Notifier::send($buyers, "New message from {$c->supplier->name}: {$c->subject}", $url, 'Message');
            }
        }
    }

    public function markRead(Conversation $c, User $user): void
    {
        ConversationRead::updateOrCreate(['conversation_id' => $c->id, 'user_id' => $user->id], ['message_id' => (int) $c->messages()->max('id'), 'read_at' => now()]);
    }

    /** Conversations holding a message from someone else that this user has not opened yet. */
    public function unread(User $user): int
    {
        return Conversation::visibleTo($user)->whereHas('messages', fn ($m) => $m->where('messages.user_id', '!=', $user->id)->whereRaw(
            'messages.id > coalesce((select r.message_id from conversation_reads r where r.conversation_id = messages.conversation_id and r.user_id = ?), 0)', [$user->id]))->count();
    }
}
