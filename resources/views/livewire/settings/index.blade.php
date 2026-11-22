<div class="stack">
<header class="ph"><div><h1 class="ph-t">{{ __('System settings') }}</h1><p class="ph-d">{{ __('Configuration and operational controls for 48Source.') }}</p></div></header>
<div class="note warn">{{ __('Settings are saved through the existing application service. Some integration settings do not become active until they are configured on the server.') }}</div>
<div class="tabs" style="flex-wrap:wrap"><button type="button" class="tab {{ $activeTab === 'general' ? 'on' : '' }}" wire:click="$set('activeTab','general')">{{ __('General') }}</button>
<button type="button" class="tab {{ $activeTab === 'sap' ? 'on' : '' }}" wire:click="$set('activeTab','sap')">{{ __('SAP integration') }}</button>
<button type="button" class="tab {{ $activeTab === 'mail' ? 'on' : '' }}" wire:click="$set('activeTab','mail')">{{ __('Email') }}</button>
<button type="button" class="tab {{ $activeTab === 'database' ? 'on' : '' }}" wire:click="$set('activeTab','database')">{{ __('Database') }}</button>
<button type="button" class="tab {{ $activeTab === 'cache' ? 'on' : '' }}" wire:click="$set('activeTab','cache')">{{ __('Cache and queue') }}</button>
<button type="button" class="tab {{ $activeTab === 'security' ? 'on' : '' }}" wire:click="$set('activeTab','security')">{{ __('Security') }}</button>
<button type="button" class="tab {{ $activeTab === 'api' ? 'on' : '' }}" wire:click="$set('activeTab','api')">{{ __('API') }}</button>
<button type="button" class="tab {{ $activeTab === 'notifications' ? 'on' : '' }}" wire:click="$set('activeTab','notifications')">{{ __('Notifications') }}</button>
<button type="button" class="tab {{ $activeTab === 'business' ? 'on' : '' }}" wire:click="$set('activeTab','business')">{{ __('Business rules') }}</button>
<button type="button" class="tab {{ $activeTab === 'files' ? 'on' : '' }}" wire:click="$set('activeTab','files')">{{ __('File uploads') }}</button>
<button type="button" class="tab {{ $activeTab === 'system' ? 'on' : '' }}" wire:click="$set('activeTab','system')">{{ __('System information') }}</button></div>
@if($activeTab === 'general')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('General') }}</h2></div>
   <form wire:submit="saveGeneral" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-app_name">{{ __('Application name') }}</label><input class="input" id="setting-app_name" type="text" wire:model="app_name" />@error('app_name')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-app_url">{{ __('Application URL') }}</label><input class="input" id="setting-app_url" type="text" wire:model="app_url" />@error('app_url')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-app_timezone">{{ __('Timezone') }}</label><input class="input" id="setting-app_timezone" type="text" wire:model="app_timezone" />@error('app_timezone')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-app_locale">{{ __('Locale') }}</label><input class="input" id="setting-app_locale" type="text" wire:model="app_locale" />@error('app_locale')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-app_env">{{ __('Environment') }}</label><input class="input" id="setting-app_env" type="text" wire:model="app_env" />@error('app_env')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="app_debug"><span>{{ __('Debug mode') }}</span></label></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveGeneral">{{ __('Save General') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'sap')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('SAP integration') }}</h2></div>
   <form wire:submit="saveSap" class="card-b stack">
      <div class="fgrid"><label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="sap_enabled"><span>{{ __('SAP integration enabled') }}</span></label>
<div class="field"><label for="setting-sap_host">{{ __('SAP host') }}</label><input class="input" id="setting-sap_host" type="text" wire:model="sap_host" />@error('sap_host')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-sap_client">{{ __('SAP client') }}</label><input class="input" id="setting-sap_client" type="text" wire:model="sap_client" />@error('sap_client')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-sap_username">{{ __('SAP username') }}</label><input class="input" id="setting-sap_username" type="text" wire:model="sap_username" />@error('sap_username')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-sap_password">{{ __('SAP password') }}</label><input class="input" id="setting-sap_password" type="password" wire:model="sap_password" autocomplete="new-password"/>@error('sap_password')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-sap_language">{{ __('Language') }}</label><input class="input" id="setting-sap_language" type="text" wire:model="sap_language" />@error('sap_language')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-sap_sync_interval">{{ __('Sync interval (minutes)') }}</label><input class="input" id="setting-sap_sync_interval" type="number" wire:model="sap_sync_interval" />@error('sap_sync_interval')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="sap_auto_sync"><span>{{ __('Automatic synchronization') }}</span></label></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveSap">{{ __('Save SAP integration') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'mail')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('Email') }}</h2></div>
   <form wire:submit="saveMail" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-mail_driver">{{ __('Mail driver') }}</label><input class="input" id="setting-mail_driver" type="text" wire:model="mail_driver" />@error('mail_driver')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_host">{{ __('Mail host') }}</label><input class="input" id="setting-mail_host" type="text" wire:model="mail_host" />@error('mail_host')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_port">{{ __('Mail port') }}</label><input class="input" id="setting-mail_port" type="number" wire:model="mail_port" />@error('mail_port')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_username">{{ __('Mail username') }}</label><input class="input" id="setting-mail_username" type="text" wire:model="mail_username" />@error('mail_username')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_password">{{ __('Mail password') }}</label><input class="input" id="setting-mail_password" type="password" wire:model="mail_password" autocomplete="new-password"/>@error('mail_password')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_encryption">{{ __('Encryption') }}</label><input class="input" id="setting-mail_encryption" type="text" wire:model="mail_encryption" />@error('mail_encryption')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_from_address">{{ __('From email') }}</label><input class="input" id="setting-mail_from_address" type="email" wire:model="mail_from_address" />@error('mail_from_address')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-mail_from_name">{{ __('From name') }}</label><input class="input" id="setting-mail_from_name" type="text" wire:model="mail_from_name" />@error('mail_from_name')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveMail">{{ __('Save Email') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'database')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('Database') }}</h2></div>
   <form wire:submit="saveDatabase" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-db_connection">{{ __('Connection driver') }}</label><input class="input" id="setting-db_connection" type="text" wire:model="db_connection" />@error('db_connection')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-db_host">{{ __('Host') }}</label><input class="input" id="setting-db_host" type="text" wire:model="db_host" />@error('db_host')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-db_port">{{ __('Port') }}</label><input class="input" id="setting-db_port" type="number" wire:model="db_port" />@error('db_port')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-db_database">{{ __('Database') }}</label><input class="input" id="setting-db_database" type="text" wire:model="db_database" />@error('db_database')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-db_username">{{ __('Username') }}</label><input class="input" id="setting-db_username" type="text" wire:model="db_username" />@error('db_username')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-db_password">{{ __('Database password') }}</label><input class="input" id="setting-db_password" type="password" wire:model="db_password" autocomplete="new-password"/>@error('db_password')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveDatabase">{{ __('Save Database') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'security')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('Security') }}</h2></div>
   <form wire:submit="saveSecurity" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-session_lifetime">{{ __('Session lifetime (minutes)') }}</label><input class="input" id="setting-session_lifetime" type="number" wire:model="session_lifetime" />@error('session_lifetime')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="force_https"><span>{{ __('Force HTTPS') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="csrf_protection"><span>{{ __('CSRF protection') }}</span></label>
<div class="field"><label for="setting-password_min_length">{{ __('Minimum password length') }}</label><input class="input" id="setting-password_min_length" type="number" wire:model="password_min_length" />@error('password_min_length')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="password_require_special"><span>{{ __('Require special character') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="password_require_number"><span>{{ __('Require number') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="password_require_uppercase"><span>{{ __('Require uppercase') }}</span></label>
<div class="field"><label for="setting-max_login_attempts">{{ __('Maximum login attempts') }}</label><input class="input" id="setting-max_login_attempts" type="number" wire:model="max_login_attempts" />@error('max_login_attempts')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-lockout_duration">{{ __('Lockout duration') }}</label><input class="input" id="setting-lockout_duration" type="number" wire:model="lockout_duration" />@error('lockout_duration')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveSecurity">{{ __('Save Security') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'api')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('API') }}</h2></div>
   <form wire:submit="saveApi" class="card-b stack">
      <div class="fgrid"><label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="api_enabled"><span>{{ __('Enable API') }}</span></label>
<div class="field"><label for="setting-api_rate_limit">{{ __('Requests per minute') }}</label><input class="input" id="setting-api_rate_limit" type="number" wire:model="api_rate_limit" />@error('api_rate_limit')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="api_key_required"><span>{{ __('Require API key') }}</span></label>
<div class="field"><label for="setting-api_version">{{ __('Version') }}</label><input class="input" id="setting-api_version" type="text" wire:model="api_version" />@error('api_version')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveApi">{{ __('Save API') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'notifications')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('Notifications') }}</h2></div>
   <form wire:submit="saveNotifications" class="card-b stack">
      <div class="fgrid"><label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_rfq_created"><span>{{ __('RFQ created alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_quote_submitted"><span>{{ __('Quote submitted alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_order_placed"><span>{{ __('Order placed alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_sla_breach"><span>{{ __('SLA breach alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_via_email"><span>{{ __('Email alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_via_sms"><span>{{ __('SMS alerts') }}</span></label>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="notify_via_slack"><span>{{ __('Slack alerts') }}</span></label>
<div class="field"><label for="setting-slack_webhook">{{ __('Slack webhook') }}</label><input class="input" id="setting-slack_webhook" type="password" wire:model="slack_webhook" autocomplete="new-password"/>@error('slack_webhook')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveNotifications">{{ __('Save Notifications') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'business')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('Business rules') }}</h2></div>
   <form wire:submit="saveBusinessRules" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-rfq_default_duration">{{ __('Default RFQ duration (days)') }}</label><input class="input" id="setting-rfq_default_duration" type="number" wire:model="rfq_default_duration" />@error('rfq_default_duration')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-quote_validity_days">{{ __('Default quote validity (days)') }}</label><input class="input" id="setting-quote_validity_days" type="number" wire:model="quote_validity_days" />@error('quote_validity_days')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-min_order_amount">{{ __('Minimum order amount') }}</label><input class="input" id="setting-min_order_amount" type="number" wire:model="min_order_amount" />@error('min_order_amount')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-max_order_amount">{{ __('Maximum order amount') }}</label><input class="input" id="setting-max_order_amount" type="number" wire:model="max_order_amount" />@error('max_order_amount')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="require_approval"><span>{{ __('Approval required') }}</span></label>
<div class="field"><label for="setting-approval_threshold">{{ __('Approval threshold') }}</label><input class="input" id="setting-approval_threshold" type="number" wire:model="approval_threshold" />@error('approval_threshold')<span class="err">{{ $message }}</span>@enderror</div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveBusinessRules">{{ __('Save Business rules') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'files')
 <section class="card">
   <div class="card-h"><h2 class="card-t">{{ __('File uploads') }}</h2></div>
   <form wire:submit="saveFileUpload" class="card-b stack">
      <div class="fgrid"><div class="field"><label for="setting-max_upload_size">{{ __('Maximum file size (KB)') }}</label><input class="input" id="setting-max_upload_size" type="number" wire:model="max_upload_size" />@error('max_upload_size')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="setting-allowed_extensions">{{ __('Allowed file extensions') }}</label><input class="input" id="setting-allowed_extensions" type="text" wire:model="allowed_extensions" />@error('allowed_extensions')<span class="err">{{ $message }}</span>@enderror</div>
<label class="row" style="min-height:40px;cursor:pointer"><input class="chk" type="checkbox" wire:model="scan_uploads"><span>{{ __('Scan uploaded files') }}</span></label></div>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="saveFileUpload">{{ __('Save File uploads') }}</button></div>
   </form>
 </section>
@endif
@if($activeTab === 'cache')
 <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Cache & queue') }}</h2></div>
 <div class="card-b stack"><dl class="kv">
     <dt>{{ __('Cache driver') }}</dt><dd>{{ $cache_driver }}</dd><dt>{{ __('Queue driver') }}</dt><dd>{{ $queue_driver }}</dd>
     <dt>{{ __('Cache TTL') }}</dt><dd>{{ $cache_ttl }}</dd>
 </dl><div class="row wrap">
   <button type="button" class="btn" wire:click="clearCache">{{ __('Clear cache') }}</button>
   <button type="button" class="btn btn-d" wire:click="clearLogs" wire:confirm="{{ __('Clear application logs?') }}">{{ __('Clear logs') }}</button>
 </div></div></section>
@endif
@if($activeTab === 'system')
 <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Runtime information') }}</h2></div>
 <div class="card-b"><dl class="kv">@foreach($systemInfo as $key => $value)
    <dt>{{ ucfirst(str_replace('_',' ',$key)) }}</dt><dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
 @endforeach</dl></div></section>
 <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Feature flags') }}</h2></div><div class="card-b"><livewire:settings.feature-flags /></div></section>
@endif
<section class="card"><div class="card-h"><h2 class="card-t">{{ __('Maintenance') }}</h2></div>
<div class="card-b row wrap">
  <span class="badge {{ $maintenance_mode ? 'b-warn' : 'b-ok' }}">{{ $maintenance_mode ? __('Enabled') : __('Disabled') }}</span>
  <button type="button" class="btn btn-d" wire:click="toggleMaintenance" wire:confirm="{{ __('Change maintenance mode?') }}">{{ $maintenance_mode ? __('Disable maintenance') : __('Enable maintenance') }}</button>
</div></section>
</div>