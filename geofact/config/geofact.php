<?php

return [
    'tolerance_window_hours'            => env('GEOFACT_TOLERANCE_WINDOW_HOURS', 4),
    'raw_store_retention_days'          => env('GEOFACT_RAW_STORE_RETENTION_DAYS', 365),
    'audit_log_retention_days'          => env('GEOFACT_AUDIT_LOG_RETENTION_DAYS', 730),
    'max_transfer_delay_days'           => env('GEOFACT_MAX_TRANSFER_DELAY_DAYS', 90),
    'device_disconnect_threshold_hours' => env('GEOFACT_DEVICE_DISCONNECT_THRESHOLD_HOURS', 24),
];
