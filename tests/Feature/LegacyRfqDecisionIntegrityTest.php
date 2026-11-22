<?php

// Regression guard for the legacy detail view. A view-only user must not be able
// to mutate RFQs and award decisions must always use the audited PO service.
it('keeps the legacy RFQ detail mutation methods behind authorization', function () {
    $source = file_get_contents(app_path('Livewire/Rfq/Show.php'));

    expect($source)
        ->toContain('private function authorizeRfqDecision(): void')
        ->toContain('$this->authorizeRfqDecision();')
        ->toContain("abort_unless(\$actor && \$actor->isAdmin() && \$actor->can('view_rfqs'), 403)")
        ->toContain('Invalid RFQ status transition.');
});

it('routes legacy quote acceptance through the transactional award service', function () {
    $source = file_get_contents(app_path('Livewire/Rfq/Show.php'));
    $start = strpos($source, 'public function acceptQuote($params): void');
    $end = strpos($source, 'Confirm rejecting a quote', $start);

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();
    $method = substr($source, $start, $end - $start);
    expect($method)
        ->toContain('AwardAndPurchaseOrderService::class')
        ->toContain('->award($this->request, $quoteId, Auth::user())')
        ->not->toContain("\$this->request->status = 'awarded'");
});
