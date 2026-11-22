<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('System health') }}</h1>
            <p class="ph-d">{{ __('Infrastructure and application readiness checks.') }}</p></div>
        <div class="ph-a"><span class="badge b-info">{{ __('Live checks') }}</span>
            <button class="btn btn-p" type="button" wire:click="runChecks" wire:loading.attr="disabled" wire:target="runChecks">
                <x-source.icon name="pulse" :size="15" />{{ __('Refresh') }}
            </button></div>
    </header>
    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Application') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Environment') }}</dt><dd>{{ data_get($checks,'app.env','—') }}</dd>
                    <dt>{{ __('Debug') }}</dt><dd><span class="badge {{ data_get($checks,'app.debug') ? 'b-warn' : 'b-ok' }}">{{ data_get($checks,'app.debug') ? __('Enabled') : __('Disabled') }}</span></dd>
                    <dt>{{ __('Timezone') }}</dt><dd>{{ data_get($checks,'app.timezone','—') }}</dd>
                </dl></div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Storage and database') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Database connected') }}</dt><dd><span class="badge {{ data_get($checks,'database.connected') ? 'b-ok' : 'b-bad' }}">{{ data_get($checks,'database.connected') ? __('Yes') : __('No') }}</span></dd>
                    <dt>{{ __('Database driver') }}</dt><dd>{{ data_get($checks,'database.driver','—') }}</dd>
                    <dt>{{ __('Storage') }}</dt><dd><span class="badge {{ data_get($checks,'storage.writable') ? 'b-ok' : 'b-warn' }}">{{ data_get($checks,'storage.writable') ? __('Writable') : __('Check storage') }}</span></dd>
                </dl></div>
            </section>
        </div>
        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Cache and queue') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Cache connected') }}</dt><dd><span class="badge {{ data_get($checks,'cache.connected') ? 'b-ok' : 'b-bad' }}">{{ data_get($checks,'cache.connected') ? __('Yes') : __('No') }}</span></dd>
                    <dt>{{ __('Cache driver') }}</dt><dd>{{ data_get($checks,'cache.driver','—') }}</dd>
                    <dt>{{ __('Queue driver') }}</dt><dd>{{ data_get($checks,'queue.driver','—') }}</dd>
                    <dt>{{ __('Failed jobs') }}</dt><dd class="mono">{{ data_get($checks,'queue.failed',0) }}</dd>
                </dl></div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Scheduler') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Last run') }}</dt><dd class="mono">{{ data_get($checks,'scheduler.last_run','—') }}</dd>
                </dl></div>
            </section>
            <div class="note">{{ __('Health checks refresh automatically when the server-side polling component is active.') }}</div>
        </aside>
    </div>
</div>