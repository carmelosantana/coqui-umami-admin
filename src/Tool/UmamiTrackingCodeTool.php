<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitUmamiAdmin\UmamiClient;

/**
 * Generate tracking code snippets for Umami-tracked websites.
 *
 * Outputs the HTML <script> tag or GTM-compatible JavaScript snippet
 * with the correct data-website-id and script source URL.
 */
final class UmamiTrackingCodeTool implements ToolInterface
{
    public function __construct(
        private readonly UmamiClient $client,
    ) {}

    public function name(): string
    {
        return 'umami_tracking_code';
    }

    public function description(): string
    {
        return 'Generate the Umami tracking code snippet for a website. Returns the HTML script tag or Google Tag Manager compatible snippet with the correct website ID and script URL.';
    }

    public function parameters(): array
    {
        return [
            new StringParameter('website_id', 'Website UUID to generate tracking code for', required: true),
            new StringParameter('script_url', 'Custom script URL (defaults to UMAMI_API_URL + /script.js)'),
            new EnumParameter(
                'format',
                'Output format for the tracking code',
                ['html', 'gtm'],
            ),
        ];
    }

    public function execute(array $input): ToolResult
    {
        $websiteId = $input['website_id'] ?? '';

        if ($websiteId === '') {
            return ToolResult::error('website_id is required.');
        }

        try {
            // Fetch website details to confirm it exists and show domain info
            $website = $this->client->get("/websites/{$websiteId}");

            $baseUrl = $this->resolveScriptUrl($input);

            if ($baseUrl === '') {
                return ToolResult::error('Cannot determine script URL. Provide script_url or set UMAMI_API_URL.');
            }

            $format = $input['format'] ?? 'html';
            $websiteName = $website['name'] ?? $website['domain'] ?? 'Unknown';
            $websiteDomain = $website['domain'] ?? '';

            $snippet = match ($format) {
                'gtm' => $this->generateGtmSnippet($baseUrl, $websiteId),
                default => $this->generateHtmlSnippet($baseUrl, $websiteId),
            };

            $output = [
                'website' => $websiteName,
                'domain' => $websiteDomain,
                'website_id' => $websiteId,
                'format' => $format,
                'tracking_code' => $snippet,
                'instructions' => $format === 'gtm'
                    ? 'Add this JavaScript snippet as a Custom HTML tag in Google Tag Manager.'
                    : 'Add this script tag to the <head> section of your website HTML.',
            ];

            return ToolResult::success(json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            return ToolResult::error("umami_tracking_code failed: {$e->getMessage()}");
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
    private function resolveScriptUrl(array $input): string
    {
        if (isset($input['script_url']) && $input['script_url'] !== '') {
            return $input['script_url'];
        }

        $baseUrl = $this->client->resolvedBaseUrl();

        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/') . '/script.js';
    }

    private function generateHtmlSnippet(string $scriptUrl, string $websiteId): string
    {
        return sprintf(
            '<script defer src="%s" data-website-id="%s"></script>',
            $scriptUrl,
            $websiteId,
        );
    }

    private function generateGtmSnippet(string $scriptUrl, string $websiteId): string
    {
        return sprintf(
            "<script>\n(function () {\n  var el = document.createElement('script');\n  el.setAttribute('src', '%s');\n  el.setAttribute('data-website-id', '%s');\n  document.body.appendChild(el);\n})();\n</script>",
            $scriptUrl,
            $websiteId,
        );
    }
}
