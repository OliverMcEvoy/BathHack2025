<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    // protected $except = [
    //     '/external-api', // Exclude this route from CSRF verification
    // ];

    // /**
    //  * Determine if the URI has been excluded from CSRF verification.
    //  *
    //  * @param \Illuminate\Http\Request $request
    //  * @return bool
    //  */
    // protected function inExceptArray($request)
    // {
    //     return true; // Ignore CSRF tokens for all routes
    // }
}
