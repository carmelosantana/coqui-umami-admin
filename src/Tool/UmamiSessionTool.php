<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiClient;

/**
 * Session analytics — list sessions, get details, activity timelines, session data, and property queries.
 *
 * Actions: list, get, activity, data, properties, property_values
 */
final class UmamiSessionTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_session';
    }

    public function description(): string
    {
        return 'Query website sessions in Umami Analytics. List sessions, get session details (device, OS, browser, location), view activity timelines, access session custom data, and analyze session data properties and their values.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The session operation to perform',
                ['list', 'get', 'activity', 'data', 'properties', 'property_values'],
                required: true,
            ),
            new StringParameter('website_id', 'Website UUID (required for all actions)', required: true),
            new StringParameter('session_id', 'Session UUID (required for get, activity, data)'),
            new StringParameter('start_date', 'Start date — ISO 8601 string or Unix ms timestamp'),
            new StringParameter('end_date', 'End date — ISO 8601 string or Unix ms timestamp'),
            new StringParameter('property_name', 'Session property name (required for property_values)'),
            new StringParameter('event_name', 'Filter property values by event name (optional for property_values)'),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listSessions($input),
                'get' => $this->getSession($input),
                'activity' => $this->getActivity($input),
                'data' => $this->getData($input),
                'properties' => $this->getProperties($input),
                'property_values' => $this->getPropertyValues($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: list, get, activity, data, properties, property_values"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_session({$action}) failed: {$e->getMessage()}");
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
    private function listSessions(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getSession(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $sessionId = $input['session_id'] ?? '';

        if ($sessionId === '') {
            return ToolResult::error('session_id is required for the "get" action.');
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions/{$sessionId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getActivity(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $sessionId = $input['session_id'] ?? '';

        if ($sessionId === '') {
            return ToolResult::error('session_id is required for the "activity" action.');
        }

        $query = [];

        if (isset($input['start_date']) && $input['start_date'] !== '') {
            $query['startAt'] = UmamiClient::toTimestamp($input['start_date']);
        }
        if (isset($input['end_date']) && $input['end_date'] !== '') {
            $query['endAt'] = UmamiClient::toTimestamp($input['end_date']);
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions/{$sessionId}/activity", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getData(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $sessionId = $input['session_id'] ?? '';

        if ($sessionId === '') {
            return ToolResult::error('session_id is required for the "data" action.');
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions/{$sessionId}/data");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getProperties(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions/data/properties", [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getPropertyValues(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $propertyName = $input['property_name'] ?? '';

        if ($propertyName === '') {
            return ToolResult::error('property_name is required for the "property_values" action.');
        }

        $dateResult = $this->requireDates($input);

        if ($dateResult !== null) {
            return $dateResult;
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($input['start_date']),
            'endAt' => UmamiClient::toTimestamp($input['end_date']),
            'propertyName' => $propertyName,
        ];

        if (isset($input['event_name']) && $input['event_name'] !== '') {
            $query['eventName'] = $input['event_name'];
        }

        $data = $this->client->get("/websites/{$websiteId}/sessions/data/values", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
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
