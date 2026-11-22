<?php

use App\Models\ProcurementPurchaseOrder;

it('enforces the approved procurement PO lifecycle', function () {
    $po = new ProcurementPurchaseOrder(['status' => 'draft']);
    expect($po->nextStatus())->toBe('issued');

    $po->status = 'issued';
    expect($po->nextStatus())->toBe('acknowledged');

    $po->status = 'acknowledged';
    expect($po->nextStatus())->toBe('in_transit');

    $po->status = 'in_transit';
    expect($po->nextStatus())->toBe('delivered');

    $po->status = 'delivered';
    expect($po->nextStatus())->toBe('invoiced');

    $po->status = 'invoiced';
    expect($po->nextStatus())->toBeNull();
});

it('does not reuse marketplace Order statuses', function () {
    expect(ProcurementPurchaseOrder::TRANSITIONS)->not->toHaveKey('pending');
    expect(ProcurementPurchaseOrder::TRANSITIONS)->toHaveKeys(['draft', 'issued', 'acknowledged', 'in_transit', 'delivered']);
});
