@php $quick = ['' => ['All', $counts->sum()], 'sent' => ['Sent', $counts['sent'] ?? 0], 'failed' => ['Failed', $counts['failed'] ?? 0], 'queued' => ['Waiting', $counts['queued'] ?? 0]]; @endphp
<div>
    <x-ph title="Email outbox" desc="Every email the system wrote, and whether it went out." :crumbs="[['Email outbox']]" />
    <div style="margin-bottom:16px"><x-note :kind="$on ? ($mailer === 'log' ? 'warn' : 'ok') : 'info'">@if (! $on)<b>Email is switched off.</b> People only get notifications inside the app. Switch it on under Settings.@elseif ($mailer === 'log')<b>Mail is written to the log file, not delivered.</b> Set <span class="mono">MAIL_MAILER</span> and the SMTP details in <span class="mono">.env</span> to send real email.@else<b>Email is on.</b> Mailer: <span class="mono">{{ $mailer }}</span>.@endif</x-note></div>
    <x-dt :lw="$this" :rows="$rows" id="ob" search="Search recipient or subject" label="Search emails" noun="emails" :all="$all" :quick="$quick" :facets="['area' => ['Area', $areas]]">
        <x-slot:empty><x-empty icon="mail" title="No emails yet" text="Emails appear here as notifications are sent." /></x-slot:empty>
        <x-slot:head><x-th col="created_at" :sort="$sort ?: 'created_at'">When</x-th><x-th col="to">To</x-th><th>Subject</th><th class="hide-sm">Area</th><x-th col="status">Status</x-th><th style="width:100px"></th></x-slot:head>
        @foreach ($rows as $m)
            <tr wire:key="ob-{{ $m->id }}" class="click" wire:click="show({{ $m->id }})" tabindex="0" wire:keydown.enter="show({{ $m->id }})"><td class="muted" style="white-space:nowrap">{{ $m->created_at->format('j M, H:i') }}</td><td>{{ $m->to }}</td><td class="tmain"><div class="t1 trunc" style="max-width:520px">{{ $m->subject }}</div></td><td class="hide-sm"><span class="tag">{{ $m->area }}</span></td>
                <td><x-badge :status="['sent' => 'ok', 'failed' => 'bad', 'queued' => 'warn'][$m->status]" :label="$m->status === 'queued' ? 'Waiting' : ucfirst($m->status)" /></td><td class="num">@if ($m->status !== 'sent')@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click.stop="resend({{ $m->id }})">Send again</button>@endperm @endif</td></tr>
        @endforeach
    </x-dt>
    @if ($current)
        <x-drawer :title="$current->subject"><dl class="kv" style="grid-template-columns:110px 1fr;margin-bottom:14px"><dt>To</dt><dd>{{ $current->to }}</dd><dt>Written</dt><dd>{{ $current->created_at->format('j M Y, H:i') }}</dd><dt>Sent</dt><dd>{{ $current->sent_at?->format('j M Y, H:i') ?? 'Not sent' }}</dd><dt>Area</dt><dd>{{ $current->area }}</dd>@if ($current->error)<dt>Error</dt><dd style="color:var(--bad)">{{ $current->error }}</dd>@endif</dl>
            <div style="padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--surface-2);white-space:pre-line">{{ $current->body }}@if ($current->url)

Open in 48Source: {{ url($current->url) }}@endif</div>
            <x-slot:foot><span class="sp"></span>@perm('system', 'E')<button type="button" class="btn" wire:click="resend({{ $current->id }})"><x-icon name="send" />Send again</button>@endperm</x-slot:foot></x-drawer>
    @endif
</div>
