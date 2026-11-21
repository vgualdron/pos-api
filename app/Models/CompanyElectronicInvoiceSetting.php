<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'client_secret' => 'encrypted',
        'password' => 'encrypted',
        'access_token' => 'encrypted',
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
        return filled($this->client_secret);
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function baseUrl(): string
    {
        $url = trim((string) $this->api_url);
        $url = rtrim($url, '/');
        // Allow pasting full endpoint URLs by mistake
        $url = preg_replace('#/oauth/token$#i', '', $url) ?? $url;
        $url = preg_replace('#/v2(?:/.*)?$#i', '', $url) ?? $url;
        $url = rtrim($url, '/');

        return $url;
    }
}

