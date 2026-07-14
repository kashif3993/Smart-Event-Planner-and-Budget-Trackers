<?php

namespace App\Exceptions;

use RuntimeException;

class AiTaskGeneratorNotConfiguredException extends RuntimeException
{
    protected $message = 'AI task generation isn\'t configured yet. Add AI_TASK_API_KEY (your Anthropic API key) to your .env file.';
}
