# AI Conversation Troubleshooting

## Common checks

### Provider configuration

1. Confirm the selected provider is configured in Drupal admin.
2. Confirm required credentials or network access are available.
3. Confirm the chosen model exists and is permitted.

### Bedrock

- Verify `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` if not using IAM roles.
- Verify `AWS_DEFAULT_REGION` or module-configured region.
- Verify the AWS principal can invoke the selected Bedrock model.

### Ollama

- Verify the configured base URL is reachable from the Drupal host.
- Verify the requested model is available on the Ollama instance.

## Useful commands

```bash
drush cr
drush config:get ai_conversation.settings
drush config:get ai_conversation.provider_settings
```

## Debugging symptoms

| Symptom | Likely cause |
|---|---|
| Chat request fails immediately | provider credentials, endpoint, or access issue |
| Empty/weak responses | prompt or context configuration issue |
| Long threads slow down | summary thresholds may need tuning |
| Admin reports missing | module not enabled or permissions not assigned |

## Logging

The module writes to the `ai_conversation` logger channel. Review Drupal logs first when diagnosing provider or controller failures.

## Safe-documentation rule

Do not store live credentials, internal filesystem paths, or production-only values in public troubleshooting docs.
