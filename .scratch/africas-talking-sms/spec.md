# Africa’s Talking API integration

Status: ready-for-agent

## Problem Statement

The PHP application needs an Africa’s Talking SMS API integration available for future use.

## Solution

Add a reusable server-side API client and configuration. Do not connect it to application workflows or send any SMS to anyone.

## User Stories

1. As a developer, I want an Africa’s Talking client, so that future features can use the SMS API.
2. As an operator, I want server-side configuration, so that credentials stay private.
3. As a maintainer, I want mocked verification, so that the integration can be tested without sending messages.

## Implementation Decisions

- Add a PHP client supporting authentication, SMS request construction, response parsing, and clear error handling.
- Configure username, API key, optional sender ID, and sandbox/live environment through server-side environment variables. Document setup without committing secrets.
- Keep the client unconnected to booking, email, feedback, and other application flows. Loading or configuring it must not initiate requests.
- Do not add sending endpoints, commands, queues, workers, schedules, message templates, or database changes.

## Testing Decisions

Test the client through a fake HTTP transport, following the existing PHP testing conventions. Cover request formatting, success responses, provider errors, and missing configuration. Make no real API sending requests, including sandbox sends.

## Out of Scope

Sending SMS to anyone, enabling automatic notifications, choosing recipients or message content, and deployment.

## Further Notes

This spec covers integration capability only. Any future use to send messages requires a separate request.
