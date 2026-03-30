<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CampaignListController;
use App\Http\Controllers\CampaignPageController;
use App\Http\Controllers\PlatformConnectionOAuthController;
use App\Http\Controllers\PlatformConnectionSettingsController;
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
    Route::get('/campaigns', CampaignListController::class)->name('web.campaigns.index');
    Route::get('/campaigns/{campaign}/trend.csv', [CampaignPageController::class, 'exportTrendCsv'])->name('web.campaigns.trend.csv');
    Route::get('/campaigns/{campaign}', CampaignPageController::class)->name('web.campaigns.show');

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

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
