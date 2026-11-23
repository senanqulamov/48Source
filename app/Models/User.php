<?php

namespace App\Models;

use App\Support\Perm;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['role' => 'buyer', 'status' => 'active', 'theme' => 'light', 'locale' => 'en', 'store_id' => null, 'two_factor' => false, 'email_notices' => true];

    protected $fillable = ['name', 'email', 'password', 'role', 'supplier_id', 'store_id', 'locale', 'two_factor', 'email_notices', 'status', 'department', 'phone', 'theme', 'email_verified_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['two_factor' => 'boolean', 'email_notices' => 'boolean', 'email_verified_at' => 'datetime', 'last_login_at' => 'datetime', 'password' => 'hashed'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function allowed(string $module, string $ability = 'V'): bool
    {
        return Perm::allows($this, $module, $ability);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Buyer-side roles that run sourcing. */
    public function isSourcing(): bool
    {
        return in_array($this->role, ['admin', 'buyer'], true);
    }

    public function roleLabel(): string
    {
        return Perm::ROLES[$this->role] ?? $this->role;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    public function home(): string
    {
        return route('dashboard');
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** May this user buy in the marketplace? Stores sell, they do not buy. */
    public function canShop(): bool
    {
        return in_array($this->role, ['admin', 'buyer', 'supplier'], true) && $this->allowed('marketplace', 'C');
    }
}
