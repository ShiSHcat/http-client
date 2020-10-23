<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Loop;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use function Amp\defer;

class TimeoutTest extends AsyncTestCase
{
    private DelegateHttpClient $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = HttpClientBuilder::buildDefault();
    }

    public function testTimeoutDuringBody(): void
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        defer(static function () use ($server): void {
            while ($client = $server->accept()) {
                $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::unreference(Loop::delay(3000, static function () use ($client) {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(2000);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);
            $request->setTransferTimeout(1000);

            $response = $this->client->request($request);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1000 ms");

            $response->getBody()->buffer();
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringConnect(): void
    {
        $this->setTimeout(600);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?CancellationToken $token = null
            ): Socket\EncryptableSocket {
                $this->assertSame(1, $connectContext->getConnectTimeout());
                throw new TimeoutException;
            });

        $this->client = new PooledHttpClient(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');
        $request->setTcpConnectTimeout(1);

        $this->client->request($request, new NullCancellationToken);
    }

    public function testTimeoutDuringTlsEnable(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        defer(static function () use ($server): void {
            while ($client = $server->accept()) {
                Loop::unreference(Loop::delay(3000, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(600);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageMatches("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 100 ms)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(100);

            try {
                $this->client->request($request);
            } catch (UnprocessedRequestException $e) {
                throw $e->getPrevious();
            }
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringTlsEnableCatchable(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        defer(static function () use ($server): void {
            while ($client = $server->accept()) {
                Loop::unreference(Loop::delay(3000, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(600);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(100);

            $this->client->request($request);

            $this->fail('No exception thrown');
        } catch (UnprocessedRequestException $e) {
            $this->assertStringStartsWith('TLS handshake with \'127.0.0.1:', $e->getPrevious()->getMessage());
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringBodyInterceptor(): void
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        defer(static function () use ($server): void {
            while ($client = $server->accept()) {
                $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::unreference(Loop::delay(3000, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(2000);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);

            $client = new InterceptedHttpClient(new PooledHttpClient, new SetRequestTimeout(10000, 10000, 1000));
            $response = $client->request($request, new NullCancellationToken);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1000 ms");

            $response->getBody()->buffer();
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringConnectInterceptor(): void
    {
        $this->setTimeout(600);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?CancellationToken $token = null
            ): Socket\EncryptableSocket {
                $this->assertSame(1, $connectContext->getConnectTimeout());
                throw new TimeoutException;
            });

        $client = new PooledHttpClient(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)));
        $client = new InterceptedHttpClient($client, new SetRequestTimeout(1));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');

        $client->request($request, new NullCancellationToken);
    }

    public function testTimeoutDuringTlsEnableInterceptor(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        defer(static function () use ($server): void {
            while ($client = $server->accept()) {
                Loop::unreference(Loop::delay(3000, static function () use ($client) {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(600);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageMatches("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 100 ms)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(100);

            $client = new PooledHttpClient();
            $client = new InterceptedHttpClient($client, new SetRequestTimeout(10000, 100));

            try {
                $client->request($request, new NullCancellationToken);
            } catch (UnprocessedRequestException $e) {
                throw $e->getPrevious();
            }
        } finally {
            $server->close();
        }
    }
}
