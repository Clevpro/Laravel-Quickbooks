<?php

namespace Clevpro\LaravelQuickbooks;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Clevpro\LaravelQuickbooks\Skeleton\SkeletonClass
 */
class QuickbooksFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-quickbooks';
    }
}
