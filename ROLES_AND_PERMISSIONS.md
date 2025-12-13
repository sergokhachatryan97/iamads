# Roles and Permissions Guide

## Creating the First Super Admin

The first super admin user is automatically created when you run the database seeder:

```bash
php artisan db:seed
```

Or run the seeder individually:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

**Default Super Admin Credentials:**
- Email: `superadmin@example.com`
- Password: `password`

⚠️ **Important:** Change the password after first login!

## Assigning Roles to Users

### Assign super_admin Role to an Existing User

#### Method 1: Using Eloquent (Recommended)
```php
use App\Models\User;
use Spatie\Permission\Models\Role;

// Get the user
$user = User::find(1); // or User::where('email', 'user@example.com')->first();

// Assign the super_admin role
$user->assignRole('super_admin');

// Or assign multiple roles at once
$user->assignRole(['super_admin', 'admin']);
```

#### Method 2: Using Role Model
```php
use App\Models\User;
use Spatie\Permission\Models\Role;

$user = User::find(1);
$superAdminRole = Role::firstWhere('name', 'super_admin');

$user->assignRole($superAdminRole);
```

#### Method 3: Using Tinker (Command Line)
```bash
php artisan tinker
```

Then in tinker:
```php
$user = App\Models\User::where('email', 'user@example.com')->first();
$user->assignRole('super_admin');
```

### Assign Other Roles

```php
// Assign admin role
$user->assignRole('admin');

// Assign user role
$user->assignRole('user');

// Assign multiple roles
$user->assignRole(['admin', 'user']);
```

### Remove Roles

```php
// Remove a specific role
$user->removeRole('admin');

// Remove all roles
$user->removeRole($user->roles);

// Sync roles (removes all and assigns only the specified ones)
$user->syncRoles(['user']);
```

## Checking Roles and Permissions

### Check if User Has Role
```php
$user->hasRole('super_admin'); // returns true/false
$user->hasAnyRole(['admin', 'super_admin']); // returns true if has any
$user->hasAllRoles(['admin', 'user']); // returns true if has all
```

### Check if User Has Permission
```php
$user->can('users.invite'); // returns true/false
$user->hasPermissionTo('pages.edit.any');
$user->hasAnyPermission(['pages.edit.any', 'pages.edit.own']);
```

### Using Gates
```php
use Illuminate\Support\Facades\Gate;

// Check permission
Gate::allows('users.invite'); // checks current authenticated user
Gate::forUser($user)->allows('users.invite'); // checks specific user

// In Blade templates
@can('users.invite')
    <!-- Content for users with permission -->
@endcan

@role('super_admin')
    <!-- Content for super_admin only -->
@endrole
```

## Available Roles

- **super_admin**: Has all permissions, bypasses all Gate checks
- **admin**: Can invite users and edit any pages
- **user**: Can only edit own pages

## Available Permissions

- `users.invite` - Invite new users
- `roles.manage` - Manage roles and permissions
- `pages.edit.any` - Edit any page
- `pages.edit.own` - Edit own pages only

## Super Admin Bypass

The `super_admin` role automatically bypasses all permission checks thanks to the `Gate::before()` logic in `AppServiceProvider`. This means:

```php
// Even if super_admin doesn't have explicit permission, they can still access
$superAdmin->can('any.permission'); // returns true
Gate::allows('any.permission'); // returns true for super_admin
```

