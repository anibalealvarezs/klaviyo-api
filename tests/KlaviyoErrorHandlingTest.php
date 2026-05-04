<?php

namespace Tests;

use Anibalealvarezs\KlaviyoApi\KlaviyoApi;
use Anibalealvarezs\KlaviyoApi\Support\KlaviyoErrorClassifier;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class KlaviyoErrorHandlingTest extends TestCase
{
    protected function createMockedGuzzleClient(MockHandler $mock): GuzzleClient
    {
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    /**
     * @throws GuzzleException
     */
    public function testKlaviyoSemanticRetryableFalsy200EventuallySucceeds(): void
    {
        $retryableBody = [
            'errors' => [[
                'status' => '429',
                'code' => 'rate_limit_exceeded',
                'detail' => 'Too many requests. Please slow down.',
            ]],
        ];
        $successBody = ['data' => [['id' => 'm1', 'type' => 'metric']]];

        $mock = new MockHandler([
            new Response(200, [], json_encode($retryableBody)),
            new Response(200, [], json_encode($successBody)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $response = $client->performRequest(method: 'GET', endpoint: 'metrics', revision: '2023-01-24');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(is_callable($client->getRateLimitDetector()));
    }

    public function testKlaviyoErrorClassifierRecognizesThrottlingSignals(): void
    {
        $classification = KlaviyoErrorClassifier::classify([
            'errors' => [[
                'status' => '429',
                'code' => 'throttled',
                'detail' => 'Rate limit reached.',
            ]],
        ]);

        $this->assertSame('retryable', $classification['category']);
        $this->assertTrue($classification['should_retry']);
    }
}

