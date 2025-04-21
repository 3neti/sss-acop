<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\KYC\Exceptions\FacePhotoNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withCommands([
        __DIR__.'/../app/KYC/Commands',
        __DIR__.'/../app/Commerce/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions) {
//        $exceptions->respond(function (FacePhotoNotFoundException $e) {
//            return new JsonResponse([
//                'message' => $e->getMessage(),
//            ], Response::HTTP_NOT_FOUND);
//        });
    })->create();
