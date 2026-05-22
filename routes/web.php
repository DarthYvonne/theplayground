<?php

use App\Http\Controllers\Admin\CourseAdminController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Trainer\BroadcastController;
use App\Http\Controllers\Trainer\TrainerController;
use Illuminate\Support\Facades\Route;

// Public catalog
Route::get('/', [CourseController::class, 'index'])->name('home');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

// Stripe webhook (no auth, no CSRF — gated by signature verification)
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('enroll');
    Route::get('/courses/{course}/enroll/return', [EnrollmentController::class, 'returnFromCheckout'])->name('enroll.return');
    Route::post('/courses/{course}/cancel', [EnrollmentController::class, 'cancel'])->name('enroll.cancel');

    // Chat
    Route::get('/chat', [ChatController::class, 'platform'])->name('chat.platform');
    Route::get('/api/chat/platform', [ChatController::class, 'listPlatform']);
    Route::post('/api/chat/platform', [ChatController::class, 'sendPlatform']);
    Route::get('/courses/{course}/chat', [ChatController::class, 'course'])->name('chat.course');
    Route::get('/api/chat/courses/{course}', [ChatController::class, 'listCourse']);
    Route::post('/api/chat/courses/{course}', [ChatController::class, 'sendCourse']);

    // Notifications
    Route::get('/api/notifications/counts', [NotificationController::class, 'unreadCount']);
    Route::get('/api/notifications', [NotificationController::class, 'list']);
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Trainer
    Route::middleware('role:trainer,owner')->prefix('trainer')->name('trainer.')->group(function () {
        Route::get('/', [TrainerController::class, 'index'])->name('index');
        Route::get('/courses/{course}/participants', [TrainerController::class, 'participants'])->name('participants');
        Route::get('/courses/{course}/email', [BroadcastController::class, 'create'])->name('broadcast');
        Route::post('/courses/{course}/email', [BroadcastController::class, 'send'])->name('broadcast.send');
    });

    // Owner admin
    Route::middleware('role:owner')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/courses', [CourseAdminController::class, 'index'])->name('courses.index');
        Route::get('/courses/create', [CourseAdminController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseAdminController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseAdminController::class, 'edit'])->name('courses.edit');
        Route::post('/courses/{course}', [CourseAdminController::class, 'update'])->name('courses.update');
        Route::post('/courses/{course}/delete', [CourseAdminController::class, 'destroy'])->name('courses.destroy');
        Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/role', [UserAdminController::class, 'updateRole'])->name('users.role');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-stripe', [SettingsController::class, 'testStripe'])->name('settings.test');
    });
});
