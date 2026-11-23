<div>
    <x-ph :title="$number ? 'Edit ' . $number : 'Create RFQ'" desc="Describe what you need, list the items and choose who can quote. You can save a draft at any step." :crumbs="[[auth()->user()->isAdmin() ? 'RFQs' : 'My RFQs', route('rfqs.index')], [$number ?? 'New RFQ']]">
        <a class="btn btn-g" href="{{ $number ? route('rfqs.show', $number) : route('rfqs.index') }}" wire:navigate>Discard</a>
    </x-ph>
    <ol class="steps" style="list-style:none;padding:0">
        @foreach ([1 => 'Request', 2 => 'Items', 3 => 'Suppliers', 4 => 'Review'] as $n => $label)
            <li style="display:contents"><button type="button" class="step {{ $step === $n ? 'on' : ($step > $n ? 'done' : '') }}" wire:click="go({{ $n }})" @if ($step === $n) aria-current="step" @endif><span class="sn">@if ($step > $n)<x-icon name="check" :size="12" />@else{{ $n }}@endif</span><span><b>{{ $label }}</b></span></button></li>
        @endforeach
    </ol>

    @if ($step === 1)
        <x-card title="Request information">
            <div class="fgrid">
                <x-field label="Title" for="title" name="f.title" :full="true"><input id="title" class="input @error('f.title') err @enderror" wire:model="f.title" placeholder="e.g. Structural steel - Warehouse 3 extension" autofocus></x-field>
                <div class="field full"><span class="lbl" id="dtl">Request type</span>
                    <div class="row wrap" role="radiogroup" aria-labelledby="dtl">
                        @foreach (['public' => ['globe', 'Public tender', 'Listed on the supplier portal. Any active supplier can quote; invited suppliers are notified directly.'], 'invited' => ['lock', 'Invited suppliers only', 'Visible only to the suppliers you invite. Use for framework call-offs and sensitive purchases.']] as $k => [$icon, $tl, $td])
                            <button type="button" class="radio-card {{ $f['type'] === $k ? 'on' : '' }}" role="radio" aria-checked="{{ $f['type'] === $k ? 'true' : 'false' }}" wire:click="$set('f.type', '{{ $k }}')"><span style="color:var({{ $f['type'] === $k ? '--primary' : '--text-3' }})"><x-icon :name="$f['type'] === $k ? 'check' : $icon" /></span><span><b>{{ $tl }}</b><span class="muted sm">{{ $td }}</span></span></button>
                        @endforeach
                    </div></div>
                <x-field label="Category" for="cat" name="f.category_id" :opt="true" help="Used to suggest suppliers in step 3."><select id="cat" class="select" wire:model="f.category_id"><option value="">No category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
                <x-field label="Quotation deadline" for="deadline" name="f.deadline_at" help="Quoting closes automatically at this time."><input id="deadline" class="input" type="datetime-local" min="{{ now()->format('Y-m-d') }}" wire:model="f.deadline_at"></x-field>
                <x-field label="Delivery location" for="loc" name="f.delivery_location" :opt="true"><input id="loc" class="input" wire:model="f.delivery_location" placeholder="Site, depot or office"></x-field>
                <x-field label="Delivery needed by" for="req" name="f.required_by" :opt="true"><input id="req" class="input" type="date" min="{{ now()->format('Y-m-d') }}" wire:model="f.required_by"></x-field>
                <x-field label="Budget" for="budget" name="f.budget" :opt="true" help="Not shown to suppliers."><input id="budget" class="input mono" inputmode="decimal" wire:model="f.budget" placeholder="0.00"></x-field>
                <x-field label="Currency" for="cur" name="f.currency"><select id="cur" class="select" wire:model="f.currency">@foreach (\App\Models\Currency::codes() as $c)<option>{{ $c }}</option>@endforeach</select></x-field>
                <x-field label="Payment terms" for="terms" name="f.payment_terms" :opt="true"><input id="terms" class="input" wire:model="f.payment_terms" placeholder="Net 30"></x-field>
                <x-field label="How quotations are scored" for="preset" name="f.scoring_preset" help="You can change this while comparing."><select id="preset" class="select" wire:model="f.scoring_preset">@foreach (\App\Services\QuoteEvaluator::PRESETS as $k => $p)<option value="{{ $k }}">{{ $p['label'] }} - price {{ $p['price'] }}%, quality {{ $p['quality'] }}%, delivery {{ $p['delivery'] }}%, terms {{ $p['terms'] }}%</option>@endforeach</select></x-field>
                <div class="field full"><label class="row" style="cursor:pointer"><input id="sealed" type="checkbox" class="chk" wire:model="f.sealed">Keep quotations sealed until the deadline</label><span class="help">Prices stay hidden from everyone until quoting closes.</span></div>
                <x-field label="Description" for="desc" :full="true" :opt="true"><textarea id="desc" class="textarea" rows="3" wire:model="f.description" placeholder="What is being purchased, and for which project or site?"></textarea></x-field>
                <x-field label="Requirements for suppliers" for="reqs" :full="true" :opt="true" help="Certificates, standards, packaging, site rules."><textarea id="reqs" class="textarea" rows="3" wire:model="f.requirements"></textarea></x-field>
            </div>
        </x-card>
    @elseif ($step === 2)
        <x-card title="Items" sub="One line per thing you want priced" :flush="true">
            <x-slot:act><button type="button" class="btn btn-sm" wire:click="addItem"><x-icon name="plus" :size="14" />Add item</button></x-slot:act>
            <div class="tbl-wrap"><table class="tbl compact"><thead><tr><th style="width:40px">#</th><th>Item</th><th style="width:140px">Quantity</th><th style="width:120px">Unit</th><th style="width:160px">Target unit price</th><th style="width:48px"></th></tr></thead><tbody>
                @foreach ($items as $i => $row)
                    <tr wire:key="item-{{ $i }}">
                        <td class="mono muted">{{ $i + 1 }}</td>
                        <td><input class="input" wire:model="items.{{ $i }}.name" aria-label="Item {{ $i + 1 }} name" placeholder="What is needed">@error("items.$i.name")<span class="errt">{{ $message }}</span>@enderror</td>
                        <td><input class="input mono" inputmode="decimal" wire:model="items.{{ $i }}.quantity" aria-label="Item {{ $i + 1 }} quantity" placeholder="0">@error("items.$i.quantity")<span class="errt">{{ $message }}</span>@enderror</td>
                        <td><input class="input" wire:model="items.{{ $i }}.unit" aria-label="Item {{ $i + 1 }} unit" list="units">@error("items.$i.unit")<span class="errt">{{ $message }}</span>@enderror</td>
                        <td><input class="input mono" inputmode="decimal" wire:model="items.{{ $i }}.target_price" aria-label="Item {{ $i + 1 }} target price" placeholder="optional">@error("items.$i.target_price")<span class="errt">{{ $message }}</span>@enderror</td>
                        <td><button type="button" class="icon-btn" wire:click="removeItem({{ $i }})" aria-label="Remove item {{ $i + 1 }}"><x-icon name="trash" :size="15" /></button></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <datalist id="units">@foreach (['pcs', 't', 'kg', 'm', 'm2', 'm3', 'bag', 'box', 'set', 'lot', 'l', 'h'] as $un)<option value="{{ $un }}">@endforeach</datalist>
            @error('items')<div class="card-b"><span class="errt">{{ $message }}</span></div>@enderror
        </x-card>
    @elseif ($step === 3)
        <x-card title="Suppliers to invite" :sub="count($suppliers) . ' chosen · only active suppliers are listed'" :flush="true">
            <div class="toolbar"><div class="dt-search" style="flex:1 1 240px;max-width:360px"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="find" placeholder="Search supplier or city" aria-label="Search suppliers"></div>
                @if ($f['type'] === 'public')<span class="muted sm">Public tender: any active supplier can also join without an invitation.</span>@endif</div>
            @error('suppliers')<div class="card-b" style="padding-bottom:0"><x-note kind="bad">{{ $message }}</x-note></div>@enderror
            @if ($supplierList->isEmpty())
                <x-empty icon="building" title="No active suppliers found" text="Approve a pending supplier or add one under Suppliers." />
            @else
                <ul class="list" style="max-height:440px;overflow:auto">
                    @foreach ($supplierList as $s)
                        <li wire:key="sup-{{ $s->id }}"><label class="row grow" style="gap:12px;cursor:pointer"><input type="checkbox" class="chk" value="{{ $s->id }}" wire:model.live="suppliers"><div class="grow"><div class="t1">{{ $s->name }}</div><div class="muted sm">{{ $s->category?->name ?? 'No category' }}@if ($s->city) · {{ $s->city }}@endif</div></div>@if ((string) $s->category_id === (string) $f['category_id'] && $f['category_id'] !== '')<span class="tag">Same category</span>@endif<span class="mono sm">{{ $s->rating() }} ★</span></label></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @else
        <div class="grid g-2-1">
            <div class="stack">
                <x-card title="Review">
                    <dl class="kv"><dt>Title</dt><dd>{{ $f['title'] ?: '-' }}</dd><dt>Who can quote</dt><dd>{{ \App\Models\Rfq::TYPES[$f['type']] }}</dd><dt>Category</dt><dd>{{ $categories[$f['category_id']] ?? '-' }}</dd><dt>Quotation deadline</dt><dd>{{ $f['deadline_at'] ? \Illuminate\Support\Carbon::parse($f['deadline_at'])->format('j M Y, H:i') : '-' }}</dd><dt>Currency</dt><dd>{{ $f['currency'] }}</dd><dt>Budget</dt><dd>{{ $f['budget'] !== '' ? $f['budget'] . ' ' . $f['currency'] : '-' }}</dd><dt>Payment terms</dt><dd>{{ $f['payment_terms'] ?: '-' }}</dd><dt>Sealed bids</dt><dd>{{ $f['sealed'] ? 'Yes' : 'No' }}</dd></dl>
                </x-card>
                <x-card title="Items" :sub="count($items) . ' line(s)'" :flush="true">
                    <div class="tbl-wrap"><table class="tbl compact"><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Target price</th></tr></thead><tbody>@foreach ($items as $row)<tr><td class="t1">{{ $row['name'] ?: '-' }}</td><td class="num mono">{{ $row['quantity'] }} {{ $row['unit'] }}</td><td class="num mono">{{ $row['target_price'] ?: '-' }}</td></tr>@endforeach</tbody></table></div>
                </x-card>
            </div>
            <x-card title="Invited suppliers" :sub="$chosen->count() . ' chosen'" :flush="true">
                @if ($chosen->isEmpty())<div class="card-b faint">None chosen.</div>@else<ul class="list">@foreach ($chosen as $s)<li><div class="grow"><div class="t1">{{ $s->name }}</div><div class="muted sm">{{ $s->city }}</div></div></li>@endforeach</ul>@endif
            </x-card>
        </div>
        @if ($errors->any())<div style="margin-top:16px"><x-note kind="bad"><b>Fix these before publishing:</b><ul style="margin:6px 0 0 18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-note></div>@endif
    @endif

    <div class="row" style="margin-top:18px">
        @if ($step > 1)<button type="button" class="btn" wire:click="go({{ $step - 1 }})"><x-icon name="left" />Back</button>@endif
        <span class="sp"></span>
        <button type="button" class="btn" wire:click="saveDraft" wire:loading.attr="disabled">Save draft</button>
        @if ($step < 4)<button type="button" class="btn btn-p" wire:click="go({{ $step + 1 }})">Continue<x-icon name="right" /></button>
        @else<button type="button" class="btn btn-p" wire:click="publish" wire:loading.attr="disabled"><x-icon name="send" />Publish and notify suppliers</button>@endif
    </div>
</div>
