# Icon Picker Component - Complete Setup Guide

## Architecture Overview

This icon picker uses a **server-side API with pagination** approach:
- Icons and emojis stored in database tables
- API endpoint handles search and pagination
- Frontend fetches data on-demand with debounced search
- Caching layer for performance

---

## A) Migration Files

**File**: `database/migrations/2025_12_17_000001_create_icons_table.php`
- Creates `icons` table with `id`, `name`, `class` (Font Awesome class), `timestamps`
- Indexed on `name` for fast search

**File**: `database/migrations/2025_12_17_000002_create_emojis_table.php`
- Creates `emojis` table with `id`, `name`, `symbol` (emoji character), `timestamps`
- Indexed on `name` for fast search

**Run migrations:**
```bash
php artisan migrate
```

---

## B) Models

**File**: `app/Models/Icon.php`
- Eloquent model for icons table
- Fillable: `name`, `class`

**File**: `app/Models/Emoji.php`
- Eloquent model for emojis table
- Fillable: `name`, `symbol`

---

## C) Seeder

**File**: `database/seeders/IconPickerSeeder.php`
- Seeds ~50 icons (Font Awesome classes)
- Seeds ~100 emojis (common emojis)
- Sample data to get started immediately

**Run seeder:**
```bash
php artisan db:seed --class=IconPickerSeeder
```

**To add more data:**
- Add icons: `Icon::create(['name' => '...', 'class' => 'fas fa-...']);`
- Add emojis: `Emoji::create(['name' => '...', 'symbol' => '...']);`

---

## D) API Route + Controller

**Route**: `routes/api.php`
```php
Route::get('/icon-picker', [IconPickerController::class, 'index']);
```

**Controller**: `app/Http/Controllers/Api/IconPickerController.php`

**API Endpoint**: `GET /api/icon-picker`

**Query Parameters:**
- `tab` (required): `icons` or `emoji`
- `q` (optional): Search query string
- `limit` (optional, default: 200): Number of items per page
- `offset` (optional, default: 0): Pagination offset

**Response Format:**
```json
{
  "items": [
    { "type": "icon", "name": "Home", "value": "fas fa-home" },
    { "type": "emoji", "name": "grinning face", "value": "😀" }
  ],
  "has_more": true,
  "next_offset": 200
}
```

**Features:**
- Validation of all parameters
- Case-insensitive search on `name` field
- Pagination using `limit` and `offset`
- Caching with configurable TTL (default 10 minutes)
- Cache key includes tab, query, limit, offset for proper invalidation

**Config**: `config/iconpicker.php`
- `cache_ttl`: Cache time-to-live in seconds (default: 600)

---

## E) Blade Component

**File**: `resources/views/components/icon-picker.blade.php`

**Props:**
- `name` (default: "icon"): Hidden input name
- `value` (default: ""): Initial selected value
- `label` (optional): Label text (not currently displayed)

**Usage:**
```blade
<x-icon-picker name="icon" value="{{ old('icon') }}" />
```

**Features:**
- ✅ `x-teleport="body"` - Popover never clipped
- ✅ Smart positioning (below/above, left/right adjustment)
- ✅ Measures popover width/height dynamically (no hardcoded values)
- ✅ Server-side search with 200ms debounce
- ✅ Pagination with "Load more" button
- ✅ Race condition handling (ignores stale responses)
- ✅ Loading states
- ✅ Accessibility (ARIA roles, keyboard navigation)
- ✅ Close on outside click and Escape
- ✅ Focus search input on open

**Selected Value Storage:**
- Icons: Font Awesome class string (e.g., `fas fa-home`)
- Emojis: Emoji symbol (e.g., `😀`)
- Uploaded: DataURL string (e.g., `data:image/png;base64,...`)

---

## F) Dependencies & Setup Notes

### Font Awesome
**Already included** in `resources/views/layouts/app.blade.php`:
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" ... />
```

### Alpine.js
**Already included** in `resources/js/app.js`:
```javascript
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

**Ensure Alpine.js is loaded** before the component renders. If using Vite, it should be bundled automatically.

### API Routes
The API route is in `routes/api.php` and should be accessible at `/api/icon-picker`.

**Note**: If you're using API route prefixing or middleware, adjust the fetch URL in the component accordingly.

---

## Quick Start Checklist

1. ✅ Run migrations: `php artisan migrate`
2. ✅ Seed data: `php artisan db:seed --class=IconPickerSeeder`
3. ✅ Clear caches: `php artisan route:clear && php artisan config:clear && php artisan view:clear`
4. ✅ Use component: `<x-icon-picker name="icon" />`
5. ✅ Test API: Visit `/api/icon-picker?tab=icons&limit=10` in browser

---

## Adding More Icons/Emojis

**Via Tinker:**
```bash
php artisan tinker
```

```php
// Add icon
App\Models\Icon::create(['name' => 'New Icon', 'class' => 'fas fa-new-icon']);

// Add emoji
App\Models\Emoji::create(['name' => 'New Emoji', 'symbol' => '🆕']);
```

**Via Seeder:**
Edit `database/seeders/IconPickerSeeder.php` and re-run:
```bash
php artisan db:seed --class=IconPickerSeeder
```

**Note**: Cache will auto-refresh after TTL expires, or clear manually:
```bash
php artisan cache:clear
```

---

## Performance Notes

- **Caching**: API responses cached for 10 minutes (configurable)
- **Pagination**: Default 200 items per page (adjustable via API)
- **Search**: Server-side filtering reduces data transfer
- **Debounce**: 200ms delay prevents excessive API calls

---

## Troubleshooting

**Modal not showing:**
- Check browser console for Alpine.js errors
- Ensure Alpine.js is loaded before component renders
- Verify `x-teleport="body"` is supported (Alpine.js 3.4+)

**API not working:**
- Check route: `php artisan route:list | grep icon-picker`
- Test endpoint: `curl http://localhost:8000/api/icon-picker?tab=icons&limit=5`
- Check API middleware (should be public or authenticated as needed)

**No data showing:**
- Verify seeder ran: `php artisan tinker` → `App\Models\Icon::count()`
- Check database tables exist
- Clear cache: `php artisan cache:clear`
