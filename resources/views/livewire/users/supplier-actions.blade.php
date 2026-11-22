<div>
    {{-- Approve Modal --}}
    @if($approveModal)
    <div class="scrim source-dialog" wire:key="source-resources-views-livewire-users-supplier-actions-blade-php-0" wire:keydown.escape="$set('approveModal', false)" wire:click.self="$set('approveModal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Approve Supplier') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('approveModal', false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body">
        @if($supplier?->id)
            <div class="space-y-4">
                <p class="text-gray-700 dark:text-gray-300">
                    Are you sure you want to approve <strong>{{ $supplier->name }}</strong> as a supplier?
                </p>

                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">This will:</h4>
                    <ul class="list-disc list-inside text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <li>Activate the supplier account</li>
                        <li>Allow them to receive RFQ invitations</li>
                        <li>Enable quote submissions</li>
                        <li>Grant access to supplier portal</li>
                    </ul>
                </div>
            </div>

            
        @endif
    </div>
            <div class="ov-f"><x-button text="Cancel" wire:click="$set('approveModal', false)"/>
                <x-button text="Approve Supplier" wire:click="approveSupplier" color="green"/></div>
        </section>
    </div>
@endif

    {{-- Reject Modal --}}
    @if($rejectModal)
    <div class="scrim source-dialog" wire:key="source-resources-views-livewire-users-supplier-actions-blade-php-1" wire:keydown.escape="$set('rejectModal', false)" wire:click.self="$set('rejectModal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Reject Supplier') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('rejectModal', false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body">
        @if($supplier?->id)
            <div class="space-y-4">
                <p class="text-gray-700 dark:text-gray-300">
                    You are about to reject <strong>{{ $supplier->name }}</strong>'s supplier application.
                </p>

                <x-textarea
                    label="Rejection Reason *"
                    wire:model="rejectionReason"
                    hint="Provide a reason for rejection (will be added to notes)"
                    rows="4"
                    required
                />
            </div>

            
        @endif
    </div>
            <div class="ov-f"><x-button text="Cancel" wire:click="$set('rejectModal', false)"/>
                <x-button text="Reject Application" wire:click="rejectSupplier" color="red"/></div>
        </section>
    </div>
@endif

    {{-- Block Modal --}}
    @if($blockModal)
    <div class="scrim source-dialog" wire:key="source-resources-views-livewire-users-supplier-actions-blade-php-2" wire:keydown.escape="$set('blockModal', false)" wire:click.self="$set('blockModal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Block Supplier') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('blockModal', false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body">
        @if($supplier?->id)
            <div class="space-y-4">
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                    <p class="text-red-800 dark:text-red-200 font-medium">⚠️ {{ __('Warning')}}</p>
                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                        {!! __('Blocking :name will immediately suspend their account and prevent all activities.', ['name' => '<strong>' . e($supplier->name) . '</strong>']) !!}
                    </p>
                </div>

                <x-textarea
                    label="{{ __('Blocking Reason')}} *"
                    wire:model="blockReason"
                    hint="{{ __('Provide a reason for blocking this supplier')}}"
                    rows="4"
                    required
                />
            </div>

            
        @endif
    </div>
            <div class="ov-f"><x-button text="Cancel" wire:click="$set('blockModal', false)"/>
                <x-button text="Block Supplier" wire:click="blockSupplier" color="red"/></div>
        </section>
    </div>
@endif
</div>
