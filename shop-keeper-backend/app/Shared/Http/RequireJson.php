<?php

namespace App\Shared\Http;

use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireJson
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBody($request) && ! $this->hasValidJsonBody($request)) {
            throw new ApiException('request.not_json', 415);
        }

        return $next($request);
    }

    private function hasBody(Request $request): bool
    {
        return $request->getContent() !== ''
            || $request->request->count() > 0
            || $request->allFiles() !== [];
    }

    private function hasValidJsonBody(Request $request): bool
    {
        return $request->isJson() && json_validate($request->getContent());
    }
}
