<?php

it('includes the same selectable RFQ table in admin and buyer workspaces', function () {
    $table = file_get_contents(resource_path('views/components/source/rfq-table.blade.php'));
    $admin = file_get_contents(app_path('Livewire/Rfq/Index.php'));
    $buyer = file_get_contents(app_path('Livewire/Buyer/Rfq/Index.php'));

    expect($table)
        ->toContain('togglePageRfqSelection', 'toggleRfqSelection', 'exportSelectedRfqsCsv')
        ->toContain('source-rfq-bulk-bar');
    expect($admin)->toContain('WithRfqBulkExport');
    expect($buyer)->toContain('WithRfqBulkExport');
});

it('scopes bulk exports to the requesting actor and escapes spreadsheet formulas', function () {
    $source = file_get_contents(app_path('Livewire/Traits/WithRfqBulkExport.php'));

    expect($source)
        ->toContain("->where('buyer_id', \$actor->id)")
        ->toContain("abort_unless(\$records->count() === count(\$ids), 403)")
        ->toContain('streamDownload')
        ->toContain('fputcsv')
        ->toContain('preg_match');
});

it('restricts buyer RFQ mutations to the owning buyer', function () {
    $source = file_get_contents(app_path('Livewire/Buyer/Rfq/Show.php'));

    expect($source)
        ->toContain('private function authorizeBuyerOwner(): void')
        ->toContain('Invalid RFQ status transition.')
        ->toContain("abort_unless(\$quote->status === 'submitted', 403)");
});
