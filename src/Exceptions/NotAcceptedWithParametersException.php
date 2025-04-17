<?php

namespace theoLuirard\getEasyQuery\Exceptions;

use Exception;
use RuntimeException;

class NotAcceptedWithParametersException extends RuntimeException
{

    // Custom exception for handling circular tree relations
    public function __construct(
        $model,
        $with,
        int $code = 0,
        Exception $previous = null
    ) {

        $message = sprintf('A laravel with (binding other model method) requested has not been accepted for the model %s. With requested : %s',
            $model::class,
            $with,
        );

        parent::__construct($message, $code, $previous);
    }
}
