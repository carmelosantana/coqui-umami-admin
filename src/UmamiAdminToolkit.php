<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitUmamiAdmin;

use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiAnalyticsTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiEventTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiMeTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiReportTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiSessionTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiTeamTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiTrackingCodeTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiUserTool;
use CarmeloSantana\CoquiToolkitUmamiAdmin\Tool\UmamiWebsiteTool;

/**
 * Umami Analytics admin toolkit — full website management, analytics,
 * user/team admin, reports, event tracking, session analysis, and tracking code generation.
 *
 * Auto-discovered by Coqui's ToolkitDiscovery when installed via Composer.
 * Requires UMAMI_API_URL and either UMAMI_API_KEY or UMAMI_USERNAME + UMAMI_PASSWORD.
 */
final class UmamiAdminToolkit implements ToolkitInterface
{
    private readonly UmamiClient $client;

    public function __construct(
        ?UmamiClient $client = null,
    ) {
        $this->client = $client ?? UmamiClient::fromEnv();
    }

    public static function fromEnv(): self
    {
        return new self(UmamiClient::fromEnv());
    }

    public function tools(): array
    {
        return [
            new UmamiWebsiteTool($this->client),
            new UmamiAnalyticsTool($this->client),
            new UmamiEventTool($this->client),
            new UmamiSessionTool($this->client),
            new UmamiUserTool($this->client),
            new UmamiTeamTool($this->client),
            new UmamiReportTool($this->client),
            new UmamiTrackingCodeTool($this->client),
            new UmamiMeTool($this->client),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <UMAMI-ADMIN-GUIDELINES>
        ## Umami Analytics Admin Toolkit

        You have full access to a self-hosted Umami Analytics instance via 9 tools:

        ### Tool Overview
        - **umami_website** — Create, list, get, update, delete, and reset websites (sites being tracked)
        - **umami_analytics** — Query website statistics: pageviews, visitors, bounce rate, metrics by dimension (url, referrer, browser, country, etc.), active visitors, and realtime data
        - **umami_event** — Query custom events, event metrics over time, event data fields/values/stats, and send/batch events
        - **umami_session** — List sessions, get session details, view session activity timelines, session data, and session property analytics
        - **umami_user** — Admin: create, list, update, delete users; view user usage stats, websites, and teams
        - **umami_team** — Admin: create, list, update, delete teams; manage team members and team websites; join teams
        - **umami_report** — Create, list, get, update, delete custom reports; list website-specific reports
        - **umami_tracking_code** — Generate the HTML tracking script tag or GTM snippet for any tracked website
        - **umami_me** — Get current user profile, list own websites/teams, change password

        ### Date Parameters
        - All date parameters accept ISO 8601 strings (e.g. "2024-01-01", "2024-01-15T10:30:00Z") or Unix timestamps in milliseconds
        - When the user says "last 7 days", "this month", etc., calculate the appropriate start_date and end_date
        - Default to the last 30 days if no date range is specified

        ### Common Workflows
        1. **Add a new site**: `umami_website(action: "create", name: "My Site", domain: "example.com")` → then `umami_tracking_code(website_id: "<id>")` to get the embed code
        2. **Traffic report**: `umami_analytics(action: "stats", website_id: "<id>", start_date: "2024-01-01", end_date: "2024-01-31")` for overview, then `umami_analytics(action: "metrics", ...)` for breakdowns
        3. **User management**: `umami_user(action: "list")` to see all users, `umami_user(action: "create", ...)` to add new ones
        4. **Team setup**: `umami_team(action: "create", name: "Marketing")` → `umami_team(action: "add_member", team_id: "<id>", user_id: "<uid>", role: "member")`

        ### Important Notes
        - User management and team management require admin privileges on the Umami instance
        - Website reset and delete are destructive — confirm with the user before proceeding
        - The tracking code tool generates the standard Umami `<script>` tag — it does NOT install it on the user's website
        - When presenting analytics data, format numbers nicely (e.g. commas for thousands, percentages for bounce rate)
        </UMAMI-ADMIN-GUIDELINES>
        GUIDELINES;
    }
}
