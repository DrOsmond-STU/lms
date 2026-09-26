<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use RuntimeException;

/** Asisten AI tidak dapat dipakai (belum dikonfigurasi, kuota habis, atau API gagal). */
final class AiUnavailableException extends RuntimeException {}
