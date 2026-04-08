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
 * Team management — CRUD teams, manage members and team websites, join via access code.
 *
 * Actions: list, get, create, update, delete, join, list_members, add_member,
 *          update_member, remove_member, list_websites, add_website, remove_website
 */
final class UmamiTeamTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_team';
    }

    public function description(): string
    {
        return 'Admin: manage Umami teams. Create, list, update, delete teams. Manage team members (add, update role, remove). Manage team websites (add, remove). Join a team via access code.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The team management operation',
                ['list', 'get', 'create', 'update', 'delete', 'join', 'list_members', 'add_member', 'update_member', 'remove_member', 'list_websites', 'add_website', 'remove_website'],
                required: true,
            ),
            new StringParameter('team_id', 'Team UUID (required for most actions except list, create, join)'),
            new StringParameter('name', 'Team name (required for create, optional for update)'),
            new StringParameter('access_code', 'Team access code (for join, optional for update)'),
            new StringParameter('user_id', 'User UUID (for add_member, update_member, remove_member)'),
            new EnumParameter('role', 'Member role in team', ['member', 'admin']),
            new StringParameter('website_id', 'Website UUID (for add_website, remove_website)'),
            new StringParameter('domain', 'Website domain (for add_website)'),
            new StringParameter('website_name', 'Website display name (for add_website)'),
            new StringParameter('search', 'Search term to filter teams (for list)'),
            new NumberParameter('page', 'Page number for pagination', required: false, integer: true, minimum: 1),
            new NumberParameter('page_size', 'Results per page', required: false, integer: true, minimum: 1, maximum: 200),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'list' => $this->listTeams($input),
                'get' => $this->getTeam($input),
                'create' => $this->createTeam($input),
                'update' => $this->updateTeam($input),
                'delete' => $this->deleteTeam($input),
                'join' => $this->joinTeam($input),
                'list_members' => $this->listMembers($input),
                'add_member' => $this->addMember($input),
                'update_member' => $this->updateMember($input),
                'remove_member' => $this->removeMember($input),
                'list_websites' => $this->listWebsites($input),
                'add_website' => $this->addWebsite($input),
                'remove_website' => $this->removeWebsite($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: list, get, create, update, delete, join, list_members, add_member, update_member, remove_member, list_websites, add_website, remove_website"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_team({$action}) failed: {$e->getMessage()}");
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
    private function listTeams(array $input): ToolResult
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

        $data = $this->client->get('/teams', $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getTeam(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $data = $this->client->get("/teams/{$teamId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createTeam(array $input): ToolResult
    {
        $name = $input['name'] ?? '';

        if ($name === '') {
            return ToolResult::error('name is required for the "create" action.');
        }

        $body = ['name' => $name];

        if (isset($input['access_code']) && $input['access_code'] !== '') {
            $body['accessCode'] = $input['access_code'];
        }

        $data = $this->client->post('/teams', $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateTeam(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $body = [];

        if (isset($input['name']) && $input['name'] !== '') {
            $body['name'] = $input['name'];
        }
        if (isset($input['access_code'])) {
            $body['accessCode'] = $input['access_code'];
        }

        if ($body === []) {
            return ToolResult::error('At least one field (name, access_code) must be provided for update.');
        }

        $data = $this->client->put("/teams/{$teamId}", $body);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function deleteTeam(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $data = $this->client->delete("/teams/{$teamId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function joinTeam(array $input): ToolResult
    {
        $accessCode = $input['access_code'] ?? '';

        if ($accessCode === '') {
            return ToolResult::error('access_code is required for the "join" action.');
        }

        $data = $this->client->post('/teams/join', [
            'accessCode' => $accessCode,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function listMembers(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $query = [];

        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get("/teams/{$teamId}/users", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function addMember(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $userId = $input['user_id'] ?? '';
        $role = $input['role'] ?? 'member';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "add_member" action.');
        }

        $data = $this->client->post("/teams/{$teamId}/users", [
            'userId' => $userId,
            'role' => $role,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateMember(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $userId = $input['user_id'] ?? '';
        $role = $input['role'] ?? '';

        if ($userId === '' || $role === '') {
            return ToolResult::error('user_id and role are required for the "update_member" action.');
        }

        $data = $this->client->put("/teams/{$teamId}/users/{$userId}", [
            'role' => $role,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function removeMember(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $userId = $input['user_id'] ?? '';

        if ($userId === '') {
            return ToolResult::error('user_id is required for the "remove_member" action.');
        }

        $data = $this->client->delete("/teams/{$teamId}/users/{$userId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function listWebsites(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $query = [];

        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get("/teams/{$teamId}/websites", $query);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function addWebsite(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $websiteName = $input['website_name'] ?? $input['name'] ?? '';
        $domain = $input['domain'] ?? '';

        if ($websiteName === '' || $domain === '') {
            return ToolResult::error('website_name (or name) and domain are required for the "add_website" action.');
        }

        $data = $this->client->post("/teams/{$teamId}/websites", [
            'name' => $websiteName,
            'domain' => $domain,
        ]);

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function removeWebsite(array $input): ToolResult
    {
        $teamId = $this->requireTeamId($input);

        if ($teamId === null) {
            return ToolResult::error('team_id is required.');
        }

        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required for the "remove_website" action.');
        }

        $data = $this->client->delete("/teams/{$teamId}/websites/{$websiteId}");

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requireTeamId(array $input): ?string
    {
        $teamId = $input['team_id'] ?? '';

        return $teamId !== '' ? $teamId : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
