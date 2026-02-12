# Account Setup Pipeline Implementation

## Overview

Implemented an "Account Setup Pipeline" for 1000 action accounts (`is_probe = 0`) that runs setup tasks per account using the same lock/error handling patterns as the existing MTProto pool service.

## Implementation

### 1. Database Schema

**Migration:** `database/migrations/2026_01_26_064404_create_mtproto_account_tasks_table.php`

**Table:** `mtproto_account_tasks`
- `id` (primary key)
- `account_id` (foreign key to `mtproto_telegram_accounts`)
- `task_type` (string, indexed)
- `payload_json` (JSON, nullable)
- `status` (enum: pending|running|done|failed|retry, indexed)
- `attempts` (int, default 0)
- `retry_at` (datetime, nullable, indexed)
- `last_error_code` (string, nullable)
- `last_error` (text, nullable)
- `created_at`/`updated_at`
- Unique constraint: `(account_id, task_type)` - prevents duplicates

### 2. Model

**`app/Models/MtprotoAccountTask.php`**

- Status constants: `STATUS_PENDING`, `STATUS_RUNNING`, `STATUS_DONE`, `STATUS_FAILED`, `STATUS_RETRY`
- Task type constants: `TASK_ENSURE_2FA`, `TASK_UPDATE_NAME`, `TASK_PRIVACY_PHONE_HIDDEN`, `TASK_PRIVACY_LAST_SEEN_EVERYBODY`, `TASK_SET_PHOTO_JPG`, `TASK_SET_PHOTO_GIF`, `TASK_STORY_IMAGE`, `TASK_STORY_VIDEO`
- Helper methods: `getRequiredTaskTypes()`, `getMediaTaskTypes()`, `isFinal()`, `isEligibleToRun()`
- Relationship: `account()` belongsTo `MtprotoTelegramAccount`

**`app/Models/MtprotoTelegramAccount.php`**
- Added `setupTasks()` hasMany relationship
- Added `disable()` method (if missing)

### 3. Jobs

#### `DispatchAccountSetupTasksJob`
- Selects accounts: `is_active=1`, `disabled_at is null`, `is_probe=0`
- For each account, upserts required tasks (unique constraint prevents duplicates)
- Dispatches `RunAccountSetupTaskJob` for eligible tasks
- Routes to correct queue based on task type (`tg-setup-fast` vs `tg-setup-media`)

#### `RunAccountSetupTaskJob`
- Takes `task_id`
- Loads task + account
- Checks eligibility (not final, not running, retry_at <= now)
- Acquires per-account lock: `tg:mtproto:lock:{account_id}` (same as pool service)
- Creates Madeline instance via `makeForRuntime()`
- Executes task via `AccountSetupTaskExecutor`
- Handles errors using `handleRpcError()` pattern:
  - **FLOOD_WAIT**: Sets account cooldown, sets task `retry_at`, status=retry
  - **Permanent auth errors**: Disables account, status=failed
  - **PEER_FLOOD**: Long cooldown, status=retry
  - **Unknown errors**: Backoff based on attempts, status=retry
- Updates task status and reschedules on retry

### 4. Service

**`app/Services/Telegram/AccountSetupTaskExecutor.php`**

Methods (all idempotent):

1. **`ensure2FA()`** - Checks 2FA status, optionally enables if configured
2. **`updateName()`** - Updates profile name (checks current first)
3. **`setPrivacyPhoneHidden()`** - Sets phone visibility to hidden
4. **`setPrivacyLastSeenEverybody()`** - Sets last seen to Everybody
5. **`setPhotoJpg()`** - Sets profile photo from JPG file
6. **`setPhotoGif()`** - Sets profile photo from GIF file
7. **`postStoryImage()`** - Posts story from image file
8. **`postStoryVideo()`** - Posts story from video file

All methods:
- Return standardized array: `['ok' => bool, 'error_code' => string, 'error' => string, 'meta' => array]`
- Are idempotent (check current state first)
- Wrap exceptions in `wrapError()` helper

### 5. Configuration

**`config/telegram_mtproto.php`**

```php
'setup' => [
    'enabled' => env('TELEGRAM_MTPROTO_SETUP_ENABLED', false),
    '2fa' => [
        'enable' => env('TELEGRAM_MTPROTO_SETUP_2FA_ENABLE', false),
        'password_source' => 'external', // Password provided externally
    ],
    'media' => [
        'default_jpg_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_JPG_PATH', null),
        'default_gif_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_GIF_PATH', null),
        'story_image_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_IMAGE_PATH', null),
        'story_video_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_VIDEO_PATH', null),
    ],
    'retry' => [
        'backoff_seconds' => [60, 300, 900, 3600],
    ],
],
```

### 6. Console Command

**`app/Console/Commands/TelegramSetupDispatch.php`**

- Command: `telegram:setup-dispatch`
- Dispatches `DispatchAccountSetupTasksJob`
- Checks if setup is enabled before running

### 7. Queues

Jobs route to queues based on task type:
- **Fast tasks** (2FA, name, privacy): `tg-setup-fast`
- **Media tasks** (photos, stories): 

Queues are created dynamically by Laravel - no explicit config needed.

## Safety Features

1. **Per-account lock**: Uses same lock key as pool service (`tg:mtproto:lock:{account_id}`)
2. **Idempotency**: Tasks check current state before applying changes
3. **Error handling**: Reuses existing `handleRpcError()` patterns
4. **Retry logic**: Tasks reschedule via `retry_at` instead of job retries
5. **No sensitive logging**: 2FA passwords are never logged

## Usage

1. **Enable setup:**
   ```bash
   # In .env
   TELEGRAM_MTPROTO_SETUP_ENABLED=true
   ```

2. **Configure media paths:**
   ```bash
   TELEGRAM_MTPROTO_SETUP_MEDIA_JPG_PATH=/path/to/default.jpg
   TELEGRAM_MTPROTO_SETUP_MEDIA_GIF_PATH=/path/to/default.gif
   TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_IMAGE_PATH=/path/to/story.jpg
   TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_VIDEO_PATH=/path/to/story.mp4
   ```

3. **Run dispatcher:**
   ```bash
   php artisan telegram:setup-dispatch
   ```

4. **Process queues:**
   ```bash
   php artisan queue:work --queue=tg-setup-fast,tg-setup-media
   ```

## Notes

- **2FA password**: Must be provided in task `payload_json['password']` when enabling. Not stored in code.
- **MadelineProto API**: Some method calls (privacy keys, password update) may need adjustment based on actual MadelineProto version. Wrappers ensure no crashes.
- **File paths**: Media tasks expect local file paths. Ensure files exist before task creation.
- **Concurrency**: Media tasks use slower queue (`tg-setup-media`) to limit concurrent uploads.

## Testing Checklist

- [ ] Tasks created for eligible accounts
- [ ] No duplicate tasks (unique constraint)
- [ ] Per-account lock prevents concurrent execution
- [ ] Idempotency: rerunning done tasks doesn't reapply
- [ ] FLOOD_WAIT sets cooldown and retry_at
- [ ] Permanent errors disable account and mark task failed
- [ ] Media tasks use correct queue
- [ ] Retry backoff works correctly
