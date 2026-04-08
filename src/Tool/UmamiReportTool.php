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
 * Custom report management — CRUD reports and list website-specific reports.
 *
 * Actions: list, get, create, update, delete, website_reports
 */
final class UmamiReportTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_report';
    }

    public function description(): string
    {
        return 'Manage custom reports in Umami Analytics. Create, list, get, update, and delete reports. List reports for a specific website.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The report management operation',
                ['list', 'get', 'create', 'update', 'delete', 'website_reports'],
                required: true,
            ),
            new StringParameter('report_id', 'Report UUID (required for get, update, delete)'),
            new StringParameter('website_id', 'Website UUID (required for create and website_reports)'),
            new StringParameter('name', 'Report name (required for create, optional for update)'),
            new EnumParameter('type', 'Report type', ['insights', 'funnel', 'retention']),
            new StringParameter('description', 'Report description'),
            new StringParameter('report_parameters', 'JSON string of report configuration parameters — defines fields, filters, and groups'),
            new NumberParameter('page', 'Page number for pagination (for list)', required: false, integer: true, minimum: 1),
            new NumberParameter('page_size', 'Results per page (for list)', required: false, integer: true, minimum: 1, maximum: 200),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listReports($input),
                'get' => $this->getReport($input),
                'create' => $this->createReport($input),
                'update' => $this->updateReport($input),
                'delete' => $this->deleteReport($input),
                'website_reports' => $this->websiteReports($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: list, get, create, update, delete, website_reports"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_report({$action}) failed: {$e->getMessage()}");
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
    private function listReports(array $input): ToolResult
    {
        $query = [];

        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get('/reports', $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getReport(array $input): ToolResult
    {
        $reportId = $input['report_id'] ?? '';

        if ($reportId === '') {
            return ToolResult::error('report_id is required for the "get" action.');
        }

        $data = $this->client->get("/reports/{$reportId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createReport(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';
        $name = $input['name'] ?? '';
        $type = $input['type'] ?? 'insights';

        if ($websiteId === '' || $name === '') {
            return ToolResult::error('website_id and name are required for the "create" action.');
        }

        $body = [
            'websiteId' => $websiteId,
            'name' => $name,
            'type' => $type,
        ];

        if (isset($input['description']) && $input['description'] !== '') {
            $body['description'] = $input['description'];
        }

        if (isset($input['report_parameters']) && $input['report_parameters'] !== '') {
            $params = json_decode($input['report_parameters'], true, 512, JSON_THROW_ON_ERROR);

            if (is_array($params)) {
                $body['parameters'] = $params;
            }
        }

        $data = $this->client->post('/reports', $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateReport(array $input): ToolResult
    {
        $reportId = $input['report_id'] ?? '';

        if ($reportId === '') {
            return ToolResult::error('report_id is required for the "update" action.');
        }

        $body = [];

        if (isset($input['website_id']) && $input['website_id'] !== '') {
            $body['websiteId'] = $input['website_id'];
        }
        if (isset($input['name']) && $input['name'] !== '') {
            $body['name'] = $input['name'];
        }
        if (isset($input['type']) && $input['type'] !== '') {
            $body['type'] = $input['type'];
        }
        if (isset($input['description'])) {
            $body['description'] = $input['description'];
        }
        if (isset($input['report_parameters']) && $input['report_parameters'] !== '') {
            $body['parameters'] = $input['report_parameters'];
        }

        if ($body === []) {
            return ToolResult::error('At least one field must be provided for update.');
        }

        $data = $this->client->put("/reports/{$reportId}", $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function deleteReport(array $input): ToolResult
    {
        $reportId = $input['report_id'] ?? '';

        if ($reportId === '') {
            return ToolResult::error('report_id is required for the "delete" action.');
        }

        $data = $this->client->delete("/reports/{$reportId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function websiteReports(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required for the "website_reports" action.');
        }

        $data = $this->client->get("/websites/{$websiteId}/reports");

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
