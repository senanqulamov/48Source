<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('SLA Tracker') }}</h1>
            <p class="ph-d">{{ __('Deadline monitoring and reminder dispatch for RFQ activity.') }}</p></div>
        <div class="ph-a">
            <button type="button" class="btn btn-p" wire:click="dispatchReminders" wire:loading.attr="disabled" wire:target="dispatchReminders">
                <x-source.icon name="bell" :size="15" />{{ __('Send reminders now') }}
            </button>
        </div>
    </header>
    <div class="strip">
        @foreach([
            __('Open RFQs') => $overview['open'] ?? 0,
            __('Due in 7 days') => $overview['due_7'] ?? 0,
            __('Due in 3 days') => $overview['due_3'] ?? 0,
            __('Due in 24 hours') => $overview['due_1'] ?? 0,
            __('Overdue') => $overview['overdue'] ?? 0,
        ] as $label => $value)
            <div class="strip-i"><span class="strip-l">{{ $label }}</span>
                <span class="strip-v mono">{{ $value }}</span>
                <span class="strip-s">{{ __('Requests') }}</span></div>
        @endforeach
    </div>
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Reminder rules') }}</h2></div>
        <div class="card-b"><div class="note">{{ __('Reminders are scheduled 7, 3, and 1 day before RFQ deadlines. Manual dispatch uses the same server-side workflow.') }}</div></div>
    </section>
</div>