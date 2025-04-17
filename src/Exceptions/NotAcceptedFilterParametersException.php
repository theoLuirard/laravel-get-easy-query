<?php

namespace theoLuirard\TreeStructuredRelation\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class NotAcceptedFilterParametersException extends RuntimeException
{

    // Custom exception for handling circular tree relations
    public function __construct(
        $model,
        $field,
        int $code = 0,
        Exception $previous = null
    ) {

        $message = sprintf('A filter requested has not been accepted for the model %s. Field requested : %s',
            $model::class,
            $field,
        );

        parent::__construct($message, $code, $previous);
    }
}
