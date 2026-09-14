<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a que toda petición se trate como JSON.
 *
 * Sin esto, un cliente que no manda `Accept: application/json` recibe HTML
 * en los errores de validación y la app Flutter no sabe qué hacer con eso.
 */
class ForzarJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
