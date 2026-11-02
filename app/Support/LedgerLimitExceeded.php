<?php

namespace App\Support;

/** An admin tried to move more than their monthly allowance for a hand-written balance change. */
class LedgerLimitExceeded extends \RuntimeException
{
}
