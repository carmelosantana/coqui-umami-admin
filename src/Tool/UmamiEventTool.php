<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiClient;

/**
 * Event tracking and queries — list events, event metrics, data fields/values/stats, send/batch events.
 *
 * Actions: list, metrics, data_events, data_fields, data_values, data_stats, send, batch
 */
final class UmamiEventTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_event';
    }

    public function description(): string
    {
        return 'Track and query custom events in Umami Analytics. List events, get event metrics over time, query event data fields/values/stats, and send individual or batch events.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The event operation to perform',
                ['list', 'metrics', 'data_events', 'data_fields', 'data_values', 'data_stats', 'send', 'batch'],
                required: true,
            ),
            new StringParameter('website_id', 'Website UUID (required for all query actions and for send)'),
            new StringParameter('start_date', 'Start date — ISO 8601 string or Unix ms timestamp'),
            new StringParameter('end_date', 'End date — ISO 8601 string or Unix ms timestamp'),
            new StringParameter('event_name', 'Filter by event name (for list, data_events, data_values)'),
            new StringParameter('property_name', 'Event property name (required for data_values)'),
            new StringParameter('query', 'Search filter for event names (for list)'),
            new EnumParameter(
                'unit',
                'Time aggregation unit for event metrics',
                ['hour', 'day', 'week', 'month'],
            ),
            new StringParameter('timezone', 'Timezone for aggregation (e.g. "UTC")'),
            new StringParameter('country', 'Filter by country code (for metrics)'),
            new StringParameter('event_data', 'JSON string with event payload for send/batch actions. For send: {"hostname": "...", "url": "...", "website": "...", "name": "...", "data": {...}}. For batch: array of event payloads.'),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listEvents($input),
                'metrics' => $this->eventMetrics($input),
                'data_events' => $this->dataEvents($input),
                'data_fields' => $this->dataFields($input),
                'data_values' => $this->dataValues($input),
                'data_stats' => $this->dataStats($input),
                'send' => $this->sendEvent($input),
                'batch' => $this->batchEvents($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: list, metrics, data_events, data_fields, data_values, data_stats, send, batch"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_event({$action}) failed: {$e->getMessage()}");
        }
    }

    public function toFunctionSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters() as $param) {
            $properties[$param->name] = $param->toSchema();

            if ($param->required) {
                $required[] = $param->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => empty($properties) ? new \stdClass() : $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $schema,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function listEvents(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ];

        if (isset($input['query']) && $input['query'] !== '') {
            $query['query'] = $input['query'];
        }

        $data = $this->client->get("/websites/{$websiteId}/events", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function eventMetrics(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ];

        if (isset($input['unit']) && $input['unit'] !== '') {
            $query['unit'] = $input['unit'];
        }
        if (isset($input['timezone']) && $input['timezone'] !== '') {
            $query['timezone'] = $input['timezone'];
        }
        if (isset($input['country']) && $input['country'] !== '') {
            $query['country'] = $input['country'];
        }

        $data = $this->client->get("/websites/{$websiteId}/event-metrics", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dataEvents(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $eventName = $input['event_name'] ?? '';

        if ($websiteId === '' || $eventName === '') {
            return ToolResult::error('website_id and event_name are required for data_events.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/event-data/events", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
            'event' => $eventName,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dataFields(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/event-data/fields", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dataValues(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $eventName = $input['event_name'] ?? '';
        $propertyName = $input['property_name'] ?? '';

        if ($websiteId === '' || $eventName === '' || $propertyName === '') {
            return ToolResult::error('website_id, event_name, and property_name are required for data_values.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/event-data/values", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
            'eventName' => $eventName,
            'propertyName' => $propertyName,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dataStats(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/event-data/stats", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function sendEvent(array $input): ToolResult
    {
        $eventDataRaw = $input['event_data'] ?? '';

        if ($eventDataRaw === '') {
            return ToolResult::error('event_data JSON string is required for the "send" action.');
        }

        $payload = json_decode($eventDataRaw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            return ToolResult::error('event_data must be a valid JSON object.');
        }

        $data = $this->client->post('/send', [
            'type' => 'event',
            'payload' => $payload,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function batchEvents(array $input): ToolResult
    {
        $eventDataRaw = $input['event_data'] ?? '';

        if ($eventDataRaw === '') {
            return ToolResult::error('event_data JSON string is required for the "batch" action.');
        }

        $events = json_decode($eventDataRaw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($events)) {
            return ToolResult::error('event_data must be a valid JSON array of event payloads.');
        }

        $data = $this->client->post('/batch', $events);

        return ToolResult::success($this->encode($data));
    }

    /**
     * Check required date parameters.
     *
     * @param array<string, mixed> $input
     */
    private function requireDates(array $input): ?ToolResult
    {
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';

        if ($startDate === '' || $endDate === '') {
            return ToolResult::error('start_date and end_date are required for this action.');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
