<?php

return [
    // Broker dimiliki proyek kasir-warung; diakses lewat port yang dibuka di komputer.
    'host' => env('RABBITMQ_HOST', 'host.docker.internal'),
    'port' => (int) env('RABBITMQ_PORT', 5673),
    'user' => env('RABBITMQ_USER', 'warung'),
    'password' => env('RABBITMQ_PASSWORD', 'rahasia'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => 'warung.transaksi',
];
