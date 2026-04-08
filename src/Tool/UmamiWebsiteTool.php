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
 * Website management tool — CRUD operations on tracked websites.
 *
 * Actions: list, get, create, update, delete, reset
 */
final class UmamiWebsiteTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_website';
    }

    public function description(): string
    {
        return 'Manage websites tracked by Umami Analytics. Create, list, get details, update, delete, or reset analytics data for websites.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The operation to perform',
                ['list', 'get', 'create', 'update', 'delete', 'reset'],
                required: true,
            ),
            new StringParameter('website_id', 'Website UUID (required for get, update, delete, reset)'),
            new StringParameter('name', 'Website display name (required for create, optional for update)'),
            new StringParameter('domain', 'Website domain (required for create, optional for update)'),
            new StringParameter('share_id', 'Share ID for public access (optional, for update)'),
            new StringParameter('search', 'Search term to filter websites (for list)'),
            new NumberParameter('page', 'Page number for pagination (for list)', required: false, integer: true, minimum: 1),
            new NumberParameter('page_size', 'Results per page (for list)', required: false, integer: true, minimum: 1, maximum: 200),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listWebsites($input),
                'get' => $this->getWebsite($input),
                'create' => $this->createWebsite($input),
                'update' => $this->updateWebsite($input),
                'delete' => $this->deleteWebsite($input),
                'reset' => $this->resetWebsite($input),
                default => ToolResult::error("Unknown action: {$action}. Valid actions: list, get, create, update, delete, reset"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_website({$action}) failed: {$e->getMessage()}");
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
    private function listWebsites(array $input): ToolResult
    {
        $query = [];

        if (isset($input['search']) && $input['search'] !== '') {
            $query['search'] = $input['search'];
        }
        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get('/websites', $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getWebsite(array $input): ToolResult
    {
        $id = $input['website_id'] ?? '';

        if ($id === '') {
            return ToolResult::error('website_id is required for the "get" action.');
        }

        $data = $this->client->get("/websites/{$id}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createWebsite(array $input): ToolResult
    {
        $name = $input['name'] ?? '';
        $domain = $input['domain'] ?? '';

        if ($name === '' || $domain === '') {
            return ToolResult::error('Both "name" and "domain" are required for the "create" action.');
        }

        $data = $this->client->post('/websites', [
            'name' => $name,
            'domain' => $domain,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateWebsite(array $input): ToolResult
    {
        $id = $input['website_id'] ?? '';

        if ($id === '') {
            return ToolResult::error('website_id is required for the "update" action.');
        }

        $body = [];

        if (isset($input['name']) && $input['name'] !== '') {
            $body['name'] = $input['name'];
        }
        if (isset($input['domain']) && $input['domain'] !== '') {
            $body['domain'] = $input['domain'];
        }
        if (isset($input['share_id'])) {
            $body['shareId'] = $input['share_id'];
        }

        if ($body === []) {
            return ToolResult::error('At least one field (name, domain, share_id) must be provided for update.');
        }

        $data = $this->client->put("/websites/{$id}", $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function deleteWebsite(array $input): ToolResult
    {
        $id = $input['website_id'] ?? '';

        if ($id === '') {
            return ToolResult::error('website_id is required for the "delete" action.');
        }

        $data = $this->client->delete("/websites/{$id}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resetWebsite(array $input): ToolResult
    {
        $id = $input['website_id'] ?? '';

        if ($id === '') {
            return ToolResult::error('website_id is required for the "reset" action.');
        }

        $data = $this->client->post("/websites/{$id}/reset");

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
