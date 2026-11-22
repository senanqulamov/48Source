<?php

it('loads one shared control stylesheet for all role workspaces', function () {
    $app = file_get_contents(resource_path('css/app.css'));
    $css = file_get_contents(resource_path('css/48source-control-system.css'));
    expect($app)->toContain("@import './48source-control-system.css';");
    expect($css)->toContain('.source-ui .dt-top .dt-tools', '.source-dialog .modal', '.source-dialog .source-admin-drawer', '.source-dialog .ov-f');
});

it('uses approved user creation modal and edit drawer', function () {
    $create = file_get_contents(resource_path('views/livewire/users/create.blade.php'));
    $update = file_get_contents(resource_path('views/livewire/users/update.blade.php'));
    expect($create)->toContain('class="scrim source-dialog"', 'class="modal wide"');
    expect($create)->not->toContain('<x-modal');
    expect($update)->toContain('source-admin-drawer', 'role="dialog"');
    expect($update)->not->toContain('<x-slide');
});

it('requires an explicit confirmation state for destructive user actions', function () {
    $class = file_get_contents(app_path('Livewire/Users/Delete.php'));
    $view = file_get_contents(resource_path('views/livewire/users/delete.blade.php'));
    expect($class)->toContain('public bool $confirming = false', 'abort_unless($this->confirming, 403)');
    expect($view)->toContain('role="alertdialog"', "wire:click=\"delete\"");
    expect($class)->not->toContain('$this->question()');
});
