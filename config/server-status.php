<?php

return [
    'snapshot_path' => env('SERVER_STATUS_SNAPSHOT_PATH', storage_path('app/server-status.json')),
    'stale_after_seconds' => (int) env('SERVER_STATUS_STALE_AFTER', 120),
];
