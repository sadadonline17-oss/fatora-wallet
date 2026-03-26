<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Webhook-Signature');
        
        if (!$signature) {
            $signature = $request->header('X-Hub-Signature-256');
        }

        if ($signature) {
            $secret = config('app.webhook_secret');
            
            if ($secret) {
                $expected = hash_hmac('sha256', $request->getContent(), $secret);
                
                if (!hash_equals($expected, $signature)) {
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }
        }

        return $next($request);
    }
}
