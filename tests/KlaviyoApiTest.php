<?php

namespace Tests;

use Carbon\Carbon;
use Anibalealvarezs\KlaviyoApi\Classes\Metrics;
use Anibalealvarezs\KlaviyoApi\Enums\AggregatedMeasurement;
use Anibalealvarezs\KlaviyoApi\Enums\Interval;
use Anibalealvarezs\KlaviyoApi\Enums\Metrics as EnumsMetrics;
use Anibalealvarezs\KlaviyoApi\Enums\Sort;
use Anibalealvarezs\KlaviyoApi\KlaviyoApi;
use Faker\Factory;
use Faker\Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Anibalealvarezs\ApiSkeleton\Classes\Exceptions\ApiRequestException;

class KlaviyoApiTest extends TestCase
{
    private KlaviyoApi $klaviyoApi;
    private string $metricId;
    private Generator $faker;

    /**
     * @param MockHandler $mock
     * @return GuzzleClient
     */
    protected function createMockedGuzzleClient(MockHandler $mock): GuzzleClient
    {
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    /**
     * @throws GuzzleException
     */
    protected function setUp(): void
    {
        $configFile = __DIR__ . "/../config/config.yaml";
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            $this->metricId = Metrics::getMetricIdByName(
                name: EnumsMetrics::placed_order->value,
                config: $config,
            );
        } else {
            $config = ['klaviyo_api_key' => 'pk_test'];
            $this->metricId = 'metric_id';
        }

        $this->klaviyoApi = new KlaviyoApi(
            apiKey: $config['klaviyo_api_key']
        );
        $this->faker = Factory::create();
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(KlaviyoApi::class, $this->klaviyoApi);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMetrics(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => [['id' => 'm1', 'type' => 'metric']]])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $metrics = $client->getMetrics(
            metricFields: $this->faker->randomElements(['name', 'created', 'updated', 'integration'], null),
        );

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('data', $metrics);
        $this->assertArrayHasKey('type', $metrics['data'][0]);
        $this->assertArrayHasKey('id', $metrics['data'][0]);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMetricData(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'type' => 'metric',
                    'id' => 'm1',
                    'attributes' => []
                ]
            ])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $metricData = $client->getMetricData(
            metricId: 'm1',
        );

        $this->assertIsArray($metricData);
        $this->assertArrayHasKey('data', $metricData);
        $this->assertArrayHasKey('type', $metricData['data']);
        $this->assertArrayHasKey('id', $metricData['data']);
        $this->assertArrayHasKey('attributes', $metricData['data']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMetricAggregates()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'type' => 'metric-aggregate',
                    'id' => 'ma1',
                    'attributes' => [
                        'dates' => [],
                        'data' => [
                            [
                                'dimensions' => [],
                                'measurements' => []
                            ]
                        ]
                    ]
                ]
            ])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $metricAggregates = $client->getMetricAggregates(
            metricId: 'm1',
            count: 10,
            interval: Interval::day,
            timezone: 'UTC'
        );

        $this->assertIsArray($metricAggregates);
        $this->assertArrayHasKey('data', $metricAggregates);
        $this->assertArrayHasKey('attributes', $metricAggregates['data']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllCampaignsAndProcess(): void
    {
        $response1 = [
            'data' => [['id' => 'c1']],
            'links' => ['next' => 'https://a.klaviyo.com/api/campaigns/?page[cursor]=next_cursor']
        ];
        $response2 = [
            'data' => [['id' => 'c2']],
            'links' => ['next' => null]
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);

        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $processedCount = 0;
        $client->getAllCampaignsAndProcess(callback: function ($data) use (&$processedCount) {
            $processedCount += count($data);
        });

        $this->assertEquals(2, $processedCount);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllCampaignsErrorMidLoop(): void
    {
        $response1 = [
            'data' => [['id' => 'c1']],
            'links' => ['next' => 'https://a.klaviyo.com/api/campaigns/?page[cursor]=tok2']
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($response1)),
            new Response(500, [], 'Internal Server Error'),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);

        $client = new KlaviyoApi(apiKey: 'pk_test', guzzleClient: $guzzle);

        $this->expectException(ApiRequestException::class);

        $client->getAllCampaignsAndProcess(callback: function ($data) {});
    }
}
