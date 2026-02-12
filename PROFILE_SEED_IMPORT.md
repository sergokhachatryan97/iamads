# Profile Seed Import from Google Sheets

## Overview

Implemented a system to import profile setup data from Google Sheets and integrate it with the existing account setup pipeline. The system downloads Google Drive media files once, stores them locally, and uses seed data to populate account setup tasks.

## Implementation

### 1. Database Schema

**Migration:** `database/migrations/2026_01_26_073509_create_account_profile_seeds_table.php`

**Table:** `account_profile_seeds`
- `id` (primary key)
- `username` (string, unique, indexed) - normalized lowercase, without '@'
- `display_name` (string, nullable)
- `bio` (text, nullable)
- `profile_photo_url` (text, nullable)
- `story_url` (text, nullable)
- `profile_photo_local_path` (text, nullable)
- `story_local_path` (text, nullable)
- `profile_photo_mime` (string, nullable)
- `story_mime` (string, nullable)
- `status` (string, nullable, indexed) - ready|needs_download|failed
- `last_error` (text, nullable)
- `created_at`/`updated_at`

### 2. Model

**`app/Models/AccountProfileSeed.php`**
- Helper methods: `normalizeUsername()`, `isReady()`, `needsDownload()`
- Status constants: `STATUS_READY`, `STATUS_NEEDS_DOWNLOAD`, `STATUS_FAILED`

### 3. Configuration

**`config/telegram_mtproto.php`** - Added:

```php
'sheet' => [
    'enabled' => env('TELEGRAM_SHEET_ENABLED', false),
    'csv_url' => env('TELEGRAM_SHEET_CSV_URL', null),
    'private' => [
        'use_api' => env('TELEGRAM_SHEET_USE_API', false),
        'spreadsheet_id' => env('TELEGRAM_SHEET_SPREADSHEET_ID', null),
        'range' => env('TELEGRAM_SHEET_RANGE', 'Sheet1!A:E'),
    ],
    'match_by_username' => env('TELEGRAM_SHEET_MATCH_BY_USERNAME', false),
],

'media' => [
    'storage_dir' => env('TELEGRAM_MEDIA_STORAGE_DIR', 'telegram_media'),
    'max_bytes' => env('TELEGRAM_MEDIA_MAX_BYTES', 30 * 1024 * 1024), // 30MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'],
    'allowed_mimes' => [...],
    'download_timeout_seconds' => env('TELEGRAM_MEDIA_DOWNLOAD_TIMEOUT', 300),
],
```

### 4. Jobs

#### `ImportProfileSeedsFromSheetJob`
- **CSV Mode**: Downloads CSV from `TELEGRAM_SHEET_CSV_URL` and parses rows
- **API Mode**: Uses Google Sheets API (requires `google/apiclient` package)
- Parses columns: Telegram Unique Username, Name, Bio, Profile photo, Story
- Upserts seeds by normalized username
- Sets status: `needs_download` if media URLs exist without local paths, else `ready`
- Queue: `tg-sheet-import`

#### `DownloadSeedMediaJob`
- Downloads media for a single seed (one URL at a time)
- Uses per-URL lock: `tg:media:url:{sha1(url)}` to prevent duplicates
- Converts Google Drive URLs to direct download URLs
- Validates file size and MIME type
- Saves to `storage/app/telegram_media/{sha1(url)}.{ext}`
- Updates seed with `local_path` and `mime`
- Queue: `tg-media-prep` (low concurrency recommended)

#### `DispatchSeedMediaDownloadsJob`
- Finds seeds with `status=needs_download` or missing local paths
- Dispatches `DownloadSeedMediaJob` for each URL needed
- Queue: default

### 5. Console Commands

- **`telegram:sheet-import`** - Imports seeds from Google Sheet
- **`telegram:sheet-media-download`** - Dispatches media downloads

### 6. Integration with Account Setup

**Modified:** `app/Jobs/DispatchAccountSetupTasksJob.php`

- Finds matching `AccountProfileSeed` for each account:
  - If `match_by_username=true`: Matches by normalized username (if account has username field)
  - If `match_by_username=false`: Picks random ready seed
- Only creates tasks if seed `status=ready`
- Builds task list dynamically based on seed data:
  - `UPDATE_NAME` if `display_name` present
  - `UPDATE_BIO` if `bio` present (new task type)
  - `SET_PHOTO_JPG` or `SET_PHOTO_GIF` if `profile_photo_local_path` exists (based on MIME)
  - `STORY_IMAGE` or `STORY_VIDEO` if `story_local_path` exists (based on MIME)
- Populates `payload_json` from seed data:
  - Name: `{"name": "<display_name>"}`
  - Bio: `{"bio": "<bio>"}`
  - Media: `{"file_path": "<absolute_path>", "local_path": "<relative_path>", "mime": "<mime>"}`

### 7. New Task Type

**`UPDATE_BIO`** - Added to:
- `app/Models/MtprotoAccountTask.php` - Constant `TASK_UPDATE_BIO`
- `app/Services/Telegram/AccountSetupTaskExecutor.php` - Method `updateBio()`
  - Checks current bio first (idempotent)
  - Updates via `account->updateProfile(['about' => $bio])`

### 8. Media Executor Updates

Updated media methods in `AccountSetupTaskExecutor` to support both:
- `file_path` (absolute path)
- `local_path` (storage relative path, converted to absolute)

## Usage

### 1. Configure Sheet Import

```bash
# In .env
TELEGRAM_SHEET_ENABLED=true
TELEGRAM_SHEET_CSV_URL=https://docs.google.com/spreadsheets/d/.../export?format=csv
# OR
TELEGRAM_SHEET_USE_API=true
TELEGRAM_SHEET_SPREADSHEET_ID=...
TELEGRAM_SHEET_MATCH_BY_USERNAME=false  # or true to match by username
```

### 2. Import Seeds

```bash
php artisan telegram:sheet-import
```

### 3. Download Media

```bash
php artisan telegram:sheet-media-download
```

Process downloads with low concurrency:
```bash
php artisan queue:work --queue=tg-media-prep --max-jobs=1
```

### 4. Run Account Setup

```bash
php artisan telegram:setup-dispatch
php artisan queue:work --queue=tg-setup-fast,tg-setup-media
```

## Google Drive URL Formats Supported

- `https://drive.google.com/file/d/<ID>/view...`
- `https://drive.google.com/open?id=<ID>`
- `https://drive.google.com/uc?id=<ID>&export=download` (direct)

All converted to: `https://drive.google.com/uc?id=<ID>&export=download`

## Safety Features

1. **Per-URL Lock**: Prevents duplicate downloads of same URL
2. **File Validation**: Size limits (30MB default) and MIME type whitelist
3. **Idempotency**: Re-importing sheet updates rows without duplicating
4. **Error Handling**: Failed downloads mark seed as `failed` with error message
5. **No Sensitive Logging**: URLs logged as SHA1 hashes only

## Notes

- **Google Sheets API**: Requires `composer require google/apiclient` and credentials file at `storage/app/google-sheets-credentials.json`
- **Account Username**: If `MtprotoTelegramAccount` doesn't have `username` field, `match_by_username=true` will return null and proceed without seed
- **Media Storage**: Files saved to `storage/app/telegram_media/` with filename `{sha1(url)}.{ext}`
- **Task Creation**: Tasks only created if seed has required data (e.g., no UPDATE_NAME if display_name missing)
