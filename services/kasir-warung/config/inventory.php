<?php

return [
    // Inventory Core Service dipanggil secara sinkron (gaya RPC / REST).
    'base_url' => env('INVENTORY_URL', 'http://host.docker.internal:8083'),
    'timeout' => (int) env('INVENTORY_TIMEOUT', 3),
];
