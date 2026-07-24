<?php

namespace App\Exceptions;

use RuntimeException;

class AiBudgetRebalancerNotConfiguredException extends RuntimeException
{
    protected $message = 'AI-optimized rebalancing isn\'t configured yet. Add AI_TASK_API_KEY to your .env file.';
}
