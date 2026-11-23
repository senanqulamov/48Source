<x-layouts.app title="Privacy">
    <x-ph title="Privacy and your data" desc="What 48Source stores about you and who can see it." :crumbs="[['Privacy']]" />
    <div class="stack" style="max-width:820px">
        <x-card title="What is stored"><ul style="margin:0;padding-left:18px;display:grid;gap:6px"><li>Your name, work email, phone, role and the company or store you belong to.</li><li>What you do in the system: requests, quotations, orders, approvals and messages, with the time.</li><li>Your password is stored only as a one-way hash. Nobody can read it, including administrators.</li><li>Sign-in codes are kept as a hash for ten minutes and then deleted.</li></ul></x-card>
        <x-card title="Who can see it"><ul style="margin:0;padding-left:18px;display:grid;gap:6px"><li>A supplier never sees another supplier's quotations, prices, files or messages.</li><li>Buyers see their own requests and orders; administrators see all of them.</li><li>Uploaded files are kept on a private disk and are only served after an access check.</li><li>The activity log records who did what. It cannot be edited.</li></ul></x-card>
        <x-card title="Your choices"><ul style="margin:0;padding-left:18px;display:grid;gap:6px"><li>Change your name, phone, language and password under <a class="link" href="{{ route('settings') }}" wire:navigate>Settings</a>.</li><li>Switch email copies of notifications on or off, and turn on a sign-in code by email.</li><li>To have your account closed or your data exported, ask an administrator of your organisation.</li></ul>
            <p class="muted sm" style="margin-top:12px">This page describes how the software works. The organisation that runs it is responsible for its own privacy notice and retention rules.</p></x-card>
    </div>
</x-layouts.app>
