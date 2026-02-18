<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'thirdparty/api/externals/gettermsandcondition',
            'thirdparty/api/enroll_new',
            'thirdparty/api/customerkycinfo',
            'thirdparty/api/customerkycupdation',
            'thirdparty/api/customerbankdetail_updation',
            'thirdparty/api/getstoregoldrate',
            'thirdparty/api/externals/schemebenifits',
            'thirdparty/api/externals/nomineedetails',
            'thirdparty/api/externals/get-pincode-details',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
