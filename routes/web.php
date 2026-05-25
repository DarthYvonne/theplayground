<?php

use App\Http\Controllers\Admin\CourseAdminController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BeskederController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PreviewRoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RespektController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Trainer\TrainerController;
use Illuminate\Support\Facades\Route;

// Root: send logged-in users to their feed; guests see the public catalog
Route::get('/', function (\Illuminate\Http\Request $request) {
    if ($request->user()) return redirect('/dashboard');
    return app(CourseController::class)->index($request);
})->name('home');

// Public catalog
Route::get('/hold', [CourseController::class, 'index'])->name('catalog');
Route::get('/calendar', [CourseController::class, 'calendar'])->name('home.calendar');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses/{course}/medlemmer', [CourseController::class, 'members'])
    ->middleware('auth')->name('courses.members');

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
    Route::get('/dashboard', [DashboardController::class, 'feed'])->name('dashboard');
    Route::get('/hold/dine', [CourseController::class, 'mine'])->name('catalog.mine');
    Route::get('/api/feed', [FeedController::class, 'list']);
    Route::get('/api/respekt', [RespektController::class, 'list']);
    Route::post('/api/respekt', [RespektController::class, 'toggle']);

    Route::get('/beskeder', [BeskederController::class, 'index'])->name('beskeder.index');
    Route::get('/beskeder/{user}', [BeskederController::class, 'show'])->name('beskeder.show');
    Route::post('/beskeder', [BeskederController::class, 'store'])->name('beskeder.store');
    Route::post('/beskeder/settings', [BeskederController::class, 'updateSettings'])->name('beskeder.settings');
    Route::get('/api/messages/recipients', [BeskederController::class, 'recipients'])->name('beskeder.recipients');

    Route::get('/medlemmer', [MembersController::class, 'index'])->name('members.index');
    Route::get('/medlemmer/{user}', [MembersController::class, 'show'])->name('members.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/hold', [ProfileController::class, 'courses'])->name('profile.courses');
    Route::get('/profile/betaling', [ProfileController::class, 'billing'])->name('profile.billing');
    Route::post('/profile/betaling/portal', [ProfileController::class, 'billingPortal'])->name('profile.billing.portal');

    Route::post('/preview-role', [PreviewRoleController::class, 'update'])->name('preview.role');

    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('enroll');
    Route::get('/courses/{course}/enroll/return', [EnrollmentController::class, 'returnFromCheckout'])->name('enroll.return');
    Route::post('/courses/{course}/cancel', [EnrollmentController::class, 'cancel'])->name('enroll.cancel');

    // Chat
    Route::get('/chat', [ChatController::class, 'platform'])->name('chat.platform');
    Route::get('/api/chat/platform', [ChatController::class, 'listPlatform']);
    Route::post('/api/chat/platform', [ChatController::class, 'sendPlatform']);
    Route::post('/api/feed/upload-image', [ChatController::class, 'uploadImage']);
    Route::get('/courses/{course}/chat', [ChatController::class, 'course'])->name('chat.course');
    Route::get('/api/chat/courses/{course}', [ChatController::class, 'listCourse']);
    Route::post('/api/chat/courses/{course}', [ChatController::class, 'sendCourse']);
    Route::post('/api/messages/{message}', [ChatController::class, 'updateMessage']);
    Route::post('/api/messages/{message}/delete', [ChatController::class, 'destroyMessage']);

    // Notifications
    Route::get('/api/notifications/counts', [NotificationController::class, 'unreadCount']);
    Route::get('/api/notifications', [NotificationController::class, 'list']);
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Trainer
    Route::middleware('role:trainer,owner')->prefix('trainer')->name('trainer.')->group(function () {
        Route::get('/', [TrainerController::class, 'index'])->name('index');
        Route::get('/calendar', [TrainerController::class, 'calendar'])->name('calendar');
        Route::get('/courses/{course}/participants', [TrainerController::class, 'participants'])->name('participants');
        Route::post('/courses/{course}/cancellations', [TrainerController::class, 'storeCancellation'])->name('cancellations.store');
        Route::delete('/courses/{course}/cancellations', [TrainerController::class, 'destroyCancellation'])->name('cancellations.destroy');
    });

    // Owner admin
    Route::middleware('role:owner')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/courses', [CourseAdminController::class, 'index'])->name('courses.index');
        Route::get('/courses/calendar', [CourseAdminController::class, 'calendar'])->name('courses.calendar');
        Route::get('/courses/create', [CourseAdminController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseAdminController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseAdminController::class, 'edit'])->name('courses.edit');
        Route::post('/courses/{course}', [CourseAdminController::class, 'update'])->name('courses.update');
        Route::post('/courses/{course}/delete', [CourseAdminController::class, 'destroy'])->name('courses.destroy');
        Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/role', [UserAdminController::class, 'updateRole'])->name('users.role');
        Route::post('/users/{user}/delete', [UserAdminController::class, 'destroy'])->name('users.destroy');
        Route::get('/settings', [SettingsController::class, 'revenue'])->name('settings.revenue');
        Route::get('/settings/andet', [SettingsController::class, 'other'])->name('settings.other');
    });
});
