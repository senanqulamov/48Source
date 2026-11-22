<div class="source-ui" data-theme="light">
    <div class="login">
        <main class="login-form">
            <div class="row" style="margin-bottom:8px">
                <span class="mark" aria-hidden="true">48</span>
                <span style="font-size:18px;font-weight:600;letter-spacing:-.01em">48Source</span>
            </div>
            <div>
                <h1 class="ph-t">{{ __('Sign in to your workspace') }}</h1>
                <p class="ph-d">{{ __('Use your work email to sign in.') }}</p>
            </div>
            @if($errors->any())
                <div class="note bad" role="alert">
                    <x-source.icon name="alert" :size="16" />
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif
            <form method="POST" action="{{ route('login') }}" class="stack">
                @csrf
                <div class="field">
                    <label for="email">{{ __('Work email') }}</label>
                    <input class="input @error('email') bad @enderror" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus />
                </div>
                <div class="field">
                    <label for="password">{{ __('Password') }}</label>
                    <input class="input @error('password') bad @enderror" id="password" type="password" name="password" autocomplete="current-password" required />
                </div>
                <label class="row" style="cursor:pointer">
                    <input type="checkbox" name="remember" class="chk" value="1" {{ old('remember') ? 'checked' : '' }}>
                    {{ __('Keep me signed in on this device') }}
                </label>
                <button class="btn btn-p" type="submit" style="height:40px;justify-content:center">{{ __('Sign in') }}</button>
                <button class="btn" type="button" disabled aria-describedby="sso-note" style="height:40px;justify-content:center">
                    <x-source.icon name="shield" />{{ __('Continue with single sign-on') }}
                </button>
                <p id="sso-note" class="help">{{ __('Single sign-on will be available when an identity provider is configured.') }}</p>
            </form>
            <p class="faint sm">48Source by 48Team</p>
        </main>
        <aside class="login-side" aria-label="{{ __('Procurement workflow illustration') }}">
            <div><h2>{{ __('Every request, quotation and award on one accountable record.') }}</h2></div>
            <div class="ledger" aria-hidden="true">
                <div><span>RFQ-2026-0418</span><b style="flex:1">Structural steel & rebar</b><span>4 quotes</span><span class="a">Award review</span></div>
                <div><span>RFQ-2026-0427</span><b style="flex:1">Ready-mix concrete</b><span>2 quotes</span><span>Closing soon</span></div>
                <div><span>PO-2026-0189</span><b style="flex:1">Baltic Steel & Rebar</b><span>In transit</span></div>
                <div><span>RFQ-2026-0432</span><b style="flex:1">Reinforcement steel</b><span>1 quote</span></div>
            </div>
        </aside>
    </div>
</div>
