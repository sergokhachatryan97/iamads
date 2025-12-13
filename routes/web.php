<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Email verification routes (accessible without verification)
    // These are already defined in routes/auth.php
    
    // Protected routes - require email verification
    Route::middleware('verified')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

            Route::middleware('role:super_admin')->group(function () {
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

                Route::get('/settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
                Route::get('/settings/roles/{role}/edit', [RoleController::class, 'edit'])->name('settings.roles.edit');
                Route::put('/settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');

                Route::get('/settings/invitations', [InvitationController::class, 'index'])->name('settings.invitations.index');
                Route::get('/settings/invitations/create', [InvitationController::class, 'create'])->name('settings.invitations.create');
                Route::post('/settings/invitations', [InvitationController::class, 'store'])->name('settings.invitations.store');
                Route::get('/settings/invitations/{invitation}', [InvitationController::class, 'show'])->name('settings.invitations.show');
                Route::post('/settings/invitations/{invitation}/send-email', [InvitationController::class, 'sendEmail'])->name('settings.invitations.send-email');

                // Admin Users Management
                Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
                Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
                Route::post('/admin/users/{user}/resend-verification', [UserController::class, 'resendVerification'])->name('admin.users.resend-verification');
            });
    });
});

// Invitation acceptance routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/invite/accept/{token}', [AcceptInvitationController::class, 'show'])
        ->middleware('throttle:10,1')
        ->name('invitation.accept');

    Route::post('/invite/register/{token}', [AcceptInvitationController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('invitation.register');
});

require __DIR__.'/auth.php';
