# MadelineProto Proxy Support Implementation

## Summary

Proxy support has been added to the `MtprotoClientFactory` to route all MTProto calls through a proxy per account.

## Changes Made

### 1. Updated `MtprotoClientFactory.php`

**Key Features:**
- ✅ Proxy validation before instance creation
- ✅ Support for SOCKS5, HTTP, and MTProxy types
- ✅ Cache key includes proxy configuration hash
- ✅ Helper method `buildMadelineSettingsForAccount()` for clean code organization
- ✅ Proper error handling with `PROXY_REQUIRED` error code

**New Methods:**
- `buildMadelineSettingsForAccount()` - Main helper to build settings with proxy
- `configureSocks5Proxy()` - Configures SOCKS5 proxy
- `configureHttpProxy()` - Configures HTTP proxy  
- `configureMtproxy()` - Configures MTProxy (obfuscated stream)
- `validateProxyConfiguration()` - Validates proxy settings
- `buildCacheKey()` - Builds cache key with proxy hash

### 2. Updated `MtprotoTelegramAccount.php` Model

**Added to `$fillable`:**
- `proxy_type`
- `proxy_host`
- `proxy_port`
- `proxy_user`
- `proxy_pass`
- `proxy_secret`
- `force_proxy`

**Added to `$casts`:**
- `proxy_port` => 'integer'
- `force_proxy` => 'boolean'

## Behavior

### When `proxy_type` is `null`:
- If `force_proxy = true`: Throws `RuntimeException` with error code `PROXY_REQUIRED`
- If `force_proxy = false`: Creates MadelineProto without proxy (existing behavior)

### When `proxy_type` is `'socks5'`:
- Requires: `proxy_host`, `proxy_port`
- Optional: `proxy_user`, `proxy_pass`
- Configures: `Connection::setProxy()` with `SocksProxy`

### When `proxy_type` is `'http'`:
- Requires: `proxy_host`, `proxy_port`
- Optional: `proxy_user`, `proxy_pass`
- Configures: `Connection::setProxy()` with `HttpProxy`

### When `proxy_type` is `'mtproxy'`:
- Requires: `proxy_host`, `proxy_port`, `proxy_secret`
- Configures: `Connection::setObfuscatedStream()` with host, port, secret

## Cache Key Strategy

Cache key format: `{account_id}_{proxy_hash}`

The proxy hash includes:
- `proxy_type`
- `proxy_host`
- `proxy_port`
- `proxy_user`
- `proxy_secret`

This ensures cached instances are not reused when proxy settings change.

## Database Migration Required

You need to add the following columns to `mtproto_telegram_accounts` table:

```php
$table->string('proxy_type', 20)->nullable(); // 'socks5', 'http', 'mtproxy'
$table->string('proxy_host')->nullable();
$table->integer('proxy_port')->nullable();
$table->string('proxy_user')->nullable();
$table->string('proxy_pass')->nullable();
$table->string('proxy_secret')->nullable(); // For MTProxy only
$table->boolean('force_proxy')->default(false);
```

## Notes

1. **MadelineProto API Classes**: The implementation uses:
   - `\danog\MadelineProto\Settings\SocksProxy`
   - `\danog\MadelineProto\Settings\HttpProxy`
   - `\danog\MadelineProto\Settings\ObfuscatedStream`

   If these class names differ in your MadelineProto version, adjust accordingly.

2. **Error Handling**: All proxy validation errors throw `RuntimeException` with appropriate error codes.

3. **Backward Compatibility**: Accounts without proxy settings continue to work as before (direct connection).

4. **Session Path**: Existing session path logic is preserved.

