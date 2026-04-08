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
 * Current user operations — profile, own websites/teams, password change.
 *
 * Actions: profile, websites, teams, change_password
 */
final class UmamiMeTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_me';
    }

    public function description(): string
    {
        return 'Get information about the currently authenticated Umami user. View profile, list own websites and teams, or change password.';
    }

    public function parameters(): array
    {
        return [
            new EnumParameter(
                'action',
                'The operation to perform',
                ['profile', 'websites', 'teams', 'change_password'],
                required: true,
            ),
            new StringParameter('current_password', 'Current password (required for change_password)'),
            new StringParameter('new_password', 'New password (required for change_password)'),
            new NumberParameter('page', 'Page number for pagination (for websites)', required: false, integer: true, minimum: 1),
            new NumberParameter('page_size', 'Results per page (for websites)', required: false, integer: true, minimum: 1, maximum: 200),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $action = $input['action'] ?? '';

        try {
            return match ($action) {
                'profile' => $this->getProfile(),
                'websites' => $this->getWebsites($input),
                'teams' => $this->getTeams(),
                'change_password' => $this->changePassword($input),
                default => ToolResult::error("Unknown action: {$action}. Valid: profile, websites, teams, change_password"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error("umami_me({$action}) failed: {$e->getMessage()}");
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

    private function getProfile(): ToolResult
    {
        $data = $this->client->get('/me');

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function getWebsites(array $input): ToolResult
    {
        $query = ['includeTeams' => 'true'];

        if (isset($input['page'])) {
            $query['page'] = (int) $input['page'];
        }
        if (isset($input['page_size'])) {
            $query['pageSize'] = (int) $input['page_size'];
        }

        $data = $this->client->get('/me/websites', $query);

        return ToolResult::success($this->encode($data));
    }

    private function getTeams(): ToolResult
    {
        $data = $this->client->get('/me/teams');

        return ToolResult::success($this->encode($data));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function changePassword(array $input): ToolResult
    {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '') {
            return ToolResult::error('current_password and new_password are required for the "change_password" action.');
        }

        $data = $this->client->put('/me/password', [
            'currentPassword' => $currentPassword,
            'newPassword' => $newPassword,
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
