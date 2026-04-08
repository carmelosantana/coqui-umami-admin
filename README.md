# Umami Admin Toolkit for Coqui

Full-featured Umami Analytics admin toolkit for [Coqui](https://github.com/carmelosantana/coqui). Provides complete API coverage for managing websites, querying analytics, tracking events, managing users and teams, generating reports, and producing tracking code snippets.

## Requirements

- PHP 8.4+
- [Coqui](https://github.com/carmelosantana/coqui) with `carmelosantana/php-agents ^0.7`
- A self-hosted [Umami Analytics](https://umami.is/) instance

## Installation

```bash
composer require coquibot/umami-admin-toolkit
```

Coqui auto-discovers the toolkit on boot. No code changes needed.

## Credentials

Set these in your workspace `.env` or via Coqui's `credentials` tool:

| Variable | Required | Description |
|----------|----------|-------------|
| `UMAMI_API_URL` | Yes | Base URL of your Umami instance (e.g. `https://umami.example.com`) |
| `UMAMI_API_KEY` | Yes* | API key for authentication |
| `UMAMI_USERNAME` | No | Username for JWT fallback auth |
| `UMAMI_PASSWORD` | No | Password for JWT fallback auth |

*Either `UMAMI_API_KEY` or both `UMAMI_USERNAME` + `UMAMI_PASSWORD` must be set. API key is preferred.

### Getting an API Key

1. Log in to your Umami instance
2. Go to **Settings** > **API Keys**
3. Click **Create API Key**
4. Copy the key and set it as `UMAMI_API_KEY`

## Tools

| Tool | Description | Actions |
|------|-------------|---------|
| `umami_website` | Manage tracked websites | `list`, `get`, `create`, `update`, `delete`, `reset` |
| `umami_analytics` | Query website analytics | `stats`, `pageviews`, `metrics`, `active_visitors`, `realtime` |
| `umami_event` | Track and query events | `list`, `metrics`, `data_events`, `data_fields`, `data_values`, `data_stats`, `send`, `batch` |
| `umami_session` | Analyze visitor sessions | `list`, `get`, `activity`, `data`, `properties`, `property_values` |
| `umami_user` | Admin: manage users | `list`, `get`, `create`, `update`, `delete`, `usage`, `websites`, `teams` |
| `umami_team` | Admin: manage teams | `list`, `get`, `create`, `update`, `delete`, `join`, `list_members`, `add_member`, `update_member`, `remove_member`, `list_websites`, `add_website`, `remove_website` |
| `umami_report` | Manage custom reports | `list`, `get`, `create`, `update`, `delete`, `website_reports` |
| `umami_tracking_code` | Generate tracking snippets | Generates HTML `<script>` or GTM snippet |
| `umami_me` | Current user operations | `profile`, `websites`, `teams`, `change_password` |

## Usage Examples

### Add a new site and get tracking code

```
> Add example.com to Umami and give me the tracking code

The agent will:
1. Call umami_website(action: "create", name: "example.com", domain: "example.com")
2. Call umami_tracking_code(website_id: "<id>", format: "html")
```

### Generate a traffic report

```
> Show me traffic stats for example.com for the last 30 days

The agent will:
1. Call umami_website(action: "list") to find the website ID
2. Call umami_analytics(action: "stats", website_id: "<id>", start_date: "...", end_date: "...")
3. Call umami_analytics(action: "metrics", website_id: "<id>", metric_type: "url", ...)
```

### Manage users

```
> Create a new user "editor" with the user role

The agent will call umami_user(action: "create", username: "editor", password: "...", role: "user")
```

### Set up a team

```
> Create a Marketing team and add user alice to it

The agent will:
1. Call umami_team(action: "create", name: "Marketing")
2. Call umami_user(action: "list") to find alice's user ID
3. Call umami_team(action: "add_member", team_id: "<id>", user_id: "<alice_id>", role: "member")
```

## Authentication

The toolkit supports two authentication methods:

1. **API Key** (recommended): Set `UMAMI_API_KEY`. Sent via `x-umami-api-key` header on every request.
2. **Username/Password** (fallback): Set `UMAMI_USERNAME` and `UMAMI_PASSWORD`. The client authenticates via `POST /api/auth/login` and caches the JWT token for subsequent requests.

API key auth is preferred because it doesn't require an initial login request and works with service accounts.

## Development

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/pest

# Static analysis
./vendor/bin/phpstan analyse
```

## License

MIT
