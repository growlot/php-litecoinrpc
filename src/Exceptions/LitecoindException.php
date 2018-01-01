<?php

namespace Majestic\Litecoin\Exceptions;

use RuntimeException;

class LitecoindException extends RuntimeException
{
    /**
     * Construct new litecoind exception.
     *
     * @param object $error
     *
     * @return void
     */
    public function __construct($error)
    {
        parent::__construct($error['message'], $error['code']);
    }
}
