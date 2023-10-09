<?php

namespace App\Services\Workflow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Access.
 */
class Workflow extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'workflow';
    }
}
