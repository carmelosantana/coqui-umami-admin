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
 * User management (admin) — CRUD operations on Umami users, usage stats, and associated resources.
 *
 * Actions: list, get, create, update, delete, usage, websites, teams
 */
final class UmamiUserTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_user';
    }

    public function description(): string
    {
        return 'Admin: manage Umami users. Create, list, get, update, and delete users. View user usage statistics, associated websites, and team memberships. Requires admin privileges.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The user management operation',
                ['list', 'get', 'create', 'update', 'delete', 'usage', 'websites', 'teams'],
                required: true,
            ),
            new StringParameter('user_id', 'User UUID (required for get, update, delete, usage, websites, teams)'),
            new StringParameter('username', 'Username (required for create, optional for update)'),
            new StringParameter('password', 'Password (required for create, optional for update)'),
            new EnumParameter('role', 'User role', ['user', 'admin']),
            new StringParameter('start_date', 'Start date for usage stats — ISO 8601 or Unix ms'),
            new StringParameter('end_date', 'End date for usage stats — ISO 8601 or Unix ms'),
            new StringParameter('search', 'Search term to filter users by username (for list)'),
            new NumberParameter('page', 'Page number for pagination', required: false, integer: true, minimum: 1),
            new NumberParameter('page_size', 'Results per page', required: false, integer: true, minimum: 1, maximum: 200),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listUsers($input),
                'get' => $this->getUser($input),
                'create' => $this->createUser($input),
                'update' => $this->updateUser($input),
                'delete' => $this->deleteUser($input),
                'usage' => $this->getUserUsage($input),
                'websites' => $this->getUserWebsites($input),
                'teams' => $this->getUserTeams($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: list, get, create, update, delete, usage, websites, teams"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_user({$action}) failed: {$e->getMessage()}");
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
    private function listUsers(array $input): ToolResult
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

        $data = $this->client->get('/users', $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getUser(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "get" action.');
        }

        $data = $this->client->get("/users/{$userId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createUser(array $input): ToolResult
    {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'user';

        if ($username === '' || $password === '') {
            return ToolResult::error('username and password are required for the "create" action.');
        }

        $data = $this->client->post('/users', [
            'username' => $username,
            'password' => $password,
            'role' => $role,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateUser(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "update" action.');
        }

        $body = [];

        if (isset($input['username']) && $input['username'] !== '') {
            $body['username'] = $input['username'];
        }
        if (isset($input['password']) && $input['password'] !== '') {
            $body['password'] = $input['password'];
        }
        if (isset($input['role']) && $input['role'] !== '') {
            $body['role'] = $input['role'];
        }

        if ($body === []) {
            return ToolResult::error('At least one field (username, password, role) must be provided for update.');
        }

        $data = $this->client->put("/users/{$userId}", $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function deleteUser(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "delete" action.');
        }

        $data = $this->client->delete("/users/{$userId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getUserUsage(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "usage" action.');
        }

        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';

        if ($startDate === '' || $endDate === '') {
            return ToolResult::error('start_date and end_date are required for the "usage" action.');
        }

        $data = $this->client->get("/users/{$userId}/usage", [
            'startAt' => UmamiClient::toTimestamp($startDate),
            'endAt' => UmamiClient::toTimestamp($endDate),
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getUserWebsites(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "websites" action.');
        }

        $query = [];

        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get("/users/{$userId}/websites", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getUserTeams(array $input): ToolResult
    {
        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "teams" action.');
        }

        $data = $this->client->get("/users/{$userId}/teams");

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
