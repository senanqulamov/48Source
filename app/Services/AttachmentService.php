<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Attachment;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Files on requests and quotations. Stored on the private disk and only served through an access check. */
class AttachmentService
{
    public const MAX_PER_RECORD = 12;

    public function addToRfq(Rfq $rfq, User $user, UploadedFile $file): Attachment
    {
        if (! $user->isSourcing() || ! ($user->isAdmin() || $rfq->buyer_id === $user->id)) {
            throw new BusinessRule('Only the buyer of this request can add files to it.');
        }
        if (! in_array($rfq->status, ['draft', 'open'], true)) {
            throw new BusinessRule('Files can be added while the request is a draft or open.');
        }

        return $this->store($rfq, $user, $file, null, "rfq/{$rfq->id}", $rfq->number);
    }

    public function addToQuote(Quote $quote, User $user, UploadedFile $file): Attachment
    {
        if ($user->role !== 'supplier' || $user->supplier_id !== $quote->supplier_id) {
            throw new BusinessRule('Only the supplier that owns this quotation can add files to it.');
        }
        if (! $quote->rfq->isOpenForQuotes() && ! $quote->rfq->openBafo()) {
            throw new BusinessRule('Files can be added until the quotation deadline.');
        }

        return $this->store($quote, $user, $file, $quote->supplier_id, "quote/{$quote->id}", $quote->rfq->number);
    }

    private function store(Rfq|Quote $on, User $user, UploadedFile $file, ?int $supplierId, string $dir, string $label): Attachment
    {
        if ($on->attachments()->count() >= self::MAX_PER_RECORD) {
            throw new BusinessRule('There are already ' . self::MAX_PER_RECORD . ' files here. Remove one first.');
        }
        $a = $on->attachments()->create(['uploaded_by' => $user->id, 'supplier_id' => $supplierId, 'path' => $file->store("attachments/{$dir}", 'local'), 'name' => mb_strimwidth($file->getClientOriginalName(), 0, 180, ''), 'size' => $file->getSize()]);
        Audit::log($on instanceof Rfq ? 'RFQ' : 'Quotation', "Added file \"{$a->name}\"", $on instanceof Rfq ? $on : $on->rfq, $label, supplierId: $supplierId);

        return $a->fresh();
    }

    public function canSee(Attachment $a, User $user): bool
    {
        $on = $a->attachable;
        if ($on instanceof Rfq) {
            return Rfq::visibleTo($user)->whereKey($on->id)->exists();
        }
        if ($on instanceof Quote) {
            if ($user->role === 'supplier') {
                return $user->supplier_id === $on->supplier_id;
            }

            // The buyer side sees quotation files under the same rule as prices: not while bids are sealed.
            return $user->isSourcing() && Rfq::visibleTo($user)->whereKey($on->rfq_id)->exists() && ! $on->rfq->pricesHidden() && $on->status !== 'draft';
        }

        return false;
    }

    public function remove(Attachment $a, User $user): void
    {
        $on = $a->attachable;
        $ok = $on instanceof Rfq ? ($user->isSourcing() && ($user->isAdmin() || $on->buyer_id === $user->id) && in_array($on->status, ['draft', 'open'], true))
            : ($on instanceof Quote && $user->role === 'supplier' && $user->supplier_id === $on->supplier_id && ($on->rfq->isOpenForQuotes() || $on->rfq->openBafo()));
        if (! $ok) {
            throw new BusinessRule('This file can no longer be removed by you.');
        }
        Storage::disk('local')->delete($a->path);
        $a->delete();
    }
}
