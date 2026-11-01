<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika Inventory Core Service tidak bisa dihubungi.
 * Di level HTTP ini menjadi respons 503 — bukti "temporal coupling" gaya RPC.
 */
class StokTidakTerjangkau extends RuntimeException {}
