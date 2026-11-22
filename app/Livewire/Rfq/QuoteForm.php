<?php

namespace App\Livewire\Rfq;

use App\Events\QuoteSubmitted;
use App\Livewire\Traits\Alert;
use App\Livewire\Traits\WithCalculation;
use App\Livewire\Traits\WithLogging;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Request;
use App\Models\SupplierInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class QuoteForm extends Component
{
    use Alert, WithCalculation, WithLogging;

    public Request $request;

    /**
     * Quote items - keyed by request_item_id
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    public ?string $notes = null;
    public ?string $terms_conditions = null;
    public ?string $valid_until = null;
    public string $currency = 'USD';

    public function mount(Request $request): void
    {
        $this->request = $request->load('items');

        $this->logPageView('RFQ Quote Form', [
            'request_id' => $this->request->id,
        ]);

        $user = Auth::user();

        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        if (! $user || (! $isAdmin && $user->id === $this->request->buyer_id)) {
            abort(403, 'Buyers cannot quote on their own RFQs.');
        }

        if (! $isAdmin && ($this->request->status !== 'open' || ($this->request->deadline && $this->request->deadline->isPast()))) {
            $this->error(__('This RFQ is not open for quotes.'));
            abort(403, 'This RFQ is not open for quotes.');
        }

        // Check if supplier already submitted a quote
        $existingQuote = Quote::where('request_id', $this->request->id)
            ->where('supplier_id', $user->id)
            ->first();

        if ($existingQuote) {
            $this->error(__('You have already submitted a quote for this RFQ.'));
            $this->redirect(route('rfq.show', $this->request));
            return;
        }

        // Initialize items from request items
        foreach ($this->request->items as $item) {
            $this->items[$item->id] = [
                'description' => $item->product_name ?? 'Product Item',
                'quantity' => $item->quantity,
                'unit_price' => null,
                'tax_rate' => 0,
                'notes' => null,
            ];
        }

        // Set default valid_until to 30 days from now
        $this->valid_until = now()->addDays(30)->format('Y-m-d');

        // Set currency from user or default
        $this->currency = $user->currency ?? 'USD';
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'valid_until' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_conditions' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function updated($propertyName): void
    {
        if (preg_match('/items\.\d+\.(quantity|unit_price|tax_rate)/', $propertyName)) {
            $this->triggerCalculationToast($propertyName);
        }
    }

    public function calculateTotal(): float
    {
        $total = 0;

        foreach ($this->items as $item) {
            if (isset($item['quantity']) && isset($item['unit_price'])) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $tax = $subtotal * (($item['tax_rate'] ?? 0) / 100);
                $total += $subtotal + $tax;
            }
        }

        return $total;
    }

    public function save(): void
    {
        $actor = Auth::user();
        abort_unless($actor && $this->request->status === 'open'
            && ($this->request->getAttribute('deadline') === null || $this->request->deadline->isFuture())
            && $actor->id !== $this->request->buyer_id, 403, 'RFQ is not open for quotation.');
        abort_if(Quote::query()->where('request_id', $this->request->id)
            ->where('supplier_id', $actor->id)->exists(), 409,
            'A quotation already exists for this supplier and RFQ.');

        $this->validate();

        $user = Auth::user();

        if (! $user) {
            $this->error(__('You must be logged in to submit a quote.'));
            return;
        }

        // The RFQ fixes requested line IDs and quantities. Never trust editable Livewire state.
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestItem> $requested */
        $requested = $this->request->items()->get()->keyBy('id');
        $provided = collect($this->items);
        $ids = $provided->map(fn (array $line, $key) => (int) ($line['request_item_id'] ?? $key));
        if ($requested->count() !== $provided->count() || $ids->unique()->count() !== $requested->count()
            || $ids->diff($requested->keys())->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Every requested RFQ line must be quoted exactly once.']);
        }
        foreach ($provided as $key => $line) {
            $id = (int) ($line['request_item_id'] ?? $key);
            $actual = $requested->get($id);
            if (! $actual || abs((float) ($line['quantity'] ?? 0) - (float) $actual->quantity) > 0.001) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'RFQ quantities cannot be changed by the supplier.']);
            }
        }

        $totalAmount = $this->calculateTotal();

        if ($totalAmount <= 0) {
            $this->error(__('Please provide valid pricing for all items.'));
            return;
        }

        // Find supplier invitation (if exists)
        $invitation = SupplierInvitation::where('request_id', $this->request->id)
            ->where('supplier_id', $user->id)
            ->first();

        $quote = \Illuminate\Support\Facades\DB::transaction(function () use ($requested, $user, $invitation, $totalAmount) {
            // Serialized on the RFQ row: prevents duplicate cross-entry submissions.
            $rfq = Request::query()->lockForUpdate()->findOrFail($this->request->id);
            abort_unless($rfq->status === 'open'
                && ($rfq->getAttribute('deadline') === null || $rfq->deadline->isFuture()), 403);
            abort_if(Quote::query()->where('request_id', $rfq->id)
                ->where('supplier_id', $user->id)->exists(), 409);

        // Create the quote
        $quote = Quote::create([
            'request_id' => $this->request->id,
            'supplier_id' => $user->id,
            'supplier_invitation_id' => $invitation?->id,
            'unit_price' => 0, // Legacy field - keep for backwards compatibility
            'total_price' => 0, // Legacy field - keep for backwards compatibility
            'total_amount' => $totalAmount,
            'currency' => $this->currency,
            'valid_until' => $this->valid_until,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Create quote items
        foreach ($this->items as $requestItemId => $item) {
            QuoteItem::create([
                'quote_id' => $quote->id,
                'request_item_id' => $requestItemId,
                'description' => $item['description'],
                'quantity' => $requested->get((int) $requestItemId)->quantity,
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'notes' => $item['notes'],
            ]);
        }

        // Update invitation status to 'quoted' if it exists
        if ($invitation) {
            $invitation->update([
                'status' => 'quoted',
                'responded_at' => now(),
            ]);
        }

            return $quote;
        });

        event(new QuoteSubmitted($quote, $user));

        $this->logCreate(Quote::class, $quote->id, [
            'request_id' => $this->request->id,
            'supplier_id' => $user->id,
            'total_amount' => $totalAmount,
        ]);

        $this->success(__('Quote submitted successfully!'));
        $this->redirect(route('rfq.show', $this->request));
    }

    public function render(): View
    {
        return view('livewire.rfq.quote-form');
    }
}
