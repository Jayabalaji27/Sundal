<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id that is added to each log line it writes (the
 * app, AI and integrations logs) and to the jobs it queues, and is sent back
 * as the X-Request-Id header. One id then finds a request end to end, from the
 * browser through the AI assistant to Zoom or Slack. An id set by the proxy in
 * front of the app is kept.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Request-Id');
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9._-]{8,64}$/', $id)) {
            $id = (string) Str::uuid();
        }
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
