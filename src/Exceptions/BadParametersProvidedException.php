<?php

namespace theoLuirard\TreeStructuredRelation\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ProvidedParamtersTypeNotAcceptedException extends RuntimeException
{

    // Custom exception for handling circular tree relations
    public function __construct(
        $model,
        $propertyName,
        $propertyType,
        $accepted_possibilities = [],
        int $code = 0,
        Exception $previous = null
    ) {

        $message = sprintf('%s has been defined incorrectly as a %s for the model %s. %s should be : %s',
            $propertyName,
            $propertyType,
            $model::class,
            $propertyName,
            implode('|', $accepted_possibilities)
        );

        parent::__construct($message, $code, $previous);
    }
}
