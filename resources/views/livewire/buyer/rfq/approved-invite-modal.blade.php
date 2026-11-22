<x-modal :title="__('Invite More Suppliers')" wire="showInviteModal" blur="xl" size="2xl">
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                @lang('Select suppliers to invite for this RFQ. They will receive a notification.')
            </p>

            @if($this->availableSuppliers->isEmpty())
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <x-icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 dark:text-yellow-500"/>
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            @lang('All available suppliers have already been invited to this RFQ.')
                        </p>
                    </div>
                </div>
            @else
                <div>
                    <x-select.styled
                        label="{{ __('Select Suppliers') }}"
                        wire:model="selectedSuppliers"
                        :options="$this->availableSuppliers"
                        select="label:name|value:id"
                        searchable
                        multiple
                    />
                </div>

                @if(!empty($selectedSuppliers))
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            <strong>{{ count($selectedSuppliers) }}</strong> {{ __('supplier(s) selected') }}
                        </p>
                    </div>
                @endif
            @endif
        </div>

        <x-slot:footer>
            <div class="flex gap-3 justify-end">
                <x-button color="white" wire:click="closeInviteModal">
                    @lang('Cancel')
                </x-button>
                @if(!$this->availableSuppliers->isEmpty())
                    <x-button color="blue" wire:click="inviteSuppliers">
                        @lang('Send Invitations')
                    </x-button>
                @endif
            </div>
        </x-slot:footer>
    </x-modal>
