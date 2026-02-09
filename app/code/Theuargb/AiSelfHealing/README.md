# Magento 2 AI Self-Healing Module

## Overview

The `Theuargb_AiSelfHealing` module is an AI-powered self-healing mechanism for Magento 2 that automatically detects, analyzes, and attempts to fix errors in real-time.

## Features

- **Automatic Error Interception**: Catches exceptions at the HTTP request level
- **Context Collection**: Gathers comprehensive error context including stack traces, request data, and system state
- **AI-Powered Analysis**: Communicates with a Python AI agent service to analyze and fix errors
- **Safety Controls**: Rate limiting, concurrent healing limits, and readonly mode
- **URL Filtering**: Configurable URL patterns for selective error interception
- **Response Rewriting**: Can return healed responses instead of error pages
- **Admin Interface**: View healing attempts and configure settings via Magento Admin
- **Monitoring Mode**: Log errors without attempting fixes for testing

## Architecture

```
Magento Request → Plugin Intercepts Error → Context Collector
                                                ↓
                                        Agent Client (HTTP)
                                                ↓
                                    Python AI Service (DSPy)
                                                ↓
                                    Healing Attempt Logged
                                                ↓
                                    Response Rewritten (optional)
```

## Installation

1. Copy the module to `app/code/Theuargb/AiSelfHealing`
2. Run Magento setup commands:
```bash
bin/magento module:enable Theuargb_AiSelfHealing
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

Navigate to **Stores → Configuration → Theuargb → AI Self-Healing**

### General Settings
- **Enable AI Self-Healing**: Turn the module on/off
- **Mode**: 
  - `monitor_only`: Log errors without healing
  - `auto_heal`: Attempt to fix errors automatically

### AI Agent Settings
- **Agent Endpoint URL**: URL of the Python AI agent service (e.g., `http://localhost:8100`)
- **Heal Timeout**: Maximum time to wait for healing response
- **Max Tool Calls**: Limit on AI agent tool invocations

### URL Filters
- **Intercept Strategy**: 
  - `all`: Intercept all URLs
  - `patterns`: Only intercept matching patterns
  - `none`: Disable interception
- **URL Patterns to Include**: Whitelist patterns
- **URL Patterns to Exclude**: Blacklist patterns (admin, API endpoints by default)

### Response Rewriting
- **Enable Response Rewriting**: Allow AI to return healed HTML
- **Allowed URL Patterns**: Restrict which URLs can have responses rewritten
- **HTTP Status Code**: Status code to return with healed responses

### Safety Settings
- **Max Attempts Per Fingerprint Per Hour**: Rate limit per unique error
- **Max Concurrent Healings**: Limit simultaneous healing attempts
- **Disallowed Tool Actions**: Restrict AI capabilities
- **Read-Only Mode**: Prevent AI from making changes

## Database Schema

The module creates one table:

- `aiselfhealing_attempt`: Stores all healing attempt records with error context, agent requests/responses, and outcomes

## Admin Interface

Access **AI Self-Healing → Healing Log** to view:
- All healing attempts
- Error messages and traces
- Agent responses
- Execution times
- Success/failure outcomes

## Python AI Agent Service

The module requires a separate Python service running the AI agent. The agent should expose these endpoints:

- `POST /heal`: Accept error context and return healing response
- `GET /health`: Health check endpoint
- `POST /snapshot`: Store homepage snapshots

## Security Considerations

- Never expose the AI agent service to the public internet
- Use strong authentication between Magento and the agent
- Review disallowed tool actions to restrict AI capabilities
- Enable readonly mode in production for testing
- Exclude admin URLs and API endpoints from healing
- Monitor healing attempt logs for suspicious activity

## Cron Jobs

- **Homepage Snapshot**: Captures periodic snapshots of the homepage for the AI agent's reference

Configure frequency in **Stores → Configuration → Theuargb → AI Self-Healing → Homepage Snapshot**

## Troubleshooting

### Healing Not Working
1. Check if module is enabled in configuration
2. Verify AI agent service is running and accessible
3. Check URL filters aren't excluding the error URL
4. Review rate limiting settings
5. Check Magento logs for errors

### Performance Impact
- Healing adds latency to error responses
- Use aggressive rate limiting in high-traffic sites
- Consider monitor_only mode for production
- Exclude high-frequency URLs from healing

## Development

### File Structure
```
app/code/Theuargb/AiSelfHealing/
├── Api/                      # API interfaces
├── Controller/               # Admin controllers
├── Cron/                     # Cron jobs
├── Helper/                   # Helper classes
├── Model/                    # Models and repositories
├── Plugin/                   # Plugins
├── etc/                      # Configuration
└── view/                     # Admin UI templates
```

### Key Classes
- `Plugin/AppHttpPlugin.php`: Main error interception
- `Helper/ContextCollector.php`: Error context gathering
- `Model/AgentClient.php`: AI agent communication
- `Helper/UrlFilter.php`: URL filtering logic
- `Helper/Config.php`: Configuration helper

## License

Open Software License (OSL 3.0) / Academic Free License (AFL 3.0)

## Author

Theuargb

## Version

1.0.0
