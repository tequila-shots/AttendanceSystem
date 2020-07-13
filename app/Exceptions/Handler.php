<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
# For 404 error
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {

        if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            return response(['success' => false, 'error' => 'Token is invalid'], 401);
        }
        if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            return response(['success' => false, 'error' => 'Token has expired'], 401);
        }
        if ($exception instanceof NotFoundHttpException) {
            return Route::respondWithRoute('fallback');
        }
        if ($exception instanceof ModelNotFoundException) {
            return Route::respondWithRoute('fallback');
        }
        return parent::render($request, $exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['success' => false, 'error' => $exception->getMessage()], 401);
    }
}
