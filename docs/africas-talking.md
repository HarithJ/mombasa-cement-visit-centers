# Africa’s Talking API client

The standalone PHP client is available for future integration. No application workflow calls it; installing, loading, or configuring it sends nothing. No sending endpoint, command, worker, queue, or schedule is included.

## Configuration

Set these in the server environment when configuring a future caller:

| Variable | Purpose |
| --- | --- |
| `AFRICAS_TALKING_USERNAME` | Application username; must be `sandbox` for sandbox mode |
| `AFRICAS_TALKING_API_KEY` | Private API key for the selected account/environment |
| `AFRICAS_TALKING_ENVIRONMENT` | Required explicit `sandbox` or `live`; live requires a non-sandbox username |
| `AFRICAS_TALKING_SENDER_ID` | Optional sender registered with the account; omitted from requests when empty |

Use the host’s secret/environment configuration. Never put keys in tracked files, public assets, or static exports. The application does not automatically load `.env` files. No real credentials are needed for tests. PHP cURL is required for the default HTTP transport; mocked tests do not use it.

## Interface

`AfricasTalkingClient::fromEnvironment()` creates a client without making requests. The constructor also accepts explicit configuration. Configuration is checked when `send` is invoked.

For future callers, `send(string $recipient, string $message): string` accepts one international-format number (leading `+` and 7–15 digits) and a nonempty message. It returns the provider message ID only after recipient-level acceptance. This is not proof of handset delivery. It throws sanitized `RuntimeException` errors for invalid configuration/input, HTTP failures, recipient rejection, transport failure, or malformed responses. There are no automatic retries; uncertain outcomes must not be blindly retried.

The optional transport closure accepts URL, header list, and form-encoded body, and returns an array with integer `status` and string `body`. This is the test boundary. Without it, the client performs a single HTTPS POST with connection/request timeouts of 5/15 seconds and no redirects.

Do not invoke sending against either live or sandbox APIs as part of this work. Connecting the client to any workflow or sending messages requires a separate request.

## Verification

Run `npm run test:sms` or `php tests/sms.test.php`. Every send in these tests uses fake transport; no SMS API requests are made. The suite covers configuration, form encoding, response parsing, rejection, malformed responses, transport errors, and input validation. It also runs with `npm test`.

Contract references: [official PHP SDK configuration](https://github.com/AfricasTalkingLtd/africastalking-php/blob/master/src/AfricasTalking.php), [SMS request implementation](https://github.com/AfricasTalkingLtd/africastalking-php/blob/master/src/SMS.php), and [recipient response and delivery stages](https://help.africastalking.com/en/articles/742491-why-did-my-messages-fail).
