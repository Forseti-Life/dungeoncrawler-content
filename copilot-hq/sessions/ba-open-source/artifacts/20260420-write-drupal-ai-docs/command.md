# Task: Write drupal-ai-conversation Public Documentation

## Owner
ba-open-source

## Objective
Create comprehensive public-facing documentation for the drupal-ai-conversation module for its first release. Write README, QUICKSTART, and CONTRIBUTING guide.

## Acceptance Criteria
- [ ] README.md complete (features, requirements, config)
- [ ] QUICKSTART.md complete (local install, first run)
- [ ] CONTRIBUTING.md written
- [ ] Code examples provided
- [ ] All docs reviewed against public-repo-positioning.md
- [ ] Ready for repo creation and CI validation

## README.md Structure
**File:** README.md

```markdown
# Drupal AI Conversation Module

Autonomous conversational AI integration for Drupal 10/11 using AWS Bedrock.

## Features
- AWS Bedrock integration (Claude models)
- Configurable system prompts
- Multi-site support
- RESTful API endpoints
- Conversation history tracking
- Built-in safety guardrails

## Requirements
- Drupal 10.1+ or 11+
- PHP 8.1+
- Composer
- AWS Bedrock access (with credentials)

## Installation

### 1. Install via Composer
\`\`\`bash
composer require forseti-life/drupal-ai-conversation
\`\`\`

### 2. Configure AWS Credentials
Create `.env.local`:
\`\`\`
AWS_BEDROCK_REGION=YOUR_AWS_REGION
AWS_BEDROCK_MODEL_ID=anthropic.claude-3-sonnet-20240229-v1:0
AWS_ACCESS_KEY_ID=YOUR_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_SECRET_KEY
\`\`\`

### 3. Enable Module
\`\`\`bash
drush en ai_conversation
drush cr
\`\`\`

### 4. Configure Prompts
Settings: `/admin/config/ai/conversation`

## Configuration

### System Prompt
Define the AI behavior. Example:
\`\`\`yaml
ai_conversation.settings:
  system_prompt: |
    You are a helpful assistant for this website.
    Respond concisely and professionally.
\`\`\`

### Fallback Prompt
Used if system prompt fails to load:
\`\`\`yaml
ai_conversation.settings:
  fallback_prompt: |
    You are a helpful assistant.
\`\`\`

## Usage

### Via REST API
\`\`\`bash
curl -X POST /api/ai/conversation/ask \\
  -H "Content-Type: application/json" \\
  -d '{
    "message": "Hello, what can you do?",
    "conversation_id": "conv-123"
  }'
\`\`\`

### Via Drupal Block
Add block: "AI Conversation Widget"
- Appears on frontend pages
- Handles authentication automatically
- Customizable styling

## Safety & Privacy
- Conversations logged locally (optional, configurable)
- AWS Bedrock privacy policy applies to API calls
- No data sent to third parties
- Review AWS data retention policies

## Troubleshooting

**Module won't enable:**
- Check AWS credentials in `.env.local`
- Verify AWS Bedrock API access
- Review logs: `drush watchdog`

**API calls failing:**
- Confirm model ID in config
- Check AWS region matches
- Verify IAM permissions

## Contributing
See CONTRIBUTING.md

## License
Apache 2.0

## Support
- GitHub Issues: https://github.com/Forseti-Life/drupal-ai-conversation/issues
- Documentation: https://forseti.life/docs
```

## QUICKSTART.md Structure
**File:** QUICKSTART.md

```markdown
# Quick Start: drupal-ai-conversation

Get the AI Conversation module running in 10 minutes.

## Prerequisites
- Ubuntu 22.04 or 24.04
- Docker + Docker Compose (or DDEV)
- Git

## Option 1: DDEV Setup (Recommended)

\`\`\`bash
# Clone the repo
git clone https://github.com/Forseti-Life/drupal-ai-conversation.git
cd drupal-ai-conversation

# Start DDEV environment
ddev start

# Install dependencies
ddev composer install

# Copy environment template
cp .env.example .env

# Edit .env with your AWS credentials
ddev exec nano .env

# Enable module
ddev drush en ai_conversation
ddev drush cr

# Access Drupal
ddev launch
# Admin: admin / password
```

## Option 2: Manual Setup

\`\`\`bash
# Install Drupal 11
composer create-project drupal/project-drupal-recommended my-drupal --no-interaction

# Add module
cd my-drupal
composer require forseti-life/drupal-ai-conversation

# Configure AWS
cp .env.example .env
# Edit .env with AWS credentials

# Enable
php vendor/bin/drush en ai_conversation
php vendor/bin/drush cr

# Serve
php -S localhost:8000 -t web/
```

## First Run

1. **Configure System Prompt**
   - Go to: Administration > Configuration > AI > Conversation
   - Set your system prompt (or use default)
   - Save

2. **Test REST API**
   \`\`\`bash
   curl -X POST http://localhost:8000/api/ai/conversation/ask \\
     -H "Content-Type: application/json" \\
     -H "Authorization: Bearer YOUR_TOKEN" \\
     -d '{"message": "Hello, how are you?"}'
   \`\`\`

3. **Add Block to Homepage**
   - Go to: Structure > Block Layout
   - Add block: "AI Conversation Widget"
   - Configure region
   - Save

## Testing

\`\`\`bash
# Run unit tests
ddev composer test

# Run code standards
ddev composer phpcs

# Install test
ddev drush en ai_conversation
\`\`\`

## Troubleshooting

**AWS Credentials Not Working?**
1. Verify `.env` file exists and is readable
2. Check AWS IAM permissions for Bedrock
3. Confirm region is correct
4. Review logs: `ddev drush watchdog`

**Module Enable Fails?**
- Check Drupal 10+ requirement
- Verify PHP 8.1+
- Run `ddev composer update`

## Next Steps
- Read [Configuration Guide](README.md#configuration)
- Explore [API Documentation](docs/api.md)
- Join [GitHub Discussions](https://github.com/Forseti-Life/drupal-ai-conversation/discussions)
```

## CONTRIBUTING.md
**File:** CONTRIBUTING.md

```markdown
# Contributing to drupal-ai-conversation

Thanks for contributing! Please follow these guidelines.

## Code Style
- Follow Drupal coding standards (phpcs)
- Use 2-space indentation
- Document public functions with PHPDoc

## Pull Request Process
1. Fork the repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Make changes
4. Run tests: `composer test`
5. Submit PR with description

## Reporting Issues
- Check existing issues first
- Provide reproduction steps
- Include PHP version and Drupal version

## License
By contributing, you agree to license your work under Apache 2.0.
```

## Instructions
1. Create these files in the new repo structure:
   - `README.md` (use template above)
   - `QUICKSTART.md` (use template above)
   - `CONTRIBUTING.md` (use template above)

2. Review against: `runbooks/public-repo-positioning.md`

3. Validate:
   - [ ] No hardcoded paths
   - [ ] All examples use placeholders for secrets
   - [ ] Links are to public resources only
   - [ ] Code examples actually work

4. Route to:
   - dev-open-source: "Docs ready, proceed with extraction"
   - qa-open-source: "Docs ready for QA review"
   - pm-open-source: "Phase 2a docs complete"

## Context
- Parallel with dev extraction work
- Must be ready before repo push
- Public documentation is first impression
- Timeline: Target 2026-05-01 publish
- ROI: 65 (enables repo publication)

## Related
- Your packaging plan: sessions/ba-open-source/outbox/20260414-proj-009-first-candidate-packaging.md
- Public positioning: runbooks/public-repo-positioning.md
- Feature spec: features/forseti-open-source-initiative/feature.md
- Agent: ba-open-source
- Status: pending
