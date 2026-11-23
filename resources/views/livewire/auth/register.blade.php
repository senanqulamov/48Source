<div>
    <h1 class="auth-h">Register as a supplier</h1>
    <p class="auth-d">Create your company account. A buyer administrator reviews new suppliers before they can receive invitations.</p>
    <form class="auth-form" wire:submit="register" novalidate>
        <x-field label="Company name" for="company" name="company"><input id="company" class="input" wire:model="company" autofocus required></x-field>
        <div class="fgrid">
            <x-field label="Your name" for="name" name="name"><input id="name" class="input" wire:model="name" autocomplete="name" required></x-field>
            <x-field label="Work email" for="email" name="email"><input id="email" class="input" type="email" wire:model="email" autocomplete="username" required></x-field>
            <x-field label="City" for="city" name="city" :opt="true"><input id="city" class="input" wire:model="city"></x-field>
            <x-field label="Main category" for="cat" name="category_id" :opt="true"><select id="cat" class="select" wire:model="category_id"><option value="">Choose…</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            <x-field label="Password" for="password" name="password" help="At least 10 characters, with letters and numbers."><input id="password" class="input" type="password" wire:model="password" autocomplete="new-password" required></x-field>
            <x-field label="Repeat password" for="password2"><input id="password2" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password" required></x-field>
        </div>
        <button class="btn btn-p" type="submit" wire:loading.attr="disabled">Create account</button>
    </form>
    <div class="auth-links"><a class="link" href="{{ route('login') }}" wire:navigate>I already have an account</a></div>
</div>
