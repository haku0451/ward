<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $token = trim(substr($header, 7));

        if ($token === '' || ! str_starts_with($token, 'ward_')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $node = Node::findByPlainToken($token);

        if ($node === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->attributes->set('agentNode', $node);
        $request->setUserResolver(fn (): Node => $node);

        return $next($request);
    }
}
