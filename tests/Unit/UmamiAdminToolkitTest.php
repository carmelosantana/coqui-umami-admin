<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiAnalyticsTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiEventTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiMeTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiReportTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiSessionTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiTeamTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiTrackingCodeTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiUserTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiWebsiteTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiAdminToolkit;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// ----------------------------------------------------------------
// Toolkit registration
// ----------------------------------------------------------------

test('implements ToolkitInterface', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

test('tools returns exactly 9 tools', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    expect($toolkit->tools())->toHaveCount(9);
});

test('all tools implement ToolInterface', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    foreach ($toolkit->tools() as $tool) {
        expect($tool)->toBeInstanceOf(ToolInterface::class);
    }
});

test('tool names are unique', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toHaveCount(count(array_unique($names)));
});

test('tool names follow expected naming', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toContain('umami_website')
        ->toContain('umami_analytics')
        ->toContain('umami_event')
        ->toContain('umami_session')
        ->toContain('umami_user')
        ->toContain('umami_team')
        ->toContain('umami_report')
        ->toContain('umami_tracking_code')
        ->toContain('umami_me');
});

test('guidelines contains all tool names', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toBeString()
        ->toContain('umami_website')
        ->toContain('umami_analytics')
        ->toContain('umami_event')
        ->toContain('umami_session')
        ->toContain('umami_user')
        ->toContain('umami_team')
        ->toContain('umami_report')
        ->toContain('umami_tracking_code')
        ->toContain('umami_me');
});

// ----------------------------------------------------------------
// fromEnv factory
// ----------------------------------------------------------------

test('fromEnv reads environment variables', function () {
    $origUrl = getenv('UMAMI_API_URL');
    $origKey = getenv('UMAMI_API_KEY');

    putenv('UMAMI_API_URL=https://umami.test.com');
    putenv('UMAMI_API_KEY=test-env-key-123');

    $toolkit = UmamiAdminToolkit::fromEnv();

    expect($toolkit)->toBeInstanceOf(UmamiAdminToolkit::class)
        ->and($toolkit->tools())->toHaveCount(9);

    // Restore
    $origUrl !== false ? putenv("UMAMI_API_URL={$origUrl}") : putenv('UMAMI_API_URL');
    $origKey !== false ? putenv("UMAMI_API_KEY={$origKey}") : putenv('UMAMI_API_KEY');
});

// ----------------------------------------------------------------
// UmamiClient
// ----------------------------------------------------------------

test('UmamiClient::toTimestamp converts ISO 8601 dates to milliseconds', function () {
    // Known date: 2024-01-01T00:00:00Z = 1704067200 seconds = 1704067200000 ms
    $ts = UmamiClient::toTimestamp('2024-01-01T00:00:00Z');

    expect($ts)->toBe(1704067200000);
});

test('UmamiClient::toTimestamp passes through numeric timestamps', function () {
    $ts = UmamiClient::toTimestamp('1704067200000');

    expect($ts)->toBe(1704067200000);
});

test('UmamiClient::resolvedBaseUrl returns configured URL', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test');

    expect($client->resolvedBaseUrl())->toBe('https://umami.example.com');
});

// ----------------------------------------------------------------
// Function schema validation
// ----------------------------------------------------------------

test('all tools produce valid function schemas', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key');
    $toolkit = new UmamiAdminToolkit($client);

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema)->toHaveKey('type')
            ->and($schema['type'])->toBe('function')
            ->and($schema)->toHaveKey('function')
            ->and($schema['function'])->toHaveKey('name')
            ->and($schema['function'])->toHaveKey('description')
            ->and($schema['function'])->toHaveKey('parameters')
            ->and($schema['function']['parameters'])->toHaveKey('type')
            ->and($schema['function']['parameters']['type'])->toBe('object')
            ->and($schema['function']['parameters'])->toHaveKey('properties');
    }
});

test('umami_website schema includes action enum', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test');
    $tool = new UmamiWebsiteTool($client);
    $schema = $tool->toFunctionSchema();

    $actionProps = $schema['function']['parameters']['properties']['action'];

    expect($actionProps['enum'])->toContain('list')
        ->toContain('get')
        ->toContain('create')
        ->toContain('update')
        ->toContain('delete')
        ->toContain('reset');
});

test('umami_analytics schema includes metric_type enum', function () {
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test');
    $tool = new UmamiAnalyticsTool($client);
    $schema = $tool->toFunctionSchema();

    $metricTypeProps = $schema['function']['parameters']['properties']['metric_type'];

    expect($metricTypeProps['enum'])->toContain('url')
        ->toContain('referrer')
        ->toContain('browser')
        ->toContain('country');
});

// ----------------------------------------------------------------
// Parameter validation (execute with missing required params)
// ----------------------------------------------------------------

test('umami_website create requires name and domain', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiWebsiteTool($client);
    $result = $tool->execute(['action' => 'create']);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('name')
        ->and($result->content)->toContain('domain');
});

test('umami_website get requires website_id', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiWebsiteTool($client);
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('website_id');
});

test('umami_analytics stats requires date range', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiAnalyticsTool($client);
    $result = $tool->execute([
        'action' => 'stats',
        'website_id' => 'test-id',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('start_date')
        ->and($result->content)->toContain('end_date');
});

test('umami_analytics metrics requires metric_type', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiAnalyticsTool($client);
    $result = $tool->execute([
        'action' => 'metrics',
        'website_id' => 'test-id',
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('metric_type');
});

test('umami_user create requires username and password', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiUserTool($client);
    $result = $tool->execute(['action' => 'create']);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('username')
        ->and($result->content)->toContain('password');
});

test('umami_team join requires access_code', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiTeamTool($client);
    $result = $tool->execute(['action' => 'join']);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('access_code');
});

test('umami_me change_password requires both passwords', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiMeTool($client);
    $result = $tool->execute(['action' => 'change_password']);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('current_password')
        ->and($result->content)->toContain('new_password');
});

test('umami_tracking_code requires website_id', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $tool = new UmamiTrackingCodeTool($client);
    $result = $tool->execute([]);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('website_id');
});

// ----------------------------------------------------------------
// Mock HTTP integration tests
// ----------------------------------------------------------------

test('umami_website list returns parsed response', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'data' => [
                ['id' => 'abc-123', 'name' => 'Test Site', 'domain' => 'example.com'],
            ],
            'count' => 1,
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiWebsiteTool($client);
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('Test Site')
        ->and($result->content)->toContain('example.com');
});

test('umami_website create sends correct payload', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'id' => 'new-uuid-456',
            'name' => 'New Site',
            'domain' => 'newsite.com',
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiWebsiteTool($client);
    $result = $tool->execute([
        'action' => 'create',
        'name' => 'New Site',
        'domain' => 'newsite.com',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('new-uuid-456')
        ->and($result->content)->toContain('New Site');
});

test('umami_analytics stats returns parsed response', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'pageviews' => ['value' => 1500, 'prev' => 1200],
            'visitors' => ['value' => 800, 'prev' => 650],
            'visits' => ['value' => 1100, 'prev' => 900],
            'bounces' => ['value' => 300, 'prev' => 250],
            'totaltime' => ['value' => 45000, 'prev' => 38000],
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiAnalyticsTool($client);
    $result = $tool->execute([
        'action' => 'stats',
        'website_id' => 'abc-123',
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('1500')
        ->and($result->content)->toContain('800');
});

test('umami_analytics active_visitors returns count', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'x' => 42,
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiAnalyticsTool($client);
    $result = $tool->execute([
        'action' => 'active_visitors',
        'website_id' => 'abc-123',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('42');
});

test('umami_tracking_code generates html snippet', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'id' => 'abc-123',
            'name' => 'Test Site',
            'domain' => 'example.com',
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiTrackingCodeTool($client);
    $result = $tool->execute([
        'website_id' => 'abc-123',
        'format' => 'html',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('data-website-id')
        ->and($result->content)->toContain('abc-123')
        ->and($result->content)->toContain('script.js')
        ->and($result->content)->toContain('umami.example.com');
});

test('umami_tracking_code generates gtm snippet', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'id' => 'abc-123',
            'name' => 'Test Site',
            'domain' => 'example.com',
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiTrackingCodeTool($client);
    $result = $tool->execute([
        'website_id' => 'abc-123',
        'format' => 'gtm',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('createElement')
        ->and($result->content)->toContain('data-website-id');
});

test('umami_me profile returns user data', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'id' => 'user-uuid',
            'username' => 'admin',
            'role' => 'admin',
            'createdAt' => '2024-01-01T00:00:00Z',
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiMeTool($client);
    $result = $tool->execute(['action' => 'profile']);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('admin')
        ->and($result->content)->toContain('user-uuid');
});

test('umami_user list returns users', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'data' => [
                ['id' => 'u1', 'username' => 'alice', 'role' => 'admin'],
                ['id' => 'u2', 'username' => 'bob', 'role' => 'user'],
            ],
            'count' => 2,
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiUserTool($client);
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('alice')
        ->and($result->content)->toContain('bob');
});

test('umami_report list returns reports', function () {
    $httpClient = new MockHttpClient([
        new MockResponse(json_encode([
            'data' => [
                ['id' => 'r1', 'name' => 'Monthly Report', 'type' => 'insights'],
            ],
            'count' => 1,
        ])),
    ]);

    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test-key', httpClient: $httpClient);
    $tool = new UmamiReportTool($client);
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($result->content)->toContain('Monthly Report')
        ->and($result->content)->toContain('insights');
});

// ----------------------------------------------------------------
// Unknown action handling
// ----------------------------------------------------------------

test('tools return error for unknown actions', function () {
    $httpClient = new MockHttpClient([]);
    $client = new UmamiClient(apiUrl: 'https://umami.example.com', apiKey: 'test', httpClient: $httpClient);

    $toolClasses = [
        UmamiWebsiteTool::class,
        UmamiAnalyticsTool::class,
        UmamiEventTool::class,
        UmamiSessionTool::class,
        UmamiUserTool::class,
        UmamiTeamTool::class,
        UmamiReportTool::class,
        UmamiMeTool::class,
    ];

    foreach ($toolClasses as $class) {
        $tool = new $class($client);
        $result = $tool->execute(['action' => 'nonexistent_action']);

        expect($result->status)->toBe(ToolResultStatus::Error)
            ->and($result->content)->toContain('Unknown action');
    }
});

// ----------------------------------------------------------------
// JWT fallback auth
// ----------------------------------------------------------------

test('UmamiClient falls back to JWT when no API key', function () {
    $httpClient = new MockHttpClient([
        // First call: login
        new MockResponse(json_encode(['token' => 'jwt-token-abc'])),
        // Second call: actual API request (GET /me)
        new MockResponse(json_encode(['id' => 'user-1', 'username' => 'admin'])),
    ]);

    $client = new UmamiClient(
        apiUrl: 'https://umami.example.com',
        username: 'admin',
        password: 'pass123',
        httpClient: $httpClient,
    );

    $result = $client->get('/me');

    expect($result)->toHaveKey('id')
        ->and($result['id'])->toBe('user-1');
});
