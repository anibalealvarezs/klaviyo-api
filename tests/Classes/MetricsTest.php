<?php

namespace Tests\Classes;

use Carbon\Carbon;
use Anibalealvarezs\KlaviyoApi\Classes\Metrics;
use Anibalealvarezs\KlaviyoApi\Enums\Interval;
use Faker\Factory;
use Faker\Generator;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Anibalealvarezs\KlaviyoApi\Enums\Metrics as EnumsMetrics;

class MetricsTest extends TestCase
{
    private array $config;
    private Generator $faker;

    /**
     */
    protected function setUp(): void
    {
        $this->config = Yaml::parseFile(__DIR__ . "/../../config/config.yaml");
        $this->faker = Factory::create();
    }

    /**
     * @throws GuzzleException
     */
    public function testGetValuesForMetric(): void
    {
        $values = Metrics::getValuesForMetric(
            config: $this->config,
            metricName: EnumsMetrics::placed_order,
            from: (new Carbon($this->faker->dateTimeBetween('-1 year', '-1 month')))->toDateString(),
            to: (new Carbon($this->faker->dateTimeBetween('-1 month')))->toDateString(),
            timezone: $this->faker->timezone('US'),
            interval: $this->faker->randomElements([
                Interval::hour,
                Interval::day,
                Interval::week,
                Interval::month
            ])[0],
        );

        $this->assertIsArray($values);
        $this->assertArrayHasKey('data', $values);
        $this->assertIsArray($values['data']);
        $this->assertArrayHasKey('type', $values['data']);
        $this->assertArrayHasKey('id', $values['data']);
        $this->assertArrayHasKey('attributes', $values['data']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetBiggerIntervalsValuesForMetric(): void
    {
        $values = Metrics::getBiggerIntervalsValuesForMetric(
            config: $this->config,
            metricName: EnumsMetrics::placed_order,
            from: (new Carbon($this->faker->dateTimeBetween('-1 year', '-1 month')))->toDateString(),
            to: (new Carbon($this->faker->dateTimeBetween('-1 month')))->toDateString(),
            timezone: $this->faker->timezone('US'),
            interval: $this->faker->randomElements([Interval::year, Interval::lifetime])[0],
        );

        $this->assertIsArray($values);
        $this->assertArrayHasKey('data', $values);
        $this->assertIsArray($values['data']);
        $this->assertArrayHasKey('attributes', $values['data']);
    }

    /**
     * @throws GuzzleException
     */
        public function testGetMetricIdByName(): void
    {
        $metricId = Metrics::getMetricIdByName(
            name: EnumsMetrics::placed_order->value,
            config: $this->config,
        );

        $this->assertIsString($metricId);
        $this->assertNotEmpty($metricId);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetSendEmailFlowActions(): void
    {
        $values = Metrics::getSendEmailFlowActions(
            config: $this->config,
        );

        $this->assertIsArray($values);
        if ($values) {
            $this->assertIsArray($values[array_key_first($values)]);
        }
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMessagesSentFromFlows(): void
    {
        $values = Metrics::getMessagesSentFromFlows(
            config: $this->config,
            sendEmailFlows: Metrics::getSendEmailFlowActions(config: $this->config)
        );

        $this->assertIsArray($values);
        if ($values) {
            $this->assertIsArray($values[array_key_first($values)]);
            $this->assertArrayHasKey('data', $values[array_key_first($values)]);
            $this->assertIsArray($values[array_key_first($values)]['data']);
            $this->assertArrayHasKey('id', $values[array_key_first($values)]['data'][0]);
            $this->assertArrayHasKey('attributes', $values[array_key_first($values)]['data'][0]);
            $this->assertIsArray($values[array_key_first($values)]['data'][0]['attributes']);
            $this->assertArrayHasKey('updated', $values[array_key_first($values)]['data'][0]['attributes']);
        }
    }
}