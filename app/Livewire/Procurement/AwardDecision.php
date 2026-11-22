<?php

namespace App\Livewire\Procurement;

use App\Models\ProcurementPurchaseOrder;
use App\Models\Quote;
use App\Models\Request;
use App\Services\Procurement\AwardAndPurchaseOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AwardDecision extends Component
{
    public Request $request;
    public ?int $selectedQuoteId = null;
    public string $justification = '';

    public function mount(Request $request): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || ($user->isBuyer() && $request->buyer_id === $user->id)), 403);
        $this->request = $request;
    }

    public function award(AwardAndPurchaseOrderService $service): void
    {
        $this->validate([
            'selectedQuoteId' => ['required', 'integer'],
            'justification' => ['nullable', 'string', 'max:3000'],
        ]);
        $order = $service->award($this->request, $this->selectedQuoteId, auth()->user(), $this->justification);
        $this->redirectRoute('procurement.orders.show', ['purchaseOrder' => $order->id]);
    }

    public function render(): View
    {
        $existing = ProcurementPurchaseOrder::query()->where('request_id', $this->request->id)->first();
        $quotes = Quote::query()->where('request_id', $this->request->id)
            ->where('status', 'submitted')->with('supplier')->get();

        return view('livewire.procurement.award-decision', compact('quotes', 'existing'));
    }
}
