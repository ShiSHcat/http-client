<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\NullCancellationToken;

/**
 * Convenient HTTP client for use in applications and libraries, providing a default for the cancellation token and
 * automatically cloning the passed request, so future application requests can re-use the same object again.
 */
final class HttpClient implements DelegateHttpClient
{
    private DelegateHttpClient $httpClient;

    public function __construct(DelegateHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Request a specific resource from an HTTP server.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Response
     */
    public function request(Request $request, ?CancellationToken $cancellation = null): Response
    {
        return $this->httpClient->request(clone $request, $cancellation ?? new NullCancellationToken);
    }
}
