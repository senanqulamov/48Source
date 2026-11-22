<div x-data="{step:1}">
    @if(config('source48.redesign_enabled'))
        <x-modal :title="__('Create new RFQ')" wire size="4xl" blur="xl">
            <form id="buyer-rfq-create" wire:submit="save"
                  x-on:keydown.enter="if (step < 4 && $event.target.tagName !== 'TEXTAREA') $event.preventDefault()">
                <div class="steps" aria-label="{{ __('RFQ creation progress') }}">
                    @foreach([1=>__('Details'),2=>__('Items'),3=>__('Suppliers'),4=>__('Review')] as $number => $label)
                        <button type="button" class="step" :class="{on:step==={{ $number }},done:step>{{ $number }}}"
                                x-on:click="if({{ $number }} < step) step={{ $number }}"
                                :aria-current="step==={{ $number }} ? 'step' : null">
                            <span class="sn">{{ $number }}</span><span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="stack" data-wizard-step="1" x-show="step===1">
                    <div class="note">{{ __('Step 1 of 4 — Give the request a clear title and a deadline. Only saved requests appear in the application.') }}</div>
                    <div class="card">
                        <div class="card-h"><h3 class="card-t">{{ __('RFQ information') }}</h3></div>
                        <div class="card-b fgrid">
                            <div class="field">
                                <label for="rfq-w-title">{{ __('Request title') }} *</label>
                                <input id="rfq-w-title" class="input" type="text" maxlength="255"
                                       wire:model.live="request.title" autocomplete="off" required
                                       placeholder="{{ __('e.g. Reinforcement steel for North Site') }}">
                                @error('request.title')<p class="err">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="rfq-w-deadline">{{ __('Quotation deadline') }} *</label>
                                <input id="rfq-w-deadline" class="input" type="date" wire:model.live="request.deadline"
                                       min="{{ now()->addDay()->toDateString() }}" required>
                                @error('request.deadline')<p class="err">{{ $message }}</p>@enderror
                            </div>
                            <div class="field" style="grid-column:1/-1">
                                <label for="rfq-w-description">{{ __('Scope and requirements') }}</label>
                                <textarea id="rfq-w-description" class="textarea" rows="4"
                                          wire:model.live="request.description"
                                          placeholder="{{ __('Explain what is being purchased and any important requirements.') }}"></textarea>
                                @error('request.description')<p class="err">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stack" data-wizard-step="2" x-show="step===2" x-cloak>
                    <div class="note">{{ __('Step 2 of 4 — Add the requested products and quantities. At least one valid item is required.') }}</div>
                    <div class="card">
                        <div class="card-h">
                            <h3 class="card-t">{{ __('Requested items') }}</h3>
                            <span class="card-s">{{ count($items) }} {{ __('items') }}</span><span class="sp"></span>
                            <button type="button" class="btn btn-sm" wire:click="addItem">
                                <x-source.icon name="plus" :size="14" />{{ __('Add item') }}
                            </button>
                        </div>
                        <div class="card-b stack">
                            @foreach($items as $index => $item)
                                <div class="card" wire:key="buyer-rfq-wizard-item-{{ $index }}-{{ $item['category_id'] ?? 'none' }}">
                                    <div class="card-b stack">
                                        <div class="row" style="justify-content:space-between">
                                            <strong class="sm">{{ __('Item') }} {{ $index + 1 }}</strong>
                                            @if(count($items)>1)
                                                <button class="btn btn-sm" type="button" wire:click="removeItem({{ $index }})">
                                                    <x-source.icon name="trash" :size="14" />{{ __('Remove') }}
                                                </button>
                                            @endif
                                        </div>
                                        <div class="fgrid">
                                            <div class="field">
                                                <label for="rfq-cat-{{ $index }}">{{ __('Category') }}</label>
                                                <select id="rfq-cat-{{ $index }}" class="select" wire:model.live="items.{{ $index }}.category_id">
                                                    <option value="">{{ __('Select category') }}</option>
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label for="rfq-product-{{ $index }}">{{ __('Catalog product') }}</label>
                                                <select id="rfq-product-{{ $index }}" class="select" wire:model.live="items.{{ $index }}.product_id"
                                                        wire:key="rfq-product-choice-{{ $index }}-{{ $item['category_id'] ?? 'none' }}">
                                                    <option value="">{{ __('Enter custom product below') }}</option>
                                                    @foreach($this->getProductsForCategory($item['category_id'] ?? null) as $product)
                                                        <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label for="rfq-item-name-{{ $index }}">{{ __('Item name') }} *</label>
                                                <input id="rfq-item-name-{{ $index }}" class="input" type="text"
                                                       maxlength="255" wire:model.live="items.{{ $index }}.product_name"
                                                       placeholder="{{ __('Product or service name') }}" required>
                                                @error("items.$index.product_name")<p class="err">{{ $message }}</p>@enderror
                                            </div>
                                            <div class="field">
                                                <label for="rfq-item-qty-{{ $index }}">{{ __('Quantity') }} *</label>
                                                <input id="rfq-item-qty-{{ $index }}" class="input mono" type="number"
                                                       min="1" step="1" wire:model.live="items.{{ $index }}.quantity" required>
                                                @error("items.$index.quantity")<p class="err">{{ $message }}</p>@enderror
                                            </div>
                                            <div class="field" style="grid-column:1/-1">
                                                <label for="rfq-item-spec-{{ $index }}">{{ __('Specifications and notes') }}</label>
                                                <textarea id="rfq-item-spec-{{ $index }}" class="textarea" rows="2"
                                                          wire:model.live="items.{{ $index }}.specifications"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="stack" data-wizard-step="3" x-show="step===3" x-cloak>
                    <div class="note">{{ __('Step 3 of 4 — Optionally invite eligible active suppliers. Invitations are only sent after you create the RFQ.') }}</div>
                    <div class="card">
                        <div class="card-h"><h3 class="card-t">{{ __('Supplier invitations') }}</h3><span class="card-s">{{ count($selectedSuppliers) }} {{ __('selected') }}</span></div>
                        <div class="card-b stack">
                            <label class="sm muted">{{ __('Choose suppliers') }}</label>
                            @forelse($suppliers as $supplier)
                                <label class="row" style="gap:12px;cursor:pointer">
                                    <input class="chk" type="checkbox" value="{{ $supplier->id }}" wire:model.live="selectedSuppliers">
                                    <span><strong class="sm">{{ $supplier->company_name ?: $supplier->name }}</strong>
                                    <span class="muted sm">{{ $supplier->city ? ' · '.$supplier->city : '' }}</span></span>
                                </label>
                            @empty
                                <div class="empty">{{ __('No active suppliers are currently available.') }}</div>
                            @endforelse
                            <p class="help">{{ __('Category-based supplier ranking is not yet available. This list contains active suppliers only.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="stack" data-wizard-step="4" x-show="step===4" x-cloak>
                    <div class="note">{{ __('Step 4 of 4 — Review all information. This action creates a draft RFQ using the current backend workflow.') }}</div>
                    <div class="card">
                        <div class="card-h"><h3 class="card-t">{{ __('Review request') }}</h3></div>
                        <div class="card-b">
                            <dl class="kv">
                                <dt>{{ __('Title') }}</dt><dd>{{ $request->title ?: '—' }}</dd>
                                <dt>{{ __('Deadline') }}</dt><dd>{{ $request->deadline ? \Illuminate\Support\Carbon::parse($request->deadline)->format('d M Y') : '—' }}</dd>
                                <dt>{{ __('Items') }}</dt><dd>{{ count($items) }}</dd>
                                <dt>{{ __('Suppliers invited') }}</dt><dd>{{ count($selectedSuppliers) }}</dd>
                                <dt>{{ __('Initial status') }}</dt><dd><span class="badge">{{ __('Draft') }}</span></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-h"><h3 class="card-t">{{ __('Review item list') }}</h3></div>
                        <div class="tbl-wrap">
                            <table class="tbl compact">
                                <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Specifications') }}</th></tr></thead>
                                <tbody>
                                    @foreach($items as $item)
                                        <tr><td>{{ $item['product_name'] ?: '—' }}</td>
                                        <td class="num mono">{{ $item['quantity'] }}</td>
                                        <td class="sm muted">{{ $item['specifications'] ?: '—' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if($errors->any())
                        <div class="note bad" role="alert">{{ __('Please correct the highlighted fields before creating the RFQ.') }}</div>
                    @endif
                </div>
            </form>
            <x-slot:footer>
                <div class="row" style="justify-content:space-between;width:100%;gap:10px">
                    <button type="button" class="btn" x-on:click="step=Math.max(1,step-1)" x-show="step>1">{{ __('Back') }}</button>
                    <span class="sp"></span>
                    <button x-ref="nextStep" type="button" class="btn btn-p" x-show="step<4"
                        x-on:click="
                            let area=$el.closest('[role=dialog]') || $el.closest('.modal') || document;
                            let section=area.querySelector('[data-wizard-step=&quot;'+step+'&quot;]');
                            let fields=section ? Array.from(section.querySelectorAll('input[required],select[required]')) : [];
                            let invalid=fields.find(el => !el.checkValidity());
                            if(invalid){invalid.reportValidity();return;}
                            step=Math.min(4,step+1);
                        ">{{ __('Continue') }}</button>
                    <button type="submit" form="buyer-rfq-create" class="btn btn-p"
                            x-show="step===4" wire:loading.attr="disabled" wire:target="save">{{ __('Create draft RFQ') }}</button>
                </div>
            </x-slot:footer>
        </x-modal>
    @else
<x-modal :title="__('Create New RFQ')" wire blur="xl" size="3xl">
        <form id="buyer-rfq-create" wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input
                    label="{{ __('Title') }} *"
                    wire:model.defer="request.title"
                    required
                />

                <x-date
                    label="{{ __('Deadline') }} *"
                    wire:model.defer="request.deadline"
                    required
                />
            </div>

            <div>
                <x-textarea
                    label="{{ __('Description') }}"
                    wire:model.defer="request.description"
                    rows="4"
                />
            </div>

            <div class="border-t pt-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100">
                        @lang('Items') *
                    </h3>
                    <x-button
                        type="button"
                        wire:click="addItem"
                        text="{{ __('Add Item') }}"
                        icon="plus"
                        sm
                    />
                </div>

                <div class="space-y-4">
                    @foreach($items as $index => $item)
                        <div
                            class="border border-gray-300 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800"
                            wire:key="buyer-rfq-item-{{ $index }}-{{ $item['category_id'] ?? 'new' }}">

                            {{-- Category and Product Selection --}}
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4">
                                <div class="md:col-span-6">
                                    <x-select.styled
                                        label="{{ __('Category') }} *"
                                        wire:model.live="items.{{ $index }}.category_id"
                                        :options="$categories"
                                        select="label:name|value:id"
                                        searchable
                                        required
                                        placeholder="{{ __('Select category...') }}"
                                    />
                                </div>

                                <div class="md:col-span-6">
                                    <x-select.styled
                                        wire:key="product-select-{{ $index }}-{{ $item['category_id'] ?? 'none' }}"
                                        label="{{ __('Select Product') }}"
                                        wire:model.live="items.{{ $index }}.product_id"
                                        :options="$this->getProductsForCategory($item['category_id'] ?? null)"
                                        select="label:name|value:id"
                                        searchable
                                        placeholder="{{ __('Select from category...') }}"
                                    />
                                </div>
                            </div>

                            {{-- Manual Product Name Entry --}}
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4">
                                <div class="md:col-span-12">
                                    <x-input
                                        label="{{ __('Product Name') }} *"
                                        wire:model.live="items.{{ $index }}.product_name"
                                        placeholder="{{ __('Enter product name or select from dropdown above') }}"
                                        required
                                    />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Select from category dropdown or type custom product name') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Quantity and Specifications --}}
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4">
                                <div class="md:col-span-6">
                                    <x-number
                                        label="{{ __('Quantity') }} *"
                                        wire:model="items.{{ $index }}.quantity"
                                        min="1"
                                        required
                                    />
                                </div>

                                <div class="md:col-span-6">
                                    <x-input
                                        label="{{ __('Specifications / Notes') }}"
                                        wire:model="items.{{ $index }}.specifications"
                                    />
                                </div>
                            </div>

                            {{-- Remove Button --}}
                            <div class="flex justify-end pt-2 border-t border-gray-200 dark:border-gray-700">
                                @if(count($items) > 1)
                                    <x-button.circle
                                        type="button"
                                        wire:click="removeItem({{ $index }})"
                                        icon="trash"
                                        color="red"
                                        xs
                                    />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t pt-4">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">
                    @lang('Invite Suppliers') ({{ __('Optional') }})
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    @lang('Select suppliers to invite for this RFQ. They will receive a notification.')
                </p>

                <div>
                    <x-select.styled
                        label="{{ __('Select Suppliers') }}"
                        wire:model="selectedSuppliers"
                        :options="$suppliers"
                        select="label:name|value:id"
                        searchable
                        multiple
                    />
                </div>

                @if(!empty($selectedSuppliers))
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        <strong>{{ count($selectedSuppliers) }}</strong> {{ __('supplier(s) selected') }}
                    </div>
                @endif
            </div>
        </form>
        <x-slot:footer>
            <x-button type="submit" form="buyer-rfq-create">
                @lang('Create RFQ')
            </x-button>
        </x-slot:footer>
    </x-modal>
    @endif
</div>
