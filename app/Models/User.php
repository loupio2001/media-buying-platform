<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sO';

    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'password_reset_token',
        'password_reset_expires',
        'notification_preferences',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = ['password', 'password_reset_token'];

    protected $casts = [
        'role' => UserRole::class,
        'notification_preferences' => 'array',
        'is_active' => 'boolean',
        'password_reset_expires' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function wantsNotification(string $type): bool
    {
        $prefs = $this->notification_preferences ?? [];

        return $prefs[$type] ?? true;
    }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }
}
