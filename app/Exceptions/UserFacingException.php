<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected failure the user can act on. `code` maps to lang/{locale}/errors.php
 * which holds the title, the sentence and what to do next.
 */
class UserFacingException extends RuntimeException
{
    public function __construct(public string $errorCode, public array $params = [], public int $status = 422)
    {
        parent::__construct($errorCode);
    }
}
