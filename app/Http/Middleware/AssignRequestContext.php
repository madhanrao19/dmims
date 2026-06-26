<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every request with a correlation ID so log lines from the same
 * request can be tied together. Laravel's log channels already merge
 * Context into every log record's "extra" data automatically (see
 * Illuminate\Log\Context\ContextLogProcessor) and propagate Context into
 * queued jobs dispatched during the request — no extra wiring needed.
 */
class AssignRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        Context::add('request_id', $requestId);

        if (auth()->check()) {
            Context::add('user_id', auth()->id());
            Context::add('customer_id', auth()->user()->customer_id);
        }

        $response = $next($request);

        return $response->withHeaders(['X-Request-Id' => $requestId]);
    }
}
