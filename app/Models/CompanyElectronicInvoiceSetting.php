<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class CompanyElectronicInvoiceSetting extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'api_url',
        'client_id',
        'client_secret',
        'username',
        'password',
        'is_enabled',
        'access_token',
        'token_expires_at',
        'tested_at',
        'last_error',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'token_expires_at' => 'datetime',
        'tested_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
        'password',
        'access_token',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hasClientSecret(): bool
    {
        return filled($this->attributes['client_secret'] ?? null);
    }

    public function hasPassword(): bool
    {
        return filled($this->attributes['password'] ?? null);
    }

    public function getClientSecretAttribute($value): ?string
    {
        return $this->decryptSecret($value);
    }

    public function setClientSecretAttribute($value): void
    {
        $this->attributes['client_secret'] = $this->encryptSecret($value);
    }

    public function getPasswordAttribute($value): ?string
    {
        return $this->decryptSecret($value);
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $this->encryptSecret($value);
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $this->decryptSecret($value);
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $this->encryptSecret($value);
    }

    public function baseUrl(): string
    {
        $url = trim((string) $this->api_url);
        $url = rtrim($url, '/');
        $url = preg_replace('#/oauth/token$#i', '', $url) ?? $url;
        $url = preg_replace('#/v2(?:/.*)?$#i', '', $url) ?? $url;

        return rtrim($url, '/');
    }

    protected function encryptSecret($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }

    protected function decryptSecret($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Fallback for values stored with Laravel's encrypted cast (serialize-based)
            try {
                return decrypt($value);
            } catch (\Throwable $e2) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
    }
}
