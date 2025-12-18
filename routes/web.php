<?php

use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\Client\BalanceController;
use App\Http\Controllers\Staff\InvitationController;
use App\Http\Controllers\Staff\UserController;
use App\Http\Controllers\Staff\ClientController;
use App\Http\Controllers\Staff\ServiceController;
use App\Http\Controllers\Admin\ServicesController as AdminServicesController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Client\Auth\ClientAuthenticatedSessionController;
use App\Http\Controllers\Client\Auth\ClientRegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Staff\Auth\StaffAuthenticatedSessionController;
use App\Http\Controllers\Staff\Auth\StaffEmailVerificationNotificationController;
use App\Http\Controllers\Staff\Auth\StaffEmailVerificationPromptController;
use App\Http\Controllers\Staff\Auth\StaffVerifyEmailController;
use App\Http\Middleware\BlockClientFromStaffRoutes;
use App\Http\Middleware\UseStaffSession;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Client Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth:client'])->name('dashboard');

// Client Auth Routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('register', [ClientRegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [ClientRegisteredUserController::class, 'store']);

    Route::get('login', [ClientAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [ClientAuthenticatedSessionController::class, 'store']);
});

// Client Account Management
Route::middleware('auth:client')->group(function () {
    Route::post('logout', [ClientAuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('account', [AccountController::class, 'edit'])->name('client.account.edit');
    Route::patch('account', [AccountController::class, 'update'])->name('client.account.update');

    // Balance Management
    Route::get('balance/add', [BalanceController::class, 'create'])->name('client.balance.add');
    Route::post('balance', [BalanceController::class, 'store'])->name('client.balance.store');

    // Client Services (view only, no actions)
    Route::get('services', [ServiceController::class, 'clientIndex'])->name('client.services.index');
    Route::post('services/search', [ServiceController::class, 'clientSearch'])->name('client.services.search');
    Route::post('services/{service}/favorite', [ServiceController::class, 'toggleFavorite'])->name('client.services.favorite.toggle');
});



// Staff Auth Routes (guest only, under /staff prefix)
// Block clients from accessing staff routes
Route::prefix('staff')->middleware(['guest', UseStaffSession::class, BlockClientFromStaffRoutes::class])->group(function () {
    Route::get('login', [StaffAuthenticatedSessionController::class, 'create'])->name('staff.login');
    Route::post('login', [StaffAuthenticatedSessionController::class, 'store']);
});

// Staff Auth Routes (authenticated, but may not be verified)
// Block clients from accessing staff routes
Route::prefix('staff')->middleware(['auth:staff', UseStaffSession::class, BlockClientFromStaffRoutes::class])->group(function () {
    Route::post('logout', [StaffAuthenticatedSessionController::class, 'destroy'])->name('staff.logout');

    // Email verification routes (accessible without verification)
    Route::get('verify-email', StaffEmailVerificationPromptController::class)
        ->name('staff.verification.notice');

    Route::post('email/verification-notification', [StaffEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('staff.verification.send');
});

// Email verification route (accessible without auth - user clicks link from email)
// Block clients from accessing staff routes
Route::prefix('staff')->middleware([UseStaffSession::class, 'signed', 'throttle:6,1'])->group(function () {
    Route::get('verify-email/{id}/{hash}', StaffVerifyEmailController::class)
        ->name('staff.verification.verify');
});

// Staff Dashboard and Protected Routes
// Block clients from accessing staff routes
Route::prefix('staff')->middleware(['auth:staff', 'staff.verified', UseStaffSession::class, BlockClientFromStaffRoutes::class])->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('staff.dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('staff.profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('staff.profile.update');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('staff.profile.avatar.destroy');

    // Super Admin Routes
    Route::middleware('staff.role:super_admin')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('staff.settings.index');

        Route::get('settings/roles', [RoleController::class, 'index'])->name('staff.settings.roles.index');
        Route::get('settings/roles/{role}/edit', [RoleController::class, 'edit'])->name('staff.settings.roles.edit');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('staff.settings.roles.update');

        Route::get('settings/invitations', [InvitationController::class, 'index'])->name('staff.settings.invitations.index');
        Route::get('settings/invitations/create', [InvitationController::class, 'create'])->name('staff.settings.invitations.create');
        Route::post('settings/invitations', [InvitationController::class, 'store'])->name('staff.settings.invitations.store');
        Route::get('settings/invitations/{invitation}', [InvitationController::class, 'show'])->name('staff.settings.invitations.show');
        Route::post('settings/invitations/{invitation}/send-email', [InvitationController::class, 'sendEmail'])->name('staff.settings.invitations.send-email');

        // Staff Users Management
        Route::get('users', [UserController::class, 'index'])->name('staff.users.index');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('staff.users.destroy');
        Route::post('users/{user}/resend-verification', [UserController::class, 'resendVerification'])->name('staff.users.resend-verification');

        // Admin Services Management
        Route::prefix('admin')->group(function () {
            Route::get('services', [AdminServicesController::class, 'index'])->name('admin.services.index');
            Route::post('services', [AdminServicesController::class, 'store'])->name('admin.services.store');
            Route::put('services/{service}', [AdminServicesController::class, 'update'])->name('admin.services.update');
        });
    });

    // Clients Management (accessible to all authenticated staff, filtered by role)
    Route::get('clients', [ClientController::class, 'index'])->name('staff.clients.index');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('staff.clients.edit');
    Route::patch('clients/{client}', [ClientController::class, 'update'])->name('staff.clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('staff.clients.destroy');
    Route::post('clients/{client}/assign-staff', [ClientController::class, 'assignStaff'])->name('staff.clients.assign-staff');

    // Services Management (accessible to all authenticated staff)
    Route::get('services', [ServiceController::class, 'index'])->name('staff.services.index');
    Route::post('services/search', [ServiceController::class, 'search'])->name('staff.services.search');
    Route::get('services/create', [ServiceController::class, 'create'])->name('staff.services.create');
    Route::post('services', [ServiceController::class, 'store'])->name('staff.services.store');
    Route::get('services/{service}/edit', [ServiceController::class, 'edit'])->name('staff.services.edit');
    Route::put('services/{service}', [ServiceController::class, 'update'])->name('staff.services.update');
    Route::post('services/{service}/duplicate', [ServiceController::class, 'duplicate'])->name('staff.services.duplicate');
    Route::post('services/{service}/update-mode', [ServiceController::class, 'updateMode'])->name('staff.services.update-mode');
    Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('staff.services.destroy');
    Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleServiceStatus'])->name('staff.services.toggle-status');
    Route::post('services/{serviceId}/restore', [ServiceController::class, 'restore'])->name('staff.services.restore');

    // Categories Management (accessible to all authenticated staff)
    Route::post('categories', [ServiceController::class, 'storeCategory'])->name('staff.categories.store');
    Route::put('categories/{category}', [ServiceController::class, 'updateCategory'])->name('staff.categories.update');
    Route::post('categories/{category}/toggle-status', [ServiceController::class, 'toggleCategoryStatus'])->name('staff.categories.toggle-status');
});

// Staff Invitation Acceptance Routes (guest only, under /staff prefix)
// Block clients from accessing staff routes
Route::prefix('staff')->middleware(['guest', UseStaffSession::class, BlockClientFromStaffRoutes::class])->group(function () {
    Route::get('invite/accept/{token}', [AcceptInvitationController::class, 'show'])
        ->middleware('throttle:10,1')
        ->name('staff.invitation.accept');

    Route::post('invite/register/{token}', [AcceptInvitationController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('staff.invitation.register');
});

// Legacy invitation routes redirect to staff routes (for backward compatibility)
// These routes use the same middleware as staff routes to ensure proper session handling
Route::middleware(['guest', UseStaffSession::class])->group(function () {
    Route::get('/invite/accept/{token}', function ($token) {
        return redirect()->route('staff.invitation.accept', $token);
    })->name('invitation.accept');

    // Handle GET requests to register route - redirect to accept form
    Route::get('/invite/register/{token}', function ($token) {
        return redirect()->route('staff.invitation.accept', $token);
    });

    // POST requests to register route use the same controller and middleware as staff routes
    Route::post('/invite/register/{token}', [AcceptInvitationController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('invitation.register');
});

require __DIR__.'/auth.php';
