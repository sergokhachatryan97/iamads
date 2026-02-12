# Automated Telegram 2FA Setup with Gmail API

## Overview

Fully automated Telegram 2FA enablement system that uses Gmail API and email aliasing to handle confirmation codes without manual intervention.

## Architecture

### Flow

1. **Enable2faJob** is dispatched when `ensure2FA()` is called
2. Job generates unique password using `AccountPasswordGenerator`
3. Creates Gmail alias: `baseemail+acc_{account_id}@gmail.com`
4. Calls `update2fa()` or `updatePassword()` with recovery email
5. Stores encrypted password and alias in `mtproto_2fa_states` table
6. Dispatches **Confirm2faEmailJob** after 30 seconds

7. **Confirm2faEmailJob** polls Gmail inbox via Gmail API
8. Searches for emails to the alias address
9. Extracts 5-6 digit confirmation code using regex
10. Calls `confirmPasswordEmail()` with the code
11. Marks state as `confirmed` on success

### Database Schema

**Table:** `mtproto_2fa_states`
- `id` (primary key)
- `account_id` (unique, foreign key to `mtproto_telegram_accounts`)
- `email_alias` (string, nullable) - Gmail alias email
- `encrypted_password` (text) - Laravel encrypted password
- `status` (enum: pending|waiting_email|confirmed|failed)
- `last_error` (text, nullable)
- `created_at`/`updated_at`

### Configuration

**`config/telegram_mtproto.php`** - Added:

```php
'setup' => [
    '2fa' => [
        'enable' => env('TELEGRAM_MTPROTO_SETUP_2FA_ENABLE', false),
        'base_email' => env('TELEGRAM_MTPROTO_SETUP_2FA_BASE_EMAIL', null),
        'hint' => env('TELEGRAM_MTPROTO_SETUP_2FA_HINT', null),
        'gmail_credentials_path' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_CREDENTIALS_PATH', storage_path('app/gmail-credentials.json')),
        'gmail_poll_timeout_seconds' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_TIMEOUT', 300),
        'gmail_poll_interval_seconds' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_INTERVAL', 10),
    ],
],
```

### Jobs

#### Enable2faJob
- **Queue:** `tg-2fa-enable`
- **Timeout:** 180 seconds
- **Responsibilities:**
  - Check if 2FA already enabled (idempotent)
  - Generate unique password
  - Generate Gmail alias
  - Call MadelineProto `update2fa()` or `updatePassword()`
  - Store encrypted password and alias
  - Dispatch `Confirm2faEmailJob` after 30s delay

#### Confirm2faEmailJob
- **Queue:** `tg-2fa-confirm`
- **Timeout:** 180 seconds
- **Responsibilities:**
  - Poll Gmail inbox using Gmail API
  - Search for emails to alias address (last 24 hours)
  - Extract confirmation code via regex
  - Call `confirmPasswordEmail()` with code
  - Retry with backoff if code not found
  - Mark as failed after 3 attempts

### Services

#### GmailService
- **Purpose:** Interact with Gmail API to retrieve confirmation codes
- **Methods:**
  - `findConfirmationCode($aliasEmail, $maxAgeHours)` - Searches Gmail and extracts code
  - `extractCodeFromMessage($message)` - Parses message body for 5-6 digit codes
  - `getMessageBody($message)` - Extracts plain text from Gmail message

### Email Aliasing

Uses Gmail plus addressing:
- Base email: `myemail@gmail.com`
- Alias format: `myemail+acc_{account_id}@gmail.com`
- Example: `myemail+acc_123@gmail.com`

All emails to the alias are delivered to the base inbox, allowing the system to filter by recipient.

### Security Features

1. **Encrypted Passwords:** Uses Laravel `Crypt::encryptString()` for password storage
2. **No Plaintext Logging:** Passwords and codes never logged
3. **Per-Account Locks:** Uses `tg:mtproto:lock:{account_id}` to prevent concurrent operations
4. **Idempotent:** Safe to rerun - checks state before acting
5. **Error Handling:** Respects FLOOD_WAIT, handles timeouts gracefully

### Integration

**Updated:** `app/Services/Telegram/AccountSetupTaskExecutor.php`

- `ensure2FA()` method now:
  - Checks if 2FA already confirmed (quick DB check)
  - Validates configuration
  - Dispatches `Enable2faJob` asynchronously
  - Returns immediately with `job_dispatched` status

**No breaking changes:** Existing code continues to work, but 2FA is now fully automated.

### Queues

- `tg-2fa-enable` - 2FA enablement jobs
- `tg-2fa-confirm` - Email confirmation polling jobs

### Setup Requirements

1. **Gmail API Credentials:**
   - Create Google Cloud project
   - Enable Gmail API
   - Create Service Account or OAuth credentials
   - Save credentials JSON to `storage/app/gmail-credentials.json`
   - Grant `gmail.readonly` scope

2. **Environment Variables:**
   ```bash
   TELEGRAM_MTPROTO_SETUP_2FA_ENABLE=true
   TELEGRAM_MTPROTO_SETUP_2FA_BASE_EMAIL=myemail@gmail.com
   TELEGRAM_MTPROTO_SETUP_2FA_HINT=Optional hint
   TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_CREDENTIALS_PATH=/path/to/credentials.json
   TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_TIMEOUT=300
   TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_INTERVAL=10
   ```

3. **Dependencies:**
   ```bash
   composer require google/apiclient
   ```

### Usage

The system works automatically when `ensure2FA()` is called:

1. Task runner calls `ensure2FA()`
2. Job is dispatched to queue
3. 2FA is enabled with Gmail alias
4. Confirmation code is automatically retrieved and confirmed
5. Task marked as done

### Error Handling

- **FLOOD_WAIT:** Jobs release and retry after cooldown period
- **Email Not Found:** Job retries up to 3 times with increasing delays
- **Invalid Code:** Job retries after 60 seconds
- **Timeout:** After 3 failed attempts, state marked as `failed`

### Logging

- `2FA enablement job dispatched` - Job queued
- `2FA enabled, waiting for email confirmation` - 2FA enabled, polling started
- `2FA email confirmed successfully` - Confirmation complete
- `2FA confirmation failed after max attempts` - Final failure

**Never logs:**
- Passwords
- Confirmation codes
- Full email addresses (only hashes)

### Notes

- **Gmail API:** Requires `google/apiclient` package
- **Email Delivery:** Gmail aliases may take a few seconds to receive emails
- **Polling:** Default 10-second interval, 300-second timeout
- **MadelineProto API:** Supports both `update2fa()` and `updatePassword()` methods
- **Idempotency:** Safe to rerun - checks state before acting
