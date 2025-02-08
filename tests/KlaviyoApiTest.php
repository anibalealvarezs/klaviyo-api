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
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class KlaviyoApiTest extends TestCase
{
    private KlaviyoApi $klaviyoApi;
    private string $metricId;
    private Generator $faker;

    /**
     * @throws GuzzleException
     */
    protected function setUp(): void
    {
        $config = Yaml::parseFile(__DIR__ . "/../config/config.yaml");
        $this->klaviyoApi = new KlaviyoApi(
            apiKey: $config['klaviyo_api_key']
        );
        $this->metricId = Metrics::getMetricIdByName(
            name: EnumsMetrics::placed_order->value,
            config: $config,
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
        $metrics = $this->klaviyoApi->getMetrics(
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
        $metricData = $this->klaviyoApi->getMetricData(
            metricId: $this->metricId,
        );

        $this->assertIsArray($metricData);
        $this->assertArrayHasKey('data', $metricData);
        $this->assertIsArray($metricData['data']);
        $this->assertArrayHasKey('type', $metricData['data']);
        $this->assertArrayHasKey('id', $metricData['data']);
        $this->assertArrayHasKey('attributes', $metricData['data']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMetricAggregates() {
        $metricAggregates = $this->klaviyoApi->getMetricAggregates(
            metricId: $this->metricId,
            /* sort: $this->faker->randomElements([Sort::ascending, Sort::descending])[0],
            sortField: $this->faker->randomElements([
                '$attributed_channel',
                '$attributed_flow',
                '$attributed_message',
                '$attributed_variation',
                '$campaign_channel',
                '$flow',
                '$flow_channel',
                '$message',
                '$message_send_cohort',
                '$variation',
                '$variation_send_cohort',
                'Bounce Type',
                'Campaign Name',
                'Client Canonical',
                'Client Name',
                'Client Type',
                'Email Domain',
                'Failure Source',
                'Failure Type',
                'From Number',
                'From Phone Region',
                'List',
                'Message Name',
                'Message Type',
                'Method',
                'Subject',
                'To Number',
                'To Phone Region',
                'URL',
                'count',
                'form_id',
                'sum_value',
                'unique',
            ])[0], */
            count: $this->faker->numberBetween(1, 10000),
            measurements: $this->faker->randomElements([
                AggregatedMeasurement::count,
                AggregatedMeasurement::sum_value,
                AggregatedMeasurement::unique,
            ], null),
            interval: $this->faker->randomElements([
                Interval::hour,
                Interval::day,
                Interval::week,
                Interval::month,
            ])[0],
            filter: [
                [
                    "operator" => 'greater-or-equal',
                    "field" => 'datetime',
                    "value" => (new Carbon($this->faker->dateTimeBetween('-1 year', '-1 month')))->format('Y-m-d\TH:i:s'),
                ],
                [
                    "operator" => 'less-than',
                    "field" => 'datetime',
                    "value" => (new Carbon($this->faker->dateTimeBetween('-1 month')))->format('Y-m-d\TH:i:s'),
                ],
            ],
            timezone: $this->faker->timezone('US'),
        );

        $this->assertIsArray($metricAggregates);
        $this->assertArrayHasKey('data', $metricAggregates);
        $this->assertIsArray($metricAggregates['data']);
        $this->assertArrayHasKey('type', $metricAggregates['data']);
        $this->assertArrayHasKey('id', $metricAggregates['data']);
        $this->assertArrayHasKey('attributes', $metricAggregates['data']);
        $this->assertIsArray($metricAggregates['data']['attributes']);
        $this->assertArrayHasKey('dates', $metricAggregates['data']['attributes']);
        $this->assertIsArray($metricAggregates['data']['attributes']['dates']);
        $this->assertArrayHasKey('data', $metricAggregates['data']['attributes']);
        $this->assertIsArray($metricAggregates['data']['attributes']['data']);
        $this->assertArrayHasKey('dimensions', $metricAggregates['data']['attributes']['data'][0]);
        $this->assertArrayHasKey('measurements', $metricAggregates['data']['attributes']['data'][0]);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetMetricProfiles(): void
    {
        $metricProfiles = $this->klaviyoApi->getMetricProfiles(
            metricId: $this->metricId,
        );

        $this->assertIsArray($metricProfiles);
        if (count($metricProfiles) > 0) {
            $this->assertArrayHasKey('campaigns', $metricProfiles[array_key_first($metricProfiles)]);
            $this->assertIsArray($metricProfiles[array_key_first($metricProfiles)['campaigns']]);
        }
    }

    /**
     * @throws GuzzleException
     */
    public function testGetFlows(): void
    {
        $flows = $this->klaviyoApi->getFlows(
            flowActionFields: $this->faker->randomElements([
                'action_type',
                'status',
                'created',
                'updated',
                'settings',
                'tracking_options',
                'send_options',
                'send_options.use_smart_sending',
                'send_options.is_transactional',
                'render_options',
                'render_options.shorten_links',
                'render_options.add_org_prefix',
                'render_options.add_info_link',
                'render_options.add_opt_out_language',
            ], null),
            flowFields: $this->faker->randomElements([
                'name',
                'status',
                'archived',
                'created',
                'updated',
                'trigger_type',
            ], null),
            count: $this->faker->numberBetween(1, 50),
            sort: $this->faker->randomElements([Sort::ascending, Sort::descending])[0],
            sortField: $this->faker->randomElements([
                'created',
                'id',
                'name',
                'status',
                'trigger_type',
                'updated',
            ])[0],
        );

        $this->assertIsArray($flows);
        $this->assertArrayHasKey('data', $flows);
        $this->assertIsArray($flows['data']);
        $this->assertArrayHasKey('type', $flows['data'][0]);
        $this->assertArrayHasKey('id', $flows['data'][0]);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetFlowData(): void
    {
        $flows = $this->klaviyoApi->getFlows(count: 1);
        $flowData = $this->klaviyoApi->getFlowData(
            flowId: $flows['data'][0]['id'],
            flowActionFields: $this->faker->randomElements([
                'action_type',
                'status',
                'created',
                'updated',
                'settings',
                'tracking_options',
                'send_options',
                'send_options.use_smart_sending',
                'send_options.is_transactional',
                'render_options',
                'render_options.shorten_links',
                'render_options.add_org_prefix',
                'render_options.add_info_link',
                'render_options.add_opt_out_language',
            ], null),
            flowFields: $this->faker->randomElements([
                'name',
                'status',
                'archived',
                'created',
                'updated',
                'trigger_type',
            ], null),
        );

        $this->assertIsArray($flowData);
        $this->assertArrayHasKey('data', $flowData);
        $this->assertIsArray($flowData['data']);
        $this->assertArrayHasKey('type', $flowData['data']);
        $this->assertArrayHasKey('id', $flowData['data']);
        $this->assertArrayHasKey('attributes', $flowData['data']);
        $this->assertArrayHasKey('relationships', $flowData['data']);
        $this->assertIsArray($flowData['data']['relationships']);
        $this->assertArrayHasKey('flow-actions', $flowData['data']['relationships']);
        $this->assertArrayHasKey('data', $flowData['data']['relationships']['flow-actions']);
        $this->assertIsArray($flowData['data']['relationships']['flow-actions']['data']);
        if (count($flowData['data']['relationships']['flow-actions']['data']) > 0) {
            $this->assertArrayHasKey('type', $flowData['data']['relationships']['flow-actions']['data'][0]);
            $this->assertArrayHasKey('id', $flowData['data']['relationships']['flow-actions']['data'][0]);
        }
    }
}