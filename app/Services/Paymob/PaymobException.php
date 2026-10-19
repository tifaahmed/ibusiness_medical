<?php

namespace App\Services\Paymob;

use RuntimeException;

/** The gateway refused, was unreachable, or answered with something unusable. */
class PaymobException extends RuntimeException {}
