<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;

final class RetryRequests implements ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    private int $retryLimit;

    public function __construct(int $retryLimit)
    {
        $this->retryLimit = $retryLimit;
    }

    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $attempt = 1;

        do {
            try {
                return $httpClient->request(clone $request, $cancellation);
            } catch (UnprocessedRequestException $exception) {
                // Request was deemed retryable by connection, so carry on.
            } catch (SocketException $exception) {
                if (!$request->isIdempotent()) {
                    throw $exception;
                }

                // Request can safely be retried.
            }
        } while ($attempt++ <= $this->retryLimit);

        throw $exception;
    }
}
