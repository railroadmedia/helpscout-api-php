<?php

declare(strict_types=1);

namespace HelpScout\Api\Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HelpScout\Api\ApiClient;
use HelpScout\Api\Exception\AuthenticationException;
use HelpScout\Api\Http\Auth\ClientCredentials;
use HelpScout\Api\Http\Auth\NullCredentials;
use HelpScout\Api\Http\Auth\RefreshCredentials;
use HelpScout\Api\Http\Authenticator;
use HelpScout\Api\Http\RestClient;
use HelpScout\Api\Http\RestClientBuilder;
use HelpScout\Api\Reports\Chat;
use HelpScout\Api\Reports\Docs\Overall;
use HelpScout\Api\Reports\ParameterBag;
use HelpScout\Api\Tests\ReflectionTestTrait;
use PHPUnit\Framework\TestCase;

class RestClientTest extends TestCase
{
    use ReflectionTestTrait;

    public $methodsClient;
    public $authenticator;

    public function setUp(): void
    {
        $this->methodsClient = \Mockery::mock(Client::class);
        $this->authenticator = \Mockery::mock(Authenticator::class);
    }

    public function testDefaultHeaders()
    {
        $this->authenticator->shouldReceive('getAuthHeader')->andReturn([
            'Authorization' => 'Bearer 123abc',
        ]);

        $restClient = new RestClient($this->methodsClient, $this->authenticator);

        $headers = $restClient->getDefaultHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals(RestClient::CONTENT_TYPE, $headers['Content-Type']);

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertEquals('Help Scout PHP API Client/'.ApiClient::CLIENT_VERSION.' (PHP '.phpversion().')', $headers['User-Agent']);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer 123abc', $headers['Authorization']);
    }

    public function testRunReport()
    {
        $params = new ParameterBag([]);
        $report = new Overall($params);

        $responseData = [
            'current' => 'aaabbb',
            'previous' => 'cccddd',
        ];

        $response = new Response(200, [], json_encode($responseData));

        $this->methodsClient->shouldReceive('send')
            ->andReturn($response);
        $this->authenticator->shouldReceive('getAuthHeader')->andReturn([
            'Authorization' => 'Bearer 123abc',
        ]);

        $restClient = new RestClient($this->methodsClient, $this->authenticator);
        $result = $restClient->getReport($report);
        $this->assertSame($responseData, $result);
    }

    public function testSendingRequestDoesntRefreshToken()
    {
        $exception = \Mockery::mock(AuthenticationException::class);
        $this->expectExceptionObject($exception);

        $this->methodsClient->shouldReceive('send')
            ->andThrow($exception);

        $this->authenticator->shouldReceive([
            'getAuthHeader' => ['the-header' => 'the-value'],
            'shouldAutoRefreshAccessToken' => false,
        ]);
        $this->authenticator->shouldReceive('fetchAccessAndRefreshToken')
            ->never();

        $restClient = new RestClient($this->methodsClient, $this->authenticator);
        $restClient->getReport(new Chat(new ParameterBag([])));
    }

    public function testSendingRequestRefreshesToken()
    {
        $exception = \Mockery::mock(AuthenticationException::class);
        $this->methodsClient->shouldReceive('send')
            ->andThrow($exception)
            ->once();

        $this->authenticator->shouldReceive([
            'getAuthHeader' => ['the-header' => 'the-value'],
            'shouldAutoRefreshAccessToken' => true,
        ]);
        $this->authenticator->shouldReceive('fetchAccessAndRefreshToken')
            ->once();

        $responseData = [
            'current' => 'aaabbb',
            'previous' => 'cccddd',
        ];
        $response = new Response(200, [], json_encode($responseData));
        $this->methodsClient->shouldReceive('send')
            ->with(\Mockery::on(function (Request $request) {
                // Ensure retry request includes new auth headers.
                $this->assertSame(['the-value'], $request->getHeader('the-header'));
                return true;
            }), \Mockery::any())
            ->andReturn($response);

        $restClient = new RestClient($this->methodsClient, $this->authenticator);
        $result = $restClient->getReport(new Chat(new ParameterBag([])));
        $this->assertSame($responseData, $result);
    }

    public function testRestClientBuilderHandlesClientCredentialsAuth()
    {
        $config = [
            'auth' => [
                'type' => ClientCredentials::TYPE,
                'appId' => '123abc',
                'appSecret' => 'cba321',
            ],
        ];
        $builder = new RestClientBuilder($config);
        $client = $builder->build();

        $this->assertInstanceOf(
            ClientCredentials::class,
            $client->getAuthenticator()->getAuthCredentials()
        );
    }

    public function testRestClientBuilderHandlesRefreshCredentialsAuth()
    {
        $config = [
            'auth' => [
                'type' => RefreshCredentials::TYPE,
                'appId' => '123abc',
                'appSecret' => 'cba321',
                'refreshToken' => 'fdasfdas',
            ],
        ];
        $builder = new RestClientBuilder($config);
        $client = $builder->build();

        $this->assertInstanceOf(
            RefreshCredentials::class,
            $client->getAuthenticator()->getAuthCredentials()
        );
    }

    public function testRestClientBuilderHandlesNullCredentialsAuth()
    {
        $config = [];
        $builder = new RestClientBuilder($config);
        $client = $builder->build();

        $this->assertInstanceOf(
            NullCredentials::class,
            $client->getAuthenticator()->getAuthCredentials()
        );
    }

    /**
     * @dataProvider noRetryParamProvider
     */
    public function testDeciderDoesNotRetry(...$params)
    {
        $builder = new RestClientBuilder();
        $decider = $this->invokeMethod($builder, 'getRetryDecider');

        $shouldRetry = \call_user_func($decider, ...$params);

        $this->assertFalse($shouldRetry);
    }

    public function noRetryParamProvider(): \Generator
    {
        $request = new Request(
            'POST',
            'https://api.helpscout.net/v2/uuid-4/chat'
        );
        $badResponse = new Response(400, [], 'bad response');

        $serverException = new ServerException(
            'BAD_REQUEST',
            $request,
            $badResponse
        );

        $connectionException = new ConnectException(
            'SERVER_WENT_AWAY',
            $request
        );

        yield [
            0,
            $request,
        ];

        yield [
            0,
            $request,
            null,
        ];

        yield [
            0,
            $request,
            null,
            null,
        ];

        yield [
            0,
            $request,
            null,
            $serverException,
        ];

        yield [
            0,
            $request,
            $badResponse,
            $serverException,
        ];

        yield [
            4,
            $request,
            null,
            $connectionException,
        ];

        yield [
            100,
            $request,
            null,
            $connectionException,
        ];
    }

    /**
     * @dataProvider retryParamProvider
     */
    public function testDeciderDoesRetry(...$params)
    {
        $builder = new RestClientBuilder();
        $decider = $this->invokeMethod($builder, 'getRetryDecider');

        $shouldRetry = \call_user_func($decider, ...$params);

        $this->assertTrue($shouldRetry);
    }

    public function retryParamProvider(): \Generator
    {
        $request = new Request(
            'POST',
            'https://api.helpscout.net/v2/uuid-1234/chat'
        );

        $badResponse = new Response(400, [], 'bad response');

        $connectionException = new ConnectException(
            'SERVER_WENT_AWAY',
            $request
        );

        // This technically should never happen, but just in case
        yield [
            0,
            $request,
            $badResponse,
            $connectionException,
        ];

        yield [
            0,
            $request,
            null,
            $connectionException,
        ];

        yield [
            1,
            $request,
            null,
            $connectionException,
        ];

        yield [
            2,
            $request,
            null,
            $connectionException,
        ];

        yield [
            3,
            $request,
            null,
            $connectionException,
        ];
    }
}
