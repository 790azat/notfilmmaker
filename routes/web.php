<?php

use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TelegramController;
use App\Livewire;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', Livewire\Home::class)->name('home');
Route::get('/works', Livewire\Works\Index::class)->name('works.index');
Route::get('/works/{work}', Livewire\Works\Show::class)->name('works.show');
Route::get('/about', Livewire\About::class)->name('about');
Route::get('/contact', Livewire\Contact::class)->name('contact');
Route::get('/lang/{locale}', LocaleController::class)->name('locale');
Route::get('/sitemap.xml', SitemapController::class);
Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/cron/youtube', [CronController::class, 'youtube']);
Route::get('/cron/sync', [CronController::class, 'youtube']);
Route::get('/cron/instagram-archive', [CronController::class, 'archive']);
Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
Route::get('/chat/poll', [ChatController::class, 'poll'])->name('chat.poll');
Route::post('/telegram/webhook', TelegramController::class)->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
    Route::get('/register', Livewire\Auth\Register::class)->name('register');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Livewire\Admin\Dashboard::class)->name('dashboard');
    Route::get('/works', Livewire\Admin\Works::class)->name('works');
    Route::get('/works/create', Livewire\Admin\WorkForm::class)->name('works.create');
    Route::get('/works/{work:id}/edit', Livewire\Admin\WorkForm::class)->name('works.edit');
    Route::get('/messages', Livewire\Admin\Messages::class)->name('messages');
    Route::get('/settings', Livewire\Admin\Settings::class)->name('settings');
    Route::get('/users', Livewire\Admin\Users::class)->name('users');
    Route::get('/profile', Livewire\Admin\Profile::class)->name('profile');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload');
    Route::post('/blob-token', [UploadController::class, 'blobToken'])->name('blob-token');
});
