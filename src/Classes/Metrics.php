<?php

namespace Anibalealvarezs\KlaviyoApi\Classes;

use Carbon\Carbon;
use Anibalealvarezs\KlaviyoApi\Enums\AggregatedMeasurement;
use Anibalealvarezs\KlaviyoApi\Enums\Interval;
use Anibalealvarezs\KlaviyoApi\Enums\Metrics as EnumsMetrics;
use Anibalealvarezs\KlaviyoApi\KlaviyoApi;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class Metrics
{
    /**
     * @param array $config
     * @param EnumsMetrics $metricName
     * @param string $from
     * @param string $to
     * @param string $integration
     * @param Interval $interval
     * @param array|null $metrics
     * @param string $timezone
     * @param array $partitionedBy
     * @param array|null $filter
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public static function getValuesForMetric(
        array $config,
        EnumsMetrics $metricName,
        string $from,
        string $to,
        string $timezone,
        string $integration = "",
        Interval $interval = Interval::day,
        ?array $metrics = null,
        array $partitionedBy = [],
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
    ): array {
        if (empty($config['klaviyo_api_key'])) {
            throw new Exception("Missing API key");
        }
        if (!$metricId = self::getMetricIdByName(
            name: $metricName->value,
            config: $config,
            metrics: $metrics,
            integration: $integration,
        )) {
            throw new Exception("Metric not found");
        }
        $client = new KlaviyoApi(apiKey: $config['klaviyo_api_key']);
        if (!$filter) {
            $filter = [];
        }
        return $client->getMetricAggregates(
            metricId: $metricId,
            groupBy: $partitionedBy,
            measurements: $metricName->getMeasurements(),
            interval: $interval,
            filter: [
                ...$filter,
                [
                    "operator" => "greater-or-equal",
                    "field" => "datetime",
                    "value" => Carbon::parse($from)->toIso8601String(),
                ],
                [
                    "operator" => "less-than",
                    "field" => "datetime",
                    "value" => Carbon::parse($to)->toIso8601String(),
                ],
            ],
            timezone: $timezone,
        );
    }

    /**
     * @param array $config
     * @param EnumsMetrics $metricName
     * @param string $from
     * @param string $to
     * @param string $integration
     * @param Interval $interval
     * @param array|null $metrics
     * @param string $timezone
     * @param array $partitionedBy
     * @param array|null $filter
     * @return array
     * @throws GuzzleException
     */
    public static function getBiggerIntervalsValuesForMetric(
        array $config,
        EnumsMetrics $metricName,
        string $from,
        string $to,
        string $timezone,
        string $integration = "",
        Interval $interval = Interval::lifetime,
        ?array $metrics = null,
        array $partitionedBy = [],
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
    ): array {
        $periodicalData = [];
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);
        while($from->lessThan($to)) {
            $tempTo = $from->copy()->addYear()->subSecond();
            if ($tempTo->greaterThanOrEqualTo($to)) {
                $tempTo = $to->copy();
            }

            $currentYearPeriodicalData = self::getValuesForMetric(
                config: $config,
                metricName: $metricName,
                from: Carbon::parse($from)->toIso8601String(),
                to: Carbon::parse($tempTo)->toIso8601String(),
                timezone: $timezone,
                integration: $integration,
                interval: in_array($interval, [Interval::lifetime, Interval::year]) ? Interval::month : $interval,
                metrics: $metrics,
                partitionedBy: $partitionedBy,
                filter: $filter,
            );
            $from->addYear();

            // Fix for empty dimensions
            if ($currentYearPeriodicalData['data']['attributes']['data'][0]['dimensions'] === []) {
                $currentYearPeriodicalData['data']['attributes']['data'][0]['dimensions'] = [''];
            }
            // End fix

            if (!isset($periodicalData['data']['attributes']['dates'])) {
                $periodicalData = $currentYearPeriodicalData;
                continue;
            }

            $periodicalData['data']['attributes']['dates'] = [...$periodicalData['data']['attributes']['dates'], ...$currentYearPeriodicalData['data']['attributes']['dates']];
            $totalDates = count($periodicalData['data']['attributes']['dates']);
            foreach($currentYearPeriodicalData['data']['attributes']['data'] as $index => $currentRow) {
                $found = false;
                foreach($periodicalData['data']['attributes']['data'] as $dataKey => $row) {
                    if ($row['dimensions'] == $currentRow['dimensions']) {
                        foreach($row['measurements'] as $key => $value) {
                            $periodicalData['data']['attributes']['data'][$dataKey]['measurements'][$key] = [
                                ...$periodicalData['data']['attributes']['data'][$dataKey]['measurements'][$key],
                                ...$currentRow['measurements'][$key]
                            ];
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $currentDates = count($currentYearPeriodicalData['data']['attributes']['dates']);
                    foreach($currentRow['measurements'] as $key => $value) {
                        $zeros = array_fill(0, $totalDates - $currentDates, 0);
                        $currentRow['measurements'][$key] = [...$zeros, ...$currentRow['measurements'][$key]];
                    }
                    $periodicalData['data']['attributes']['data'][] = $currentRow;
                }
            }
            foreach($periodicalData['data']['attributes']['data'] as $dataKey => $row) {
                foreach($row['measurements'] as $key => $value) {
                    $currentDates = count($value);
                    if ($currentDates < $totalDates) {
                        $zeros = array_fill(0, $totalDates - $currentDates, 0);
                        $periodicalData['data']['attributes']['data'][$dataKey]['measurements'][$key] = [...$value, ...$zeros];
                    }
                }
            }
        }
        return match ($interval) {
            Interval::hour,
            Interval::day,
            Interval::week,
            Interval::month => $periodicalData,
            Interval::year => self::monthlyDataToYearly(
                monthlyData: $periodicalData,
                timezone: $timezone,
            ),
            Interval::lifetime => self::monthlyDataToLifetime(
                monthlyData: $periodicalData,
            ),
        };
    }

    /**
     * @param array $config
     * @param array $sendEmailFlows
     * @return array
     * @throws GuzzleException
     */
    public static function getMessagesSentFromFlows(
        array $config,
        array $sendEmailFlows
    ): array {
        return self::loopRequestFlowMessages(
            config: $config,
            sendEmailFlows: $sendEmailFlows,
        );
    }

    /**
     * @param array $flowMessages
     * @param array $sendEmailFlows
     * @param string $from
     * @param string $to
     * @param Interval $interval
     * @param string $timezone
     * @return array
     */
    public static function processMessagesFromFlows(
        array $flowMessages,
        array $sendEmailFlows,
        string $from,
        string $to,
        string $timezone,
        Interval $interval = Interval::day,
    ): array {
        $aggregatedFlows = self::aggregateFlows(
            retrievedMessages: $flowMessages,
            sendEmailFlows: $sendEmailFlows,
            from: $from,
            to: $to,
            timezone: $timezone,
            interval: $interval,
        );
        return self::toAggregatedMetricsFormat(
            data: $aggregatedFlows,
            from: $from,
            to: $to,
            timezone: $timezone,
            interval: $interval,
        );
    }

    /**
     * @param array $config
     * @param array $sendEmailFlows
     * @return array
     * @throws GuzzleException
     */
    protected static function loopRequestFlowMessages(
        array $config,
        array $sendEmailFlows
    ): array {
        $client = new KlaviyoApi(apiKey: $config['klaviyo_api_key']);
        $retrievedMessages = [];
        foreach ($sendEmailFlows as $flow) {
            foreach ($flow as $flowActionId) {
                if (!array_key_exists($flowActionId, $retrievedMessages)) {
                    $retrievedMessages[$flowActionId] = $client->getMessagesForFlowAction(
                        flowActionId: $flowActionId,
                        fields: ['id', 'updated'],
                    );
                }
                usleep(950000);
            }
        }

        return $retrievedMessages;
    }

    /**
     * @param array $retrievedMessages
     * @param array $sendEmailFlows
     * @param string $from
     * @param string $to
     * @param Interval $interval
     * @param string $timezone
     * @return array
     */
    protected static function aggregateFlows(
        array $retrievedMessages,
        array $sendEmailFlows,
        string $from,
        string $to,
        string $timezone,
        Interval $interval,
    ): array {
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);
        $results = [
            "total" => [],
            "partitioned" => [],
        ];
        $messages = [];
        foreach ($sendEmailFlows as $flowId => $flow) {
            foreach ($flow as $flowActionId) {
                $alreadyCounted = true;
                if (!array_key_exists($flowActionId, $messages)) {
                    $messages[$flowActionId] = $retrievedMessages[$flowActionId];
                    $alreadyCounted = false;
                }
                foreach ($messages[$flowActionId]['data'] as $message) {
                    $current = Carbon::parse($message['attributes']['updated']);
                    if ($current->lessThan($from) || $current->greaterThan($to)) {
                        continue;
                    }
                    $key = match ($interval->value) {
                        "day" => $current->setTimezone($timezone)->startOfDay()->toIso8601String(),
                        "month" => $current->setTimezone($timezone)->startOfMonth()->toIso8601String(),
                        "year" => $current->setTimezone($timezone)->startOfYear()->toIso8601String(),
                        "week" => $current->setTimezone($timezone)->startOfWeek()->toIso8601String(),
                        "hour" => $current->setTimezone($timezone)->startOfHour()->toIso8601String(),
                    };
                    if (!array_key_exists($key, $results['total'])) {
                        $results['total'][$key] = 0;
                    }
                    if (!array_key_exists($flowId, $results['partitioned'])) {
                        $results['partitioned'][$flowId] = [];
                    }
                    if (!array_key_exists($key, $results['partitioned'][$flowId])) {
                        $results['partitioned'][$flowId][$key] = 0;
                    }
                    if (!$alreadyCounted) {
                        $results['total'][$key] += 1;
                    }
                    $results['partitioned'][$flowId][$key] += 1;
                }
            }
        }

        return $results;
    }

    /**
     * @param array $config
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public static function getSendEmailFlowActions(
        array $config
    ): array {
        if (empty($config['klaviyo_api_key'])) {
            throw new Exception("Missing API key");
        }
        $client = new KlaviyoApi(apiKey: $config['klaviyo_api_key']);
        $response = $client->getFlows(
            flowActionFields: ['id', 'action_type'],
            flowFields: ['id'],
        );
        $sendEmailFlows = [];
        $flowActions = [];
        foreach ($response['included'] as $flowAction) {
            if ($flowAction['type'] === 'flow-action' && $flowAction['attributes']['action_type'] === 'SEND_EMAIL') {
                $flowActions[] = $flowAction['id'];
            }
        }
        foreach ($response['data'] as $flow) {
            foreach ($flow['relationships']['flow-actions']['data'] as $action) {
                if (in_array($action['id'], $flowActions)) {
                    if (!isset($sendEmailFlows[$flow['id']])) {
                        $sendEmailFlows[$flow['id']] = [];
                    }
                    $sendEmailFlows[$flow['id']][] = $action['id'];
                }
            }
        }
        return $sendEmailFlows;
    }

    /**
     * @param array $campaigns
     * @param string $from
     * @param string $to
     * @param Interval $interval
     * @param string $timezone
     * @return array
     */
    public static function getMessagesFromCampaignsLegacy(
        array $campaigns,
        string $from,
        string $to,
        string $timezone,
        Interval $interval = Interval::day,
    ): array {
        $aggregatedCampaigns = self::aggregateCampaignsMessages(
            campaigns: $campaigns,
            interval: $interval,
            timezone: $timezone,
        );

        return self::toAggregatedMetricsFormat(
            data: $aggregatedCampaigns,
            from: $from,
            to: $to,
            timezone: $timezone,
            interval: $interval,
        );
    }

    /**
     * @param array $data
     * @param string $from
     * @param string $to
     * @param Interval $interval
     * @param string $timezone
     * @return array
     */
    protected static function toAggregatedMetricsFormat(
        array $data,
        string $from,
        string $to,
        string $timezone,
        Interval $interval,
    ): array {
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);
        $start = match ($interval->value) {
            "day" => $from->setTimezone($timezone)->startOfDay(),
            "month" => $from->setTimezone($timezone)->startOfMonth(),
            "year" => $from->setTimezone($timezone)->startOfYear(),
            "week" => $from->setTimezone($timezone)->startOfWeek(),
            "hour" => $from->setTimezone($timezone)->startOfHour(),
        };
        $end = match ($interval->value) {
            "day" => $to->setTimezone($timezone)->startOfDay(),
            "month" => $to->setTimezone($timezone)->startOfMonth(),
            "year" => $to->setTimezone($timezone)->startOfYear(),
            "week" => $to->setTimezone($timezone)->startOfWeek(),
            "hour" => $to->setTimezone($timezone)->startOfHour(),
        };
        $step = match ($interval->value) {
            "day" => "1 day",
            "month" => "1 month",
            "year" => "1 year",
            "week" => "1 week",
            "hour" => "1 hour",
        };

        $formatted = [];
        foreach ($data as $versionKey => $version) {
            if (!array_key_exists($versionKey, $formatted)) {
                $formatted[$versionKey] = [
                    "dates" => [],
                    "data" => [],
                ];
            }
            $startCopy = clone $start;
            while ($startCopy->lessThanOrEqualTo($end)) {
                $formatted[$versionKey]["dates"][] = $startCopy->toIso8601String();
                $startCopy = $startCopy->add($step);
            }

            // Format "total" data
            if ($versionKey === "total") {
                $formatted[$versionKey]["data"][] =  [
                    "dimensions" => [""],
                    "measurements" => [
                        "count" => []
                    ],
                ];
                $startCopy = clone $start;
                while ($startCopy->lessThanOrEqualTo($end)) {
                    if (isset($version[$startCopy->toIso8601String()])) {
                        $formatted["total"]["data"][0]["measurements"]["count"][] = $version[$startCopy->toIso8601String()];
                    } else {
                        $formatted["total"]["data"][0]["measurements"]["count"][] = 0;
                    }
                    $startCopy = $startCopy->add($step);
                }
            }

            // Format "partitioned" data
            if ($versionKey === "partitioned") {
                foreach ($version as $flowOrCampaign => $values) {
                    $current = [
                        "dimensions" => [$flowOrCampaign],
                        "measurements" => [
                            "count" => []
                        ],
                    ];
                    $startCopy = clone $start;
                    while ($startCopy->lessThanOrEqualTo($end)) {
                        if (isset($values[$startCopy->toIso8601String()])) {
                            $current["measurements"]["count"][] = $values[$startCopy->toIso8601String()];
                        } else {
                            $current["measurements"]["count"][] = 0;
                        }
                        $startCopy = $startCopy->add($step);
                    }
                    $formatted["partitioned"]["data"][] =  $current;
                }
            }
        }

        return $formatted;
    }

    /**
     * @param array $campaigns
     * @param Interval $interval
     * @param string $timezone
     * @return array
     */
    public static function aggregateCampaignsMessages(
        array $campaigns,
        Interval $interval,
        string $timezone,
    ): array {
        $results = [
            "total" => [],
            "partitioned" => [],
        ];

        foreach ($campaigns as $campaign) {
            $key = match ($interval->value) {
                "day" => Carbon::parse($campaign['sent_at'])->setTimezone($timezone)->startOfDay()->toIso8601String(),
                "month" => Carbon::parse($campaign['sent_at'])->setTimezone($timezone)->startOfMonth()->toIso8601String(),
                "year" => Carbon::parse($campaign['sent_at'])->setTimezone($timezone)->startOfYear()->toIso8601String(),
                "week" => Carbon::parse($campaign['sent_at'])->setTimezone($timezone)->startOfWeek()->toIso8601String(),
                "hour" => Carbon::parse($campaign['sent_at'])->setTimezone($timezone)->startOfHour()->toIso8601String(),
            };
            if (!array_key_exists($key, $results['total'])) {
                $results['total'][$key] = 0;
            }
            if (!array_key_exists($campaign['id'], $results['partitioned'])) {
                $results['partitioned'][$campaign['id']] = [];
            }
            if (!array_key_exists($key, $results['partitioned'][$campaign['id']])) {
                $results['partitioned'][$campaign['id']][$key] = 0;
            }
            $results['total'][$key] += $campaign['num_recipients'];
            $results['partitioned'][$campaign['id']][$key] += $campaign['num_recipients'];
        }

        return $results;
    }

    /**
     * @param array $monthlyData
     * @param string $timezone
     * @return array
     */
    protected static function monthlyDataToYearly(
        array $monthlyData,
        string $timezone,
    ): array {
        $yearlyData = [
            'attributes' => [
                "dates" => [],
                "data" => [],
            ]
        ];

        $keys = [];
        foreach ($monthlyData['data']['attributes']['dates'] as $month) {
            $year = Carbon::parse($month)->setTimezone($timezone)->startOfYear()->toIso8601String();
            if (!in_array($year, $yearlyData['attributes']['dates'])) {
                $yearlyData['attributes']['dates'][] = $year;
            }
            $keys[] = $year;
        }

        foreach ($monthlyData['data']['attributes']['data'] as $dimension) {
            $measurements = [];
            foreach ($dimension['measurements'] as $case => $values) {
                if (!array_key_exists($case, $measurements)) {
                    $measurements[$case] = [];
                    foreach ($values as $counter => $value) {
                        if (!array_key_exists($keys[$counter], $measurements[$case])) {
                            $measurements[$case][$keys[$counter]] = 0;
                        }
                        $measurements[$case][$keys[$counter]] += $value;
                    }
                }
            }
            foreach ($measurements as $case => $values) {
                $measurements[$case] = array_values($values);
            }
            $yearlyData['attributes']['data'][] = [
                "dimensions" => $dimension['dimensions'],
                "measurements" => $measurements,
            ];
        }
        return ['data' => $yearlyData];
    }

    /**
     * @param array $monthlyData
     * @return array
     */
    protected static function monthlyDataToLifetime(
        array $monthlyData
    ): array {
        $lifetimeData = [
            'attributes' => [
                "dates" => [ "lifetime" ],
                "data" => [],
            ]
        ];

        foreach ($monthlyData['data']['attributes']['data'] as $dimension) {
            $measurements = [];
            foreach ($dimension['measurements'] as $case => $values) {
                $measurements[$case] = [
                    array_sum($values),
                ];
            }
            $lifetimeData['attributes']['data'][] = [
                "dimensions" => $dimension['dimensions'],
                "measurements" => $measurements,
            ];
        }
        return ['data' => $lifetimeData];
    }

    /**
     * @param array $dates
     * @param array $data
     * @param string $from
     * @param string $timezone
     * @return array
     */
    public static function buildTablesFromAggregatedData(
        array $dates,
        array $data,
        string $from,
        string $timezone,
    ): array {
        // bypass previous dates
        $shifted = false;
        if ($dates[0] != 'lifetime') {
            if (Carbon::parse($dates[0])->lt(Carbon::parse($from))) {
                array_shift($dates);
                $dates = array_values($dates);
                $shifted = true;
            }
        }
        // end of bypass
        $results = [];
        $header = [
            ['Dimensions'],
            ['Dates']
        ];
        $series = [];

        foreach ($data as $case) {
            $rows = [];
            $countMeasurements = 0;
            foreach (["count", "sum_value", "unique"] as $measurement) {
                if (array_key_exists($measurement, $case['measurements'])) {
                    if ($countMeasurements == 0) {
                        $series[implode(' / ', $case['dimensions'])] = [];
                        $header[0][] = implode(' / ', $case['dimensions']);
                    } else {
                        $header[0][] = '';
                    }
                    $countMeasurements++;
                    $header[1][] = $measurement;
                    $series[implode(' / ', $case['dimensions'])][] = $measurement;
                    // bypass previous dates & join first two values
                    if ($shifted && count($case['measurements'][$measurement]) > count($dates)) {
                        $removed = array_shift($case['measurements'][$measurement]);
                        $case['measurements'][$measurement] = array_values($case['measurements'][$measurement]);
                        $case['measurements'][$measurement][0] += $removed;
                    }
                    // end of bypass
                    $counter = 0;
                    foreach ($case['measurements'][$measurement] as $value) {
                        $tempDate = Carbon::parse($dates[$counter])->setTimezone($timezone)->toIso8601String();
                        if (!array_key_exists($tempDate, $rows)) {
                            $rows[$tempDate] = [];
                        }
                        $rows[$tempDate][$measurement] = $value;
                        $counter++;
                    }
                }
            }
            $results[implode(' / ', $case['dimensions'])] = $rows;
        }

        $dimensions = array_keys($results);
        $body = [];
        foreach ($dates as $date) {
            $tempDate = Carbon::parse($date)->setTimezone($timezone)->toIso8601String();
            $row = [$tempDate];
            foreach ($dimensions as $dimension) {
                if (array_key_exists($tempDate, $results[$dimension])) {
                    foreach ($series[$dimension] as $measurement) {
                        if (array_key_exists($measurement, $results[$dimension][$tempDate])) {
                            $row[] = $results[$dimension][$tempDate][$measurement];
                        } else {
                            $row[] = 0;
                        }
                    }
                } else {
                    $row = array_fill(0, count($series[$dimension]), '');
                }
            }
            $body[] = $row;
        }

        foreach ($header[1] as $key => $value) {
            if ($key > 0 && ($enum = AggregatedMeasurement::fromName($value))) {
                $header[1][$key] = $enum->getLabel();
            }
        }
        return [...$header, ...$body];
    }

    /**
     * @param string $name
     * @param array|null $config
     * @param array|null $metrics
     * @param string $integration
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    public static function getMetricIdByName(
        string $name,
        ?array $config = null,
        ?array $metrics = null,
        string $integration = '',
    ): string {
        if (is_null($metrics) && (is_null($config) || !array_key_exists('klaviyo_api_key', $config))) {
            throw new Exception('Config is required to get metric id by name');
        }
        if (is_null($metrics)) {
            $client = new KlaviyoApi(apiKey: $config['klaviyo_api_key']);
            $metrics = $client->getMetrics();
        }
        foreach ($metrics['data'] as $metric) {
            if ($metric['attributes']['name'] === $name && (!$integration || $metric['attributes']['integration']['name'] === $integration)) {
                return $metric['id'];
            }
        }
        return '';
    }

    /**
     * @param array $tables
     * @return array
     */
    public static function joinTables(
        array $tables
    ): array {
        $joinedTables = [];
        foreach ($tables as $key => $table) {
            $data = $table['data'];
            $header = $table['header'];
            if ($key == 0) {
                $joinedTables = $data;
                if (!is_null($header)) {
                    $joinedTables[0][1] = $header;
                }
            } else {
                if (!is_null($header)) {
                    $joinedTables[0][count($joinedTables[0])] = $header;
                    $missingColumnsNumber = count($data[0]) - 2;
                    $missingHeaders = array_fill(
                        start_index: count($joinedTables[0]),
                        count: max($missingColumnsNumber, 0),
                        value: ''
                    );
                    $joinedTables[0] = [
                        ...$joinedTables[0],
                        ...$missingHeaders,
                    ];
                }
                foreach ($joinedTables as $key2 => $row) {
                    if ($key2 > 0 && isset($data[$key2])) {
                        array_shift($data[$key2]);
                        $joinedTables[$key2] = [
                            ...$row,
                            ...$data[$key2],
                        ];
                    }
                }
            }
        }

        foreach ($joinedTables as $key => $row) {
            $joinedTables[$key] = [
                'values' => []
            ];
            foreach ($row as $key2 => $value) {
                if (is_numeric($value) && $key2 > 0 && $key > 1) {
                    $joinedTables[$key]['values'][$key2] = ['userEnteredValue' => ['numberValue' => $value]];
                } elseif ($value && str_starts_with($value, '=')) {
                    $joinedTables[$key]['values'][$key2] = ['userEnteredValue' => ['formulaValue' => $value]];
                } else {
                    $joinedTables[$key]['values'][$key2] = ['userEnteredValue' => ['stringValue' => $value]];
                }
            }
        }
        return $joinedTables;
    }

    /**
     * @param array $config
     * @param EnumsMetrics $metricName
     * @param string $from
     * @param string $to
     * @param array|null $metrics
     * @param string $integration
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public static function getEventsForMetric(
        array $config,
        EnumsMetrics $metricName,
        string $from,
        string $to,
        ?array $metrics = null,
        string $integration = '',
    ): array {
        if (empty($config['klaviyo_api_key'])) {
            throw new Exception("Missing API key");
        }
        if (!$metricId = self::getMetricIdByName(
            name: $metricName->value,
            config: $config,
            metrics: $metrics,
            integration: $integration,
        )) {
            throw new Exception("Metric not found");
        }
        $client = new KlaviyoApi(apiKey: $config['klaviyo_api_key']);
        $filter =  [
            [
                "operator" => "equals",
                "field" => "metric_id",
                "value" => '"'.$metricId.'"',
            ],
            [
                "operator" => "greater-or-equal",
                "field" => "datetime",
                "value" => Carbon::parse($from)->toIso8601String(),
            ],
            [
                "operator" => "less-than",
                "field" => "datetime",
                "value" => Carbon::parse($to)->toIso8601String(),
            ],
        ];
        return match ($metricName) {
            EnumsMetrics::clicked_email,
            EnumsMetrics::unsubscribed_from_list,
            EnumsMetrics::opened_email,
            EnumsMetrics::bounced_email => $client->getAllEvents(
                eventFields: ['datetime', 'id', 'profile_id', 'event_properties'],
                profileFields: ['id', 'email', 'location'],
                filter: $filter,
                includeMetrics: false
            ),
            EnumsMetrics::checkout_completed,
            EnumsMetrics::placed_order,
            EnumsMetrics::ordered_product,
            EnumsMetrics::refunded_order,
            EnumsMetrics::subscribed_to_list => throw new Exception('To be implemented'),
        };
    }

    /**
     * @param array $events
     * @return void
     */
    public static function joinGroupedEventsIntoOne(
        array &$events
    ): void {
        $firstElement = [
            'dimensions' => [],
            'measurements' => [],
        ];
        foreach ($events['data']['attributes']['data'] as $key => $value) {
            $filteredDimensions = array_values(array_filter($events['data']['attributes']['data'][$key]['dimensions']));
            if (count($filteredDimensions) > 0) {
                foreach ($value['measurements'] as $measurementKey => $measurementArray) {
                    if (!isset($firstElement['measurements'][$measurementKey])) {
                        $firstElement['measurements'][$measurementKey] = [];
                    }
                    foreach($measurementArray as $measurementIndex => $measurementValue) {
                        if (!isset($firstElement['measurements'][$measurementKey][$measurementIndex])) {
                            $firstElement['measurements'][$measurementKey][$measurementIndex] = 0;
                        }
                        $firstElement['measurements'][$measurementKey][$measurementIndex] += $measurementValue;
                    }
                }
            }
        }
        $events['data']['attributes']['data'] = [$firstElement];
    }
}
