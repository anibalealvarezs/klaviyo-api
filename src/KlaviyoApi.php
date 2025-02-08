<?php

namespace Anibalealvarezs\KlaviyoApi;

use Carbon\Carbon;
use Anibalealvarezs\ApiSkeleton\Clients\ApiKeyClient;
use Anibalealvarezs\KlaviyoApi\Enums\Interval;
use Anibalealvarezs\KlaviyoApi\Enums\Relationships;
use Anibalealvarezs\KlaviyoApi\Enums\Sort;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class KlaviyoApi extends ApiKeyClient
{
    /**
     * @param string $apiKey
     * @throws RequestException|GuzzleException
     */
    public function __construct(
        string $apiKey,
    ) {
        return parent::__construct(
            baseUrl: 'https://a.klaviyo.com/api/',
            apiKey: $apiKey,
            authSettings: [
                'location' => 'header',
                'name' => 'Authorization',
                'headerPrefix' => 'Klaviyo-API-Key ',
            ],
        );
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $query
     * @param string|array $body
     * @param array $form_params
     * @param string $baseUrl
     * @param array $headers
     * @param array $additionalHeaders
     * @param ?CookieJar $cookies
     * @param bool $verify
     * @param bool|null $allowNewToken
     * @param string $pathToSave
     * @param bool|null $stream
     * @param array|null $errorMessageNesting
     * @param int $sleep
     * @param array $customErrors
     * @param bool $ignoreAuth
     * @param string|null $revision
     * @return Response
     * @throws GuzzleException
     */
    public function performRequest(
        string $method,
        string $endpoint,
        array $query = [],
        string|array $body = "",
        array $form_params = [],
        string $baseUrl = "",
        array $headers = [],
        array $additionalHeaders = [],
        ?CookieJar $cookies = null,
        bool $verify = false,
        bool $allowNewToken = false,
        string $pathToSave = "",
        bool $stream = null,
        ?array $errorMessageNesting = null,
        int $sleep = 0,
        array $customErrors = [],
        bool $ignoreAuth = false,
        ?string $revision = null,
    ): Response {
        if ($revision) {
            $additionalHeaders["revision"] = $revision;
            // $additionalHeaders["Authorization"] = "Klaviyo-API-Key " . $this->apiKey;
        } else {
            $query["api_key"] = $this->apiKey;
        }

        if (!$errorMessageNesting) {
            $errorMessageNesting = ['errors' => [['detail']]];
        }

        return parent::performRequest(
            method: $method,
            endpoint: $endpoint,
            query: $query,
            body: $body,
            form_params: $form_params,
            baseUrl: $baseUrl,
            headers: $headers,
            additionalHeaders: $additionalHeaders,
            cookies: $cookies,
            verify: $verify,
            allowNewToken: $allowNewToken,
            pathToSave: $pathToSave,
            stream: $stream,
            errorMessageNesting: $errorMessageNesting,
            sleep: $sleep,
        );
    }

    /**
     * @param string|null $cursor
     * @param array|null $metricFields
     * @param array|null $filter
     * @return array
     * @throws GuzzleException
     */
    public function getMetrics(
        ?string $cursor = null,
        ?array $metricFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
    ): array {
        $query =[];

        if ($metricFields) {
            $query["fields[metric]"] = implode(",", $metricFields);
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "metrics",
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $metricId
     * @param array|null $filter
     * @return array
     * @throws GuzzleException
     */
    public function getMetricData(
        string $metricId,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
    ): array {
        $query =[];

        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "metrics/".$metricId,
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /*
    * @see https://developers.klaviyo.com/en/docs/supported_metrics_and_attributes for supported metrics and attributes
    * @see https://developers.klaviyo.com/en/reference/query_metric_aggregates for @param array $filter
    * @see https://www.iana.org/time-zones for @param string $timezone
    */
    /**
     * @param string $metricId
     * @param array|null $return_fields
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @param int $count
     * @param array|null $groupBy
     * @param array|null $measurements
     * @param Interval $interval
     * @param array|null $filter
     * @param string|null $timezone
     * @return array
     * @throws GuzzleException
     */
    public function getMetricAggregates(
        string $metricId,
        ?array $return_fields = null,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
        int $count = 10000, // Max: 10000
        ?array $groupBy = null,
        ?array $measurements = null, // Array of AggregatedMeasurements enum
        Interval $interval = Interval::day,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?string $timezone = null, // for format see https://www.iana.org/time-zones
    ): array {
        $attributes = [
            "metric_id" => $metricId,
        ];

        if ($return_fields) {
            $attributes["return_fields"] = $return_fields;
        }
        if ($sortField) {
            $attributes["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $attributes["page_cursor"] = $cursor;
        }
        if ($count) {
            $attributes["page_size"] = $count;
        }
        if ($groupBy) {
            $attributes["by"] =$groupBy;
        }
        if ($measurements) {
            $aMeasurements = [];
            foreach ($measurements as $measurement) {
                $aMeasurements[] = $measurement->value;
            }
            $attributes["measurements"] = $aMeasurements;
        }
        if ($interval) {
            $attributes["interval"] = $interval->value;
        }
        if ($filter) {
            $attributes["filter"] = implode(",", $this->getTranslatedFilters($filter));
        }
        if ($timezone) {
            $attributes["timezone"] = $timezone;
        }

        $body = json_encode(
            [
                "data" => [
                    "type" => "metric-aggregate",
                    "attributes" => $attributes,
                ]
            ]
        );

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "POST",
            endpoint: "metric-aggregates",
            body: $body,
            additionalHeaders: [
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $metricId
     * @param string $campaignId
     * @return array
     * @throws GuzzleException
     */
    public function getMetricProfiles(
        string $metricId,
        string $campaignId = '',
    ): array {
        $profiles = [];
        // Request the spreadsheet data
        $events = $this->getAllEventsForMetricLegacy(
            metricId: $metricId,
        );
        foreach ($events['data'] as $key => $event) {
            if (isset($event['event_properties']['$extra']['customer'])) {
                if (!$campaignId || (isset($event['event_properties']['$attribution']['$message']) && $event['event_properties']['$attribution']['$message'] == $campaignId)) {
                    if (!isset($profiles[$event['event_properties']['$extra']['customer']['id']])) {
                        $profiles[$event['event_properties']['$extra']['customer']['id']] = [
                            'campaigns' => [],
                            ...$event['event_properties']['$extra']['customer'],
                        ];
                    }
                    $profiles[$event['event_properties']['$extra']['customer']['id']]['campaigns'][] = $event['event_properties']['$attribution']['$message'] ?? 'unknown';
                }
            }
        }
        // Return response
        return $profiles;
    }

    /**
     * @param array|null $flowActionFields
     * @param array|null $flowFields
     * @param int $count
     * @param array|null $filter
     * @param bool|null $includeActions
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getFlows(
        ?array $flowActionFields = null,
        ?array $flowFields = null,
        int $count = 50, // Max: 50
        ?array $filter = null,
        ?bool $includeActions = true,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $query =[];

        if ($flowActionFields) {
            $query["fields[flow-action]"] = implode(",", $flowActionFields);
        }
        if ($flowFields) {
            $query["fields[flow]"] = implode(",", $flowFields);
        }
        if ($count) {
            $query["page_size"] = $count;
        }
        if ($filter) {
            $list = [];
            foreach ($filter as $value) {
                $list[] = $value['operator'] . "(" . $value['field'] . "," . $value['value']. ")";
            }
            $query["filter"] = implode(',', $list);
        }
        if ($includeActions) {
            $query["include"] = "flow-actions";
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "flows",
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $flowId
     * @param array|null $flowActionFields
     * @param array|null $flowFields
     * @param bool|null $includeActions
     * @return array
     * @throws GuzzleException
     */
    public function getFlowData(
        string $flowId,
        ?array $flowActionFields = null,
        ?array $flowFields = null,
        ?bool $includeActions = true,
    ): array {
        $query =[];

        if ($flowActionFields) {
            $query["fields[flow-action]"] = implode(",", $flowActionFields);
        }
        if ($flowFields) {
            $query["fields[flow]"] = implode(",", $flowFields);
        }
        if ($includeActions) {
            $query["include"] = "flow-actions";
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "flows/".$flowId,
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $flowActionId
     * @param array|null $fields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getMessagesForFlowAction(
        string $flowActionId,
        ?array $fields = null,
        ?array $filter = null,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $query =[];

        if ($fields) {
            $query["fields[flow-message]"] = implode(",", $fields);
        }
        if ($filter) {
            $list = [];
            foreach ($filter as $value) {
                $list[] = $value['operator'] . "(" . $value['field'] . "," . $value['value']. ")";
            }
            $query["filter"] = implode(',', $list);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "flow-actions/".$flowActionId."/flow-messages",
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $tagFields
     * @param array|null $campaignFields
     * @param array|null $filter
     * @param bool|null $includeTags
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $from
     * @param string|null $to
     * @param bool $sentOnly
     * @param string|null $timezone
     * @return array
     * @throws GuzzleException
     */
    public function getAllCampaigns(
        ?array $tagFields = null,
        ?array $campaignFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?bool $includeTags = true,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $from = null,
        ?string $to = null,
        bool $sentOnly = false,
        ?string $timezone = null,
    ): array {
        $cursor = null;
        $campaigns = [];

        do {
            $response = $this->getCampaignsStable(
                tagFields: $tagFields,
                campaignFields: $campaignFields,
                filter: $filter,
                includeTags: $includeTags,
                sort: $sort,
                sortField: $sortField,
                cursor: $cursor,
                from: $from,
                to: $to,
                sentOnly: $sentOnly,
                timezone: $timezone,
            );
            if (!empty($response['data'])) {
                $campaigns = [...$campaigns, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $campaigns];
    }

    /**
     * @param string $from
     * @param string $to
     * @param bool $sentOnly
     * @param string|null $timezone
     * @return array
     * @throws GuzzleException
     */
    public function getAllCampaignsLegacy(string $from, string $to, bool $sentOnly = false, ?string $timezone = null): array
    {
        $parsedFrom = Carbon::parse($from);
        if ($timezone) {
            $parsedFrom->setTimezone($timezone);
        }
        $parsedTo = Carbon::parse($to);
        if ($timezone) {
            $parsedTo->setTimezone($timezone);
        }
        $trickyRequest = $this->getCampaignsLegacy(
            page: 999999999999,
        );
        $iterations = ceil($trickyRequest['total'] / 100);

        $counter = 0;
        $campaigns = [];
        while ($counter < $iterations) {
            $current = $this->getCampaignsLegacy(
                page: $counter,
            );
            foreach ($current['data'] as $campaign) {
                $parsedSentAt = Carbon::parse($campaign['sent_at']);
                if ($timezone) {
                    $parsedSentAt->setTimezone($timezone);
                }
                if ($parsedSentAt->lessThan($parsedFrom) || $parsedSentAt->greaterThan($parsedTo)) {
                    continue;
                }
                if (!$sentOnly || $campaign['status'] === "sent") {
                    $campaigns[] = $campaign;
                }
            }
            $counter++;
        }

        return $campaigns;
    }

    /**
     * @param array|null $tagFields
     * @param array|null $campaignFields
     * @param array|null $filter
     * @param bool|null $includeTags
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @param string|null $from
     * @param string|null $to
     * @param bool $sentOnly
     * @param string|null $timezone
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignsStable(
        ?array $tagFields = null,
        ?array $campaignFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?bool $includeTags = true,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
        ?string $from = null,
        ?string $to = null,
        bool $sentOnly = false,
        ?string $timezone = null,
    ): array {
        $query =[];

        if ($from) {
            $parsedFrom = Carbon::parse($from);
            if ($timezone) {
                $parsedFrom->setTimezone($timezone);
            }
            if (!$filter) {
                $filter = [];
            }
            $filter[] = [
                "operator" => "greater-or-equal",
                "field" => "scheduled_at",
                "value" => $parsedFrom->toIso8601String(),
            ];
        }

        if ($to) {
            $parsedTo = Carbon::parse($to);
            if ($timezone) {
                $parsedTo->setTimezone($timezone);
            }
            if (!$filter) {
                $filter = [];
            }
            $filter[] = [
                "operator" => "less-than",
                "field" => "scheduled_at",
                "value" => $parsedTo->toIso8601String(),
            ];
        }

        if ($sentOnly) {
            if (!$filter) {
                $filter = [];
            }
            $filter[] = [
                "operator" => "equals",
                "field" => "status",
                "value" => "\"Sent\"",
            ];
        }

        if ($tagFields) {
            $query["fields[tag]"] = implode(",", $tagFields);
        }
        if ($campaignFields) {
            $query["fields[campaign]"] = implode(",", $campaignFields);
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }
        if ($includeTags) {
            $query["include"] = "tags";
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaigns",
            query: $query,
            revision: "2022-12-15.pre",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param int $page
     * @param int $count
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignsLegacy(
        int $page = 0,
        int $count = 100, // Max: 100
    ): array {
        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "v1/campaigns",
            query: [
                "page" => $page,
                "count" => $count,
            ],
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param array|null $tagFields
     * @param array|null $campaignFields
     * @param bool|null $includeTags
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignData(
        string $campaignId,
        ?array $tagFields = null,
        ?array $campaignFields = null,
        ?bool $includeTags = true,
    ): array {
        $query =[];

        if ($tagFields) {
            $query["fields[tag]"] = implode(",", $tagFields);
        }
        if ($campaignFields) {
            $query["fields[campaign]"] = implode(",", $campaignFields);
        }
        if ($includeTags) {
            $query["include"] = "tags";
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaigns/".$campaignId,
            query: $query,
            revision: "2022-12-15.pre",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $messageId
     * @param array|null $fields
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignMessage(
        string $messageId,
        ?array $fields = null,
    ): array {
        $query =[];

        if ($fields) {
            $query["fields[campaign-message]"] = implode(",", $fields);
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaign-messages/".$messageId,
            query: $query,
            revision: "2022-12-15.pre",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param array|null $fields
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignTags(
        string $campaignId,
        ?array $fields = null,
    ): array {
        $query =[];

        if ($fields) {
            $query["fields[tag]"] = implode(",", $fields);
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaigns/".$campaignId."/tags",
            query: $query,
            revision: "2022-12-15.pre",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param array|null $fields
     * @param Relationships $relationships
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignRelationships(
        string $campaignId,
        ?array $fields = null,
        Relationships $relationships = Relationships::tags,
    ): array {
        $query =[];

        if ($fields) {
            $query["fields[tag]"] = implode(",", $fields);
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaigns/".$campaignId."/relationships/".$relationships->value,
            query: $query,
            revision: "2022-12-15.pre",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $eventFields
     * @param array|null $metricFields
     * @param array|null $profileFields
     * @param array|null $filter
     * @param bool|null $includeMetrics
     * @param bool|null $includeProfiles
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @return array
     * @throws GuzzleException
     */
    public function getEvents(
        ?array $eventFields = null,
        ?array $metricFields = null,
        ?array $profileFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?bool $includeMetrics = true,
        ?bool $includeProfiles = true,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
    ): array {
        $query =[];

        if ($eventFields) {
            $query["fields[event]"] = implode(",", $eventFields);
        }
        if ($metricFields) {
            $query["fields[metric]"] = implode(",", $metricFields);
        }
        if ($profileFields) {
            $query["fields[profile]"] = implode(",", $profileFields);
        }
        $include = [];
        if ($includeMetrics) {
            $include[] = "metrics";
        }
        if ($includeProfiles) {
            $include[] = "profiles";
        }
        if (!empty($include)) {
            $query["include"] = implode(",", $include);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "events",
            query: $query,
            revision: "2023-01-24",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $metricId
     * @return array
     * @throws GuzzleException
     */
    public function getAllEventsForMetricLegacy(
        string $metricId,
    ): array {
        $cursor = null;
        $events = [];

        do {
            $response = $this->getEventsForMetricLegacy(
                metricId: $metricId,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $events = [...$events, ...$response['data']];
            }
        } while (isset($response['next']) && $response['next'] && ($response['next'] != "null") && ($cursor = self::getCursorFromUrl($response['next'])));

        // Return response
        return ['data' => $events];
    }

    /**
     * @param string $metricId
     * @param int $count
     * @param string|null $cursor
     * @return array
     * @throws GuzzleException
     */
    public function getEventsForMetricLegacy(
        string $metricId,
        int $count = 100, // Max: 100
        ?string $cursor = null,
    ): array {
        $query = [
            "count" => $count,
        ];
        if ($cursor) {
            $query["since"] = $cursor;
        }
        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "v1/metric/".$metricId."/timeline",
            query: $query,
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $eventFields
     * @param array|null $metricFields
     * @param array|null $profileFields
     * @param array|null $filter
     * @param bool|null $includeMetrics
     * @param bool|null $includeProfiles
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getAllEvents(
        ?array $eventFields = null,
        ?array $metricFields = null,
        ?array $profileFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?bool $includeMetrics = true,
        ?bool $includeProfiles = true,
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $cursor = null;
        $events = [];

        do {
            $response = $this->getEvents(
                eventFields: $eventFields,
                metricFields: $metricFields,
                profileFields: $profileFields,
                filter: $filter,
                includeMetrics: $includeMetrics,
                includeProfiles: $includeProfiles,
                sort: $sort,
                sortField: $sortField,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $events = [...$events, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $events];
    }

    /**
     * @param array|null $profileFields
     * @param array|null $additionalFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @param int $limit
     * @return array
     * @throws GuzzleException
     */
    public function getProfiles(
        ?array $profileFields = null,
        ?array $additionalFields = null, // Options: 'subscriptions', 'predictive_analytics'
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
        int $limit = 100,
    ): array {
        $query =[];

        if ($profileFields) {
            $query["fields[profile]"] = implode(",", $profileFields);
        }
        if ($additionalFields) {
            $query["additional-fields[profile]"] = implode(",", $additionalFields);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($limit) {
            $query["page[size]"] = $limit > 0 && $limit <= 100 ? $limit : 100;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "profiles",
            query: $query,
            revision: "2024-06-15",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $profileFields
     * @param array|null $additionalFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getAllProfiles(
        ?array $profileFields = null,
        ?array $additionalFields = null, // Options: 'subscriptions', 'predictive_analytics'
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $cursor = null;
        $profiles = [];

        do {
            $response = $this->getProfiles(
                profileFields: $profileFields,
                additionalFields: $additionalFields,
                filter: $filter,
                sort: $sort,
                sortField: $sortField,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $profiles = [...$profiles, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $profiles];
    }

    /**
     * @param array|null $catalogItemsFields
     * @param array|null $variantFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param bool|null $includeVariants
     * @param string|null $sortField
     * @param string|null $cursor
     * @param int $limit
     * @return array
     * @throws GuzzleException
     */
    public function getCatalogItems(
        ?array $catalogItemsFields = null,
        ?array $variantFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?bool $includeVariants = true,
        ?string $sortField = null,
        ?string $cursor = null,
        int $limit = 100,
    ): array {
        $query =[];

        if ($catalogItemsFields) {
            $query["fields[catalog-item]"] = implode(",", $catalogItemsFields);
        }
        if ($variantFields) {
            $query["fields[catalog-variant]"] = implode(",", $variantFields);
        }
        $include = [];
        if ($includeVariants) {
            $include[] = "variants";
        }
        if (!empty($include)) {
            $query["include"] = implode(",", $include);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($limit) {
            $query["page[size]"] = $limit > 0 && $limit <= 100 ? $limit : 100;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "catalog-items",
            query: $query,
            revision: "2024-06-15",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $catalogItemsFields
     * @param array|null $variantFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param bool|null $includeVariants
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getAllCatalogItems(
        ?array $catalogItemsFields = null,
        ?array $variantFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?bool $includeVariants = true,
        ?string $sortField = null,
    ): array {
        $cursor = null;
        $items = [];

        do {
            $response = $this->getCatalogItems(
                catalogItemsFields: $catalogItemsFields,
                variantFields: $variantFields,
                filter: $filter,
                sort: $sort,
                includeVariants: $includeVariants,
                sortField: $sortField,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $items = [...$items, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $items];
    }

    /**
     * @param array|null $catalogVariantsFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @param int $limit
     * @return array
     * @throws GuzzleException
     */
    public function getCatalogVariants(
        ?array $catalogVariantsFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
        int $limit = 100,
    ): array {
        $query =[];

        if ($catalogVariantsFields) {
            $query["fields[catalog-variant]"] = implode(",", $catalogVariantsFields);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($limit) {
            $query["page[size]"] = $limit > 0 && $limit <= 100 ? $limit : 100;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "catalog-variants",
            query: $query,
            revision: "2024-06-15",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $catalogVariantsFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getAllCatalogVariants(
        ?array $catalogVariantsFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $cursor = null;
        $variants = [];

        do {
            $response = $this->getCatalogVariants(
                catalogVariantsFields: $catalogVariantsFields,
                filter: $filter,
                sort: $sort,
                sortField: $sortField,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $variants = [...$variants, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $variants];
    }

    /**
     * @param array|null $catalogCategoriesFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @param string|null $cursor
     * @param int $limit
     * @return array
     * @throws GuzzleException
     */
    public function getCatalogCategories(
        ?array $catalogCategoriesFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
        ?string $cursor = null,
        int $limit = 100,
    ): array {
        $query =[];

        if ($catalogCategoriesFields) {
            $query["fields[catalog-category]"] = implode(",", $catalogCategoriesFields);
        }
        if ($sortField) {
            $query["sort"] = ($sort->value == "descending" ? "-" : "") . $sortField;
        }
        if ($cursor) {
            $query["page[cursor]"] = $cursor;
        }
        if ($limit) {
            $query["page[size]"] = $limit > 0 && $limit <= 100 ? $limit : 100;
        }
        if ($filter) {
            $query["filter"] = implode(',', $this->getTranslatedFilters($filter));
        }

        // Request the spreadsheet data
        $response = $this->performRequest(
            method: "GET",
            endpoint: "catalog-categories",
            query: $query,
            revision: "2024-06-15",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array|null $catalogCategoriesFields
     * @param array|null $filter
     * @param Sort|null $sort
     * @param string|null $sortField
     * @return array
     * @throws GuzzleException
     */
    public function getAllCatalogCategories(
        ?array $catalogCategoriesFields = null,
        ?array $filter = null, // Format [["operator" => ["equals",...], "field" => "name", "value" => "String"], ...]
        ?Sort $sort = Sort::ascending,
        ?string $sortField = null,
    ): array {
        $cursor = null;
        $categories = [];

        do {
            $response = $this->getCatalogCategories(
                catalogCategoriesFields: $catalogCategoriesFields,
                filter: $filter,
                sort: $sort,
                sortField: $sortField,
                cursor: $cursor,
            );
            if (!empty($response['data'])) {
                $categories = [...$categories, ...$response['data']];
            }
        } while (isset($response['links']['next']) && $response['links']['next'] && ($response['links']['next'] != "null") && ($cursor = self::getCursorFromUrl($response['links']['next'])));

        return ['data' => $categories];
    }

    /**
     * @param string $url
     * @return string|null
     */
    public static function getCursorFromUrl(string $url): string|null
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return null;
        }
        parse_str($query, $params);
        return $params['page']['cursor'] ?? null;
    }

    /**
     * @param array $filter
     * @return array
     */
    protected function getTranslatedFilters(array $filter): array
    {
        $list = [];
        foreach ($filter as $value) {
            $closer = "";
            if (is_array($value['operator'])) {
                $operator = implode("(", $value['operator']);
                $closer = str_repeat(")", count($value['operator']) - 1);
            } else {
                $operator = $value['operator'];
            }
            $list[] = $operator . "(" . $value['field'] . "," . $value['value'] . ")" . $closer;
        }
        return $list;
    }
}
