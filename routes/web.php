<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CampaignListController;
use App\Http\Controllers\CampaignPageController;
use App\Http\Controllers\PlatformConnectionOAuthController;
use App\Http\Controllers\PlatformConnectionSettingsController;
use App\Http\Controllers\Web\BriefWebController;
use App\Http\Controllers\Web\ClientWebController;
use App\Http\Controllers\Web\NotificationWebController;
use App\Http\Controllers\Web\ReportWebController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Identifiants invalides.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Campaigns
    Route::get('/campaigns', CampaignListController::class)->name('web.campaigns.index');
    Route::get('/campaigns/{campaign}/trend.csv', [CampaignPageController::class, 'exportTrendCsv'])->name('web.campaigns.trend.csv');
    Route::get('/campaigns/{campaign}', CampaignPageController::class)->name('web.campaigns.show');

    // Reports
    Route::get('/reports', [ReportWebController::class, 'index'])->name('web.reports.index');
    Route::get('/reports/create', [ReportWebController::class, 'create'])->name('web.reports.create');
    Route::post('/reports', [ReportWebController::class, 'store'])->name('web.reports.store');
    Route::get('/reports/{report}', [ReportWebController::class, 'show'])->name('web.reports.show');

    // Briefs
    Route::get('/briefs', [BriefWebController::class, 'index'])->name('web.briefs.index');
    Route::get('/briefs/create', [BriefWebController::class, 'create'])->name('web.briefs.create');
    Route::post('/briefs', [BriefWebController::class, 'store'])->name('web.briefs.store');
    Route::get('/briefs/{brief}', [BriefWebController::class, 'show'])->name('web.briefs.show');

    // Clients
    Route::get('/clients', [ClientWebController::class, 'index'])->name('web.clients.index');
    Route::get('/clients/create', [ClientWebController::class, 'create'])->name('web.clients.create');
    Route::post('/clients', [ClientWebController::class, 'store'])->name('web.clients.store');
    Route::get('/clients/{client}', [ClientWebController::class, 'show'])->name('web.clients.show');

    // Notifications
    Route::get('/notifications', [NotificationWebController::class, 'index'])->name('web.notifications.index');

    // Platform Connections (admin + manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/settings/platform-connections', [PlatformConnectionSettingsController::class, 'index'])
            ->name('web.platform-connections.index');
        Route::post('/settings/platform-connections', [PlatformConnectionSettingsController::class, 'store'])
            ->name('web.platform-connections.store');
        Route::patch('/settings/platform-connections/{platformConnection}', [PlatformConnectionSettingsController::class, 'update'])
            ->name('web.platform-connections.update');
        Route::delete('/settings/platform-connections/{platformConnection}', [PlatformConnectionSettingsController::class, 'destroy'])
            ->name('web.platform-connections.destroy');

        Route::get(
            '/settings/platform-connections/{platform}/authorize',
            [PlatformConnectionOAuthController::class, 'redirectToProvider']
        )->name('web.platform-connections.oauth.authorize');

        Route::get(
            '/settings/platform-connections/{platform}/callback',
            [PlatformConnectionOAuthController::class, 'handleProviderCallback']
        )->name('web.platform-connections.oauth.callback');
    });

    // Admin-only routes
    Route::middleware('role:admin')->prefix('admin')->name('web.admin.')->group(function () {
        // User management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');

        // Category & benchmark management
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

