<?php

namespace App\Livewire\Rfq;

use App\Livewire\Traits\Alert;
use App\Livewire\Traits\WithLogging;
use App\Models\Product;
use App\Models\SupplierInvitation;
use App\Events\SupplierInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    use Alert, WithLogging;

    public Request $request;

    /** Whether this form is in a modal (parity with Products\Create). */
    public bool $modal = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /** @var array<int,int> */
    public array $selectedSuppliers = [];

    public function mount(): void
    {
        $this->logPageView('RFQ Create');

        $this->modal = request()->routeIs('rfq.create');
        $this->request = new Request;
        $this->request->status = 'draft';
        $this->request->request_type = 'internal'; // Default to internal

        $this->items = [
            $this->makeEmptyItem(),
        ];
    }

    protected function makeEmptyItem(): array
    {
        return [
            'product_id' => null,
            'quantity' => 1,
            'specifications' => null,
        ];
    }

    public function addItem(): void
    {
        $this->items[] = $this->makeEmptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if (empty($this->items)) {
            $this->items[] = $this->makeEmptyItem();
        }
    }

    public function rules(): array
    {
        $user = Auth::user();
        $isAdmin = $user && $user->is_admin;

        $rules = [
            'request.title' => ['required', 'string', 'max:255'],
            'request.description' => ['nullable', 'string'],
            'request.deadline' => ['required', 'date', 'after:today'],
            'request.delivery_location' => ['nullable', 'string', 'max:255'],
            'request.delivery_address' => ['nullable', 'string'],
            'request.special_instructions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.specifications' => ['nullable', 'string'],
            'selectedSuppliers' => ['array'],
            'selectedSuppliers.*' => ['integer', Rule::exists('users', 'id')],
        ];

        // Add buyer_id validation for admins
        if ($isAdmin) {
            $rules['request.buyer_id'] = ['required', 'exists:users,id'];
            $rules['request.request_type'] = ['nullable', 'in:public,internal'];
        }

        return $rules;
    }

    public function save(): void
    {
        $actor = Auth::user();
        abort_unless($actor && $actor->can('create_rfqs'), 403);

        // The draft lifecycle and buyer assignment are controlled by the server.
        if (! $actor->isAdmin()) {
            $this->request->buyer_id = $actor->id;
        }
        $this->request->status = 'draft';
        $this->validate();

        $buyer = User::query()->whereKey($this->request->buyer_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'buyer'))
            ->where('is_active', true)->firstOrFail();

        $products = Product::query()->whereIn('id',
            collect($this->items)->pluck('product_id')->filter()->all())
            ->get(['id', 'name'])->keyBy('id');

        $selected = collect($this->selectedSuppliers)->map(fn ($id) => (int) $id)->unique();
        $suppliers = User::query()->whereIn('id', $selected)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'supplier'))
            ->get(['id', 'name']);

        if ($selected->count() !== $suppliers->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selectedSuppliers' => 'Invite only active supplier accounts.',
            ]);
        }

        if ($products->count() !== collect($this->items)->pluck('product_id')->unique()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'Every requested product must exist.',
            ]);
        }

        [$request, $invitations] = DB::transaction(function () use ($products, $suppliers) {
            $this->request->status = 'draft';
            $this->request->save();
            foreach ($this->items as $item) {
                $product = $products->get((int) $item['product_id']);
                RequestItem::create([
                    'request_id' => $this->request->id,
                    // request_items stores a product-name snapshot, not product_id.
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'specifications' => $item['specifications'] ?? null,
                ]);
            }

            $invitations = [];
            foreach ($suppliers as $supplier) {
                $invitations[] = SupplierInvitation::create([
                    'request_id' => $this->request->id,
                    'supplier_id' => $supplier->id,
                    'status' => 'pending',
                    'sent_at' => now(),
                ]);
            }
            return [$this->request, $invitations];
        });

        foreach ($invitations as $invitation) {
            event(new SupplierInvited($invitation, $actor));
        }

        $this->logCreate(Request::class, $request->id, [
            'title' => $request->title,
            'buyer_id' => $buyer->id,
            'items_count' => count($this->items),
            'suppliers_invited' => count($invitations),
        ]);

        $this->dispatch('created', id: $request->id);
        $this->success(__('Draft RFQ created successfully.'));
        $this->redirect(route('rfq.show', $request), navigate: true);
    }

    public function render(): View
    {
        $actor = Auth::user();
        $isAdmin = $actor?->isAdmin() ?? false;

        return view('livewire.rfq.create', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'buyers' => $isAdmin ? User::query()->whereHas('roles',
                fn ($query) => $query->where('name', 'buyer'))->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']) : collect(),
            'suppliers' => User::query()->whereHas('roles',
                fn ($query) => $query->where('name', 'supplier'))->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'company_name', 'city']),
            'isAdmin' => $isAdmin,
        ]);
    }
}
