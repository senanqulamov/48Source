<?php

return [
    /*
    | Approval chain for requisitions. Every level whose minimum is reached is added, in this order.
    | Amounts are in cents. A requisition without estimated prices only needs the first level.
    */
    'approval_levels' => [
        ['min_cents' => 0, 'role' => 'buyer', 'label' => 'Procurement review'],
        ['min_cents' => 2500000, 'role' => 'admin', 'label' => 'Head of procurement'],
    ],

    // Invoice price may differ from the order price by this many cents per line before it is flagged.
    'invoice_price_tolerance_cents' => 0,

    // Supplier documents: allowed upload types and the size limit in kilobytes.
    'document_mimes' => 'pdf,jpg,jpeg,png',
    'document_max_kb' => 5120,

    // Interface languages. Menus and common buttons are translated; page texts stay in English.
    'locales' => ['en' => 'English', 'az' => 'Azərbaycanca', 'ru' => 'Русский'],
];
