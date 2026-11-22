<?php

return [
        // Retained only as an explicit marker: the approved design is the only UI.
    'redesign_enabled' => true,

    // Proposed business-policy defaults. These do not activate workflows by themselves.
    'award_target_days' => (int) env('SOURCE48_AWARD_TARGET_DAYS', 5),
    'acknowledgement_alert_days' => (int) env('SOURCE48_PO_ACK_ALERT_DAYS', 3),
    'price_typo_warning_percent' => (int) env('SOURCE48_PRICE_WARNING_PERCENT', 25),
];
