<?php

namespace theoLuirard\TreeStructuredRelation\Errors;

use Error;

class NotProvidedPropertyError extends Error
{
    // Custom error for telling a property has not been provided
    public function __construct(
        $model,
        $propertyName,
        int $code = 0,
        Error $previous = null
    ) {

        $message = sprintf(
            '%s has not been defined for the model %s.',
            $propertyName,
            $model
        );

        parent::__construct($message, $code, $previous);
    }
}
