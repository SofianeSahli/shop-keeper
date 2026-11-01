<?php

namespace App\Shared\Errors;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiErrorRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $apiException = $this->toApiException($exception);

        return response()->json(['error' => $apiException->toErrorBody()], $apiException->status);
    }

    private function toApiException(Throwable $exception): ApiException
    {
        return match (true) {
            $exception instanceof ApiException => $exception,
            $exception instanceof ValidationException => new ApiException('validation.failed', 422, fields: $exception->errors()),
            $exception instanceof AuthenticationException => new ApiException('auth.unauthenticated', 401),
            $exception instanceof TooManyRequestsHttpException => new ApiException(
                'request.throttled',
                429,
                retryAfterSeconds: (int) ($exception->getHeaders()['Retry-After'] ?? 0),
            ),
            $exception instanceof NotFoundHttpException => new ApiException('request.not_found', 404),
            $exception instanceof MethodNotAllowedHttpException => new ApiException('request.method_not_allowed', 405),
            default => new ApiException('server.error', 500),
        };
    }
}
