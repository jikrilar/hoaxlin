<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBertInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.bert.internal_token');

        if (! is_string($expected) || trim($expected) === '') {
            return response()->json([
                'message' => 'Internal BERT authentication token is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Bearer',
            ]);
        }

        return $next($request);
    }
}
