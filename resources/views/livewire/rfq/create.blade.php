<div x-data="{ step: 1 }">
    @unless($modal)
        <button type="button" class="btn btn-p" wire:click="$set('modal',true)" x-on:click="step=1">
            <x-source.icon name="plus" :size="15"/>{{ __('Create an RFQ') }}
        </button>
    @endunless
    @if($modal)
        <div class="scrim source-dialog" wire:key="admin-rfq-create-wizard"
             wire:keydown.escape="$set('modal',false)" wire:click.self="$set('modal',false)">
            <section class="modal wide source-rfq-wizard" role="dialog" aria-modal="true" aria-labelledby="admin-rfq-wizard-title">
                <div class="ov-h"><h2 id="admin-rfq-wizard-title">{{ __('Create new RFQ') }}</h2>
                    <span class="sp"></span><button class="icon-btn" type="button" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x"/></button></div>
                <div class="ov-b stack">
                    <div class="steps" role="group" aria-label="{{ __('RFQ creation progress') }}">
                        @foreach([1=>__('Details'),2=>__('Items'),3=>__('Suppliers'),4=>__('Review')] as $n=>$label)
                            <button type="button" class="step" :class="{on:step==={{ $n }},done:step>{{ $n }}}"
                                x-on:click="if({{ $n }}<step)step={{ $n }}" :aria-current="step==={{ $n }}?'step':null">
                                <span class="sn">{{ $n }}</span><span>{{ $label }}</span></button>
                        @endforeach
                    </div>
                    <form id="admin-rfq-wizard-form" wire:submit="save"
                          x-on:keydown.enter="if(step<4&&$event.target.tagName!=='TEXTAREA')$event.preventDefault()">
                        <div class="stack" data-wizard-step="1" x-show="step===1">
                            <div class="note">{{ __('Step 1 — Define the request and select its buyer.') }}</div>
                            <div class="card"><div class="card-h"><h3 class="card-t">{{ __('Request information') }}</h3></div>
                                <div class="card-b fgrid">
                                    <div class="field"><label for="admin-rfq-title">{{ __('Title') }} *</label>
                                        <input id="admin-rfq-title" class="input" maxlength="255" wire:model.blur="request.title" required />
                                        @error('request.title')<span class="err">{{ $message }}</span>@enderror</div>
                                    <div class="field"><label for="admin-rfq-deadline">{{ __('Quotation deadline') }} *</label>
                                        <input id="admin-rfq-deadline" class="input" type="date" min="{{ now()->addDay()->toDateString() }}" wire:model.change="request.deadline" required />
                                        @error('request.deadline')<span class="err">{{ $message }}</span>@enderror</div>
                                    @if($isAdmin)
                                        <div class="field"><label for="admin-rfq-buyer">{{ __('Buyer') }} *</label>
                                            <select id="admin-rfq-buyer" class="select" wire:model.change="request.buyer_id" required>
                                                <option value="">{{ __('Select a buyer') }}</option>
                                                @foreach($buyers as $buyer)<option value="{{ $buyer->id }}">{{ $buyer->name }}</option>@endforeach
                                            </select>@error('request.buyer_id')<span class="err">{{ $message }}</span>@enderror</div>
                                        <div class="field"><label for="admin-rfq-type">{{ __('Request type') }}</label>
                                            <select id="admin-rfq-type" class="select" wire:model.change="request.request_type">
                                                <option value="internal">{{ __('Internal request') }}</option>
                                                <option value="public">{{ __('Public tender') }}</option>
                                            </select></div>
                                    @endif
                                    <div class="field"><label for="admin-rfq-location">{{ __('Delivery location') }}</label>
                                        <input id="admin-rfq-location" class="input" wire:model.blur="request.delivery_location"/></div>
                                    <div class="field"><label for="admin-rfq-address">{{ __('Delivery address') }}</label>
                                        <input id="admin-rfq-address" class="input" wire:model.blur="request.delivery_address"/></div>
                                    <div class="field" style="grid-column:1/-1"><label for="admin-rfq-description">{{ __('Requirements') }}</label>
                                        <textarea id="admin-rfq-description" class="textarea" rows="3" wire:model.blur="request.description"></textarea></div>
                                    <div class="field" style="grid-column:1/-1"><label for="admin-rfq-instructions">{{ __('Special instructions') }}</label>
                                        <textarea id="admin-rfq-instructions" class="textarea" rows="2" wire:model.blur="request.special_instructions"></textarea></div>
                                </div>
                            </div>
                        </div>
                        <div class="stack" data-wizard-step="2" x-show="step===2" x-cloak>
                            <div class="note">{{ __('Step 2 — Specify products, quantities and requirements.') }}</div>
                            <div class="card"><div class="card-h"><h3 class="card-t">{{ __('Requested items') }}</h3><span class="sp"></span>
                                <button type="button" class="btn btn-sm" wire:click="addItem"><x-source.icon name="plus" :size="14"/>{{ __('Add item') }}</button></div>
                                <div class="card-b stack">
                                    @foreach($items as $index => $item)
                                        <div class="source-item-editor" wire:key="admin-rfq-item-{{ $index }}">
                                            <div class="row" style="justify-content:space-between"><strong>{{ __('Item') }} {{ $index+1 }}</strong>
                                                @if(count($items)>1)<button type="button" class="btn btn-sm" wire:click="removeItem({{ $index }})">{{ __('Remove') }}</button>@endif</div>
                                            <div class="fgrid">
                                                <div class="field"><label for="admin-rfq-product-{{ $index }}">{{ __('Product') }} *</label>
                                                    <select id="admin-rfq-product-{{ $index }}" class="select" wire:model.change="items.{{ $index }}.product_id" required>
                                                        <option value="">{{ __('Select product') }}</option>
                                                        @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                                                    </select>@error("items.$index.product_id")<span class="err">{{ $message }}</span>@enderror</div>
                                                <div class="field"><label for="admin-rfq-qty-{{ $index }}">{{ __('Quantity') }} *</label>
                                                    <input id="admin-rfq-qty-{{ $index }}" class="input mono" type="number" min="1" step="1" wire:model.change="items.{{ $index }}.quantity" required/>
                                                    @error("items.$index.quantity")<span class="err">{{ $message }}</span>@enderror</div>
                                                <div class="field" style="grid-column:1/-1"><label for="admin-rfq-spec-{{ $index }}">{{ __('Specifications') }}</label>
                                                    <textarea id="admin-rfq-spec-{{ $index }}" class="textarea" wire:model.blur="items.{{ $index }}.specifications" rows="2"></textarea></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="stack" data-wizard-step="3" x-show="step===3" x-cloak>
                            <div class="note">{{ __('Step 3 — Optionally invite active supplier accounts. Invitations are stored and sent after saving.') }}</div>
                            <div class="card"><div class="card-h"><h3 class="card-t">{{ __('Supplier invitations') }}</h3>
                                <span class="card-s">{{ count($selectedSuppliers) }} {{ __('selected') }}</span></div>
                                <div class="card-b stack">
                                    @forelse($suppliers as $supplier)
                                        <label class="row" style="gap:12px;cursor:pointer">
                                            <input class="chk" type="checkbox" value="{{ $supplier->id }}" wire:model.change="selectedSuppliers"/>
                                            <span><strong class="sm">{{ $supplier->company_name ?: $supplier->name }}</strong>
                                            <span class="sm muted">{{ $supplier->city ? ' · '.$supplier->city : '' }}</span></span>
                                        </label>
                                    @empty<div class="empty">{{ __('No active suppliers available') }}</div>@endforelse
                                    @error('selectedSuppliers')<span class="err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="stack" data-wizard-step="4" x-show="step===4" x-cloak>
                            <div class="note">{{ __('Step 4 — Review the draft. This action creates real request items and invitations.') }}</div>
                            <div class="card"><div class="card-h"><h3 class="card-t">{{ __('Review') }}</h3></div>
                                <div class="card-b"><dl class="kv">
                                    <dt>{{ __('Title') }}</dt><dd>{{ $request->title ?: '—' }}</dd>
                                    <dt>{{ __('Deadline') }}</dt><dd>{{ $request->deadline?->format('d M Y') ?? '—' }}</dd>
                                    <dt>{{ __('Buyer') }}</dt><dd>{{ $buyers->firstWhere('id',$request->buyer_id)?->name ?? auth()->user()?->name ?? '—' }}</dd>
                                    <dt>{{ __('Items') }}</dt><dd>{{ count($items) }}</dd>
                                    <dt>{{ __('Invited suppliers') }}</dt><dd>{{ count($selectedSuppliers) }}</dd>
                                    <dt>{{ __('Initial status') }}</dt><dd><span class="badge">{{ __('Draft') }}</span></dd>
                                </dl></div></div>
                            @if($errors->any())<div class="note bad" role="alert">{{ __('Correct the highlighted fields before saving.') }}</div>@endif
                        </div>
                    </form>
                </div>
                <div class="ov-f"><div class="row" style="justify-content:space-between;width:100%;gap:10px">
                    <button type="button" class="btn" x-on:click="step=Math.max(1,step-1)" x-show="step>1">{{ __('Back') }}</button>
                    <span class="sp"></span>
                    <button type="button" class="btn btn-p" x-show="step<4"
                        x-on:click="let section=$el.closest('[role=dialog]').querySelector('[data-wizard-step=&quot;'+step+'&quot;]');let invalid=section?.querySelector('input:invalid,select:invalid,textarea:invalid');if(invalid){invalid.reportValidity();return;}step=Math.min(4,step+1)">{{ __('Continue') }}</button>
                    <button type="submit" class="btn btn-p" form="admin-rfq-wizard-form" x-show="step===4" wire:loading.attr="disabled">{{ __('Create draft RFQ') }}</button>
                </div></div>
            </section>
        </div>
    @endif
</div>