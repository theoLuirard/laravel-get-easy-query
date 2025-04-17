<?php

namespace theoLuirard\getEasyQuery\Errors;

use Error;

class NotAcceptedPropertyError extends Error
{
    // Custom error for telling a property has been badly provided
    public function __construct(
        $model,
        $propertyName,
        $propertyType,
        $accepted_possibilities = [],
        int $code = 0,
        Error $previous = null
    ) {

        $message = sprintf(
            '%s is not accepted as %s been defined for the model %s. %s should be : %s',
            $propertyName,
            $propertyType,
            $model,
            $propertyName,
            implode('|', $accepted_possibilities)
        );

        parent::__construct($message, $code, $previous);
    }
}
