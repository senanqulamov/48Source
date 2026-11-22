<?php

namespace App\Livewire\Supplier\Rfq;

use App\Models\FieldAssessment;
use App\Models\Quote;
use App\Models\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Request $request;
    public bool $canQuote = false;
    public bool $assessmentComplete = false;
    public ?Quote $supplierQuote = null;

    public function mount(Request $request): void
    {
        $user = Auth::user();
        abort_unless($user && $user->isSupplier(), 403);

        $allowed = $request->status !== 'draft'
            && $request->status !== 'cancelled'
            && ($request->request_type === 'public'
                || $request->supplierInvitations()->where('supplier_id', $user->id)->exists());
        abort_unless($allowed, 403);

        // Never eager-load other suppliers' confidential quotations.
        $this->request = $request->load(['buyer', 'items.product', 'fieldEvaluator']);
        $this->supplierQuote = Quote::query()
            ->where('request_id', $request->id)
            ->where('supplier_id', $user->id)
            ->first();

        $this->assessmentComplete = ! $request->requires_field_assessment
            || FieldAssessment::query()
                ->where('request_id', $request->id)
                ->where('supplier_id', $user->id)
                ->where('status', 'completed')
                ->exists();

        $this->canQuote = $request->status === 'open'
            && (! $request->deadline || $request->deadline->isFuture())
            && $this->supplierQuote === null;
    }

    public function render(): View
    {
        return view('livewire.supplier.rfq.show')->layout('layouts.app');
    }
}
