<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Client
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('client')->check()) {
            $notification = array(
                'message' => 'You do not have permission to access this page.',
                'alert-type' => 'error'
            );
            return redirect()->route('client.login')->with($notification);
        }
        return $next($request);
    }
}
