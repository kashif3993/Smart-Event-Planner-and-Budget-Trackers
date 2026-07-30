<?php

namespace App\Exceptions;

use RuntimeException;

class AiContentionNegotiationNotConfiguredException extends RuntimeException
{
    protected $message = 'Multi-Agent Negotiation isn\'t configured yet. Add AI_TASK_API_KEY to your .env file.';
}
