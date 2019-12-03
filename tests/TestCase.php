<?php

namespace Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\ResponseFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UriFactory;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\KeyManagement\JWKFactory;
use OpenIDConnect\Core\Builder;
use OpenIDConnect\Core\Token\TokenFactory;
use OpenIDConnect\OAuth2\Metadata\ClientInformation;
use OpenIDConnect\OAuth2\Metadata\ProviderMetadata;
use OpenIDConnect\OAuth2\Token\TokenFactoryInterface;
use OpenIDConnect\Support\Container\Container;
use OpenIDConnect\Support\Http\GuzzlePsr18Client;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use function GuzzleHttp\json_encode;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createClientInformation($overwrite = []): ClientInformation
    {
        return new ClientInformation($this->createClientInformationConfig($overwrite));
    }

    protected function createClientInformationConfig($overwrite = []): array
    {
        return array_merge([
            'client_id' => 'some_id',
            'client_secret' => 'some_secret',
            'redirect_uri' => 'https://someredirect',
            'redirect_uris' => ['https://someredirect'],
        ], $overwrite);
    }

    protected function createContainer(array $instances = []): ContainerInterface
    {
        if (empty($instances[ClientInterface::class])) {
            $instances[ClientInterface::class] = $this->createHttpClient();
        }

        if (empty($instances[StreamFactoryInterface::class])) {
            $instances[StreamFactoryInterface::class] = new StreamFactory();
        }

        if (empty($instances[ResponseFactoryInterface::class])) {
            $instances[ResponseFactoryInterface::class] = new ResponseFactory();
        }

        if (empty($instances[RequestFactoryInterface::class])) {
            $instances[RequestFactoryInterface::class] = new RequestFactory();
        }

        if (empty($instances[UriFactoryInterface::class])) {
            $instances[UriFactoryInterface::class] = new UriFactory();
        }

        if (empty($instances[TokenFactoryInterface::class])) {
            $instances[TokenFactoryInterface::class] = new TokenFactory();
        }

        return new Container($instances);
    }

    /**
     * @param array $provider
     * @param array $client
     * @param array $httpMock
     * @param array $history
     * @return Builder
     */
    protected function createFactory($provider = [], $client = [], $httpMock = [], $history = []): Builder
    {
        return new Builder(
            $this->createProviderMetadata($provider),
            $this->createClientInformation($client),
            $this->createHttpClient($httpMock, $history)
        );
    }

    /**
     * Creates HTTP client.
     *
     * @param ResponseInterface|ResponseInterface[] $responses
     * @param array $history
     * @return HandlerStack
     */
    protected function createHandlerStack($responses = [], &$history = []): HandlerStack
    {
        if (!is_array($responses)) {
            $responses = [$responses];
        }

        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return $handler;
    }

    /**
     * Creates HTTP client.
     *
     * @param ResponseInterface|ResponseInterface[] $responses
     * @param array $history
     * @return ClientInterface
     */
    protected function createHttpClient($responses = [], &$history = []): ClientInterface
    {
        return new GuzzlePsr18Client(new HttpClient($this->createHttpMockOption($responses, $history)));
    }

    /**
     * @param array $data
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    protected function createHttpJsonResponse(
        array $data = [],
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        return new HttpResponse($status, $headers, json_encode($data));
    }

    /**
     * @param ResponseInterface|ResponseInterface[] $responses
     * @param array $history
     * @return array
     */
    protected function createHttpMockOption($responses = [], &$history = []): array
    {
        return [
            'handler' => $this->createHandlerStack($responses, $history),
        ];
    }

    protected function createJwkSet($jwks = []): JWKSet
    {
        if (empty($jwks)) {
            $jwks = [JWKFactory::createRSAKey(1024, ['alg' => 'RS256'])];
        }

        return new JWKSet($jwks);
    }

    protected function createProviderMetadata($overwrite = [], $jwks = null): ProviderMetadata
    {
        return new ProviderMetadata(
            $this->createProviderMetadataConfig($overwrite),
            new \OpenIDConnect\OAuth2\Metadata\JwkSet(JsonConverter::decode(JsonConverter::encode($this->createJwkSet($jwks))))
        );
    }

    /**
     * @param array $overwrite
     * @return array
     */
    protected function createProviderMetadataConfig($overwrite = []): array
    {
        return array_merge([
            'issuer' => 'https://somewhere',
            'authorization_endpoint' => 'https://somewhere/auth',
            'token_endpoint' => 'https://somewhere/token',
            'jwks_uri' => 'https://somewhere/certs',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
        ], $overwrite);
    }

    /**
     * @param array $overwrite
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    protected function createFakeTokenEndpointResponse($overwrite = [], $status = 200, $headers = []): ResponseInterface
    {
        return $this->createHttpJsonResponse($this->createFakeTokenSetParameter($overwrite), $status, $headers);
    }

    /**
     * @param array $overwrite
     * @return array
     */
    protected function createFakeTokenSetParameter($overwrite = []): array
    {
        return array_merge([
            'access_token' => 'some-access-token',
            'expires_in' => 3600,
            'id_token' => null,
            'refresh_token' => 'some-refresh-token',
            'scope' => 'some-scope',
        ], $overwrite);
    }
}
