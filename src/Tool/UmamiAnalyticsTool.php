<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiClient;

/**
 * Core analytics queries — stats, pageviews, metrics by dimension, active visitors, realtime.
 *
 * Actions: stats, pageviews, metrics, active_visitors, realtime
 */
final class UmamiAnalyticsTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_analytics';
    }

    public function description(): string
    {
        return 'Query website analytics from Umami. Get traffic stats (pageviews, visitors, bounce rate), pageview time series, metrics by dimension (URLs, referrers, browsers, countries, etc.), active visitor count, and realtime data.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The analytics query to run',
                ['stats', 'pageviews', 'metrics', 'active_visitors', 'realtime'],
                required: true,
            ),
            new StringParameter('website_id', 'Website UUID (required for all actions)', required: true),
            new StringParameter('start_date', 'Start date — ISO 8601 string (e.g. "2024-01-01") or Unix ms timestamp. Required for stats, pageviews, metrics.'),
            new StringParameter('end_date', 'End date — ISO 8601 string or Unix ms timestamp. Required for stats, pageviews, metrics.'),
            new EnumParameter(
                'unit',
                'Time unit for pageview aggregation',
                ['hour', 'day', 'week', 'month'],
            ),
            new EnumParameter(
                'metric_type',
                'Dimension for metrics breakdown',
                ['url', 'referrer', 'browser', 'os', 'device', 'country', 'region', 'city', 'language', 'screen', 'event'],
            ),
            new StringParameter('timezone', 'Timezone for aggregation (e.g. "America/New_York")'),
            new StringParameter('url', 'Filter by specific URL path'),
            new StringParameter('country', 'Filter by country code (e.g. "US")'),
            new NumberParameter('limit', 'Maximum number of metric results to return', required: false, integer: true, minimum: 1, maximum: 500),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'stats' => $this->getStats($input),
                'pageviews' => $this->getPageviews($input),
                'metrics' => $this->getMetrics($input),
                'active_visitors' => $this->getActive($input),
                'realtime' => $this->getRealtime($input),
                default => ToolResult::error("Unknown action: {$action}. Valid actions: stats, pageviews, metrics, active_visitors, realtime"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_analytics({$action}) failed: {$e->getMessage()}");
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
    private function getStats(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';

        if ($startDate === '' || $endDate === '') {
            return ToolResult::error('start_date and end_date are required for the "stats" action.');
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($startDate),
            'endAt' => UmamiClient::toTimestamp($endDate),
        ];

        if (isset($input['url']) && $input['url'] !== '') {
            $query['url'] = $input['url'];
        }
        if (isset($input['country']) && $input['country'] !== '') {
            $query['country'] = $input['country'];
        }

        $data = $this->client->get("/websites/{$websiteId}/stats", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getPageviews(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';
        $unit = $input['unit'] ?? 'day';

        if ($startDate === '' || $endDate === '') {
            return ToolResult::error('start_date and end_date are required for the "pageviews" action.');
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($startDate),
            'endAt' => UmamiClient::toTimestamp($endDate),
            'unit' => $unit,
        ];

        if (isset($input['timezone']) && $input['timezone'] !== '') {
            $query['timezone'] = $input['timezone'];
        }

        $data = $this->client->get("/websites/{$websiteId}/pageviews", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getMetrics(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';
        $metricType = $input['metric_type'] ?? '';

        if ($startDate === '' || $endDate === '') {
            return ToolResult::error('start_date and end_date are required for the "metrics" action.');
        }

        if ($metricType === '') {
            return ToolResult::error('metric_type is required for the "metrics" action (e.g. "url", "referrer", "browser", "country").');
        }

        $query = [
            'startAt' => UmamiClient::toTimestamp($startDate),
            'endAt' => UmamiClient::toTimestamp($endDate),
            'type' => $metricType,
        ];

        if (isset($input['url']) && $input['url'] !== '') {
            $query['url'] = $input['url'];
        }
        if (isset($input['limit'])) {
            $query['limit'] = (int) $input['limit'];
        }

        $data = $this->client->get("/websites/{$websiteId}/metrics", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getActive(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        $data = $this->client->get("/websites/{$websiteId}/active");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getRealtime(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        // Default to last 5 minutes
        $startAt = isset($input['start_date']) && $input['start_date'] !== ''
            ? UmamiClient::toTimestamp($input['start_date'])
            : (int) (microtime(true) * 1000) - (5 * 60 * 1000);

        $data = $this->client->get("/websites/{$websiteId}/realtime", [
            'startAt' => $startAt,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
