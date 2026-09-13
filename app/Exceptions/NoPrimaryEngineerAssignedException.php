<?php
namespace App\Exceptions;
use Exception;

class NoPrimaryEngineerAssignedException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            'La sede no tiene un ingeniero biomédico principal asignado.'
        );
    }
}
