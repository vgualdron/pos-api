<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'document_type',
        'document_number',
        'address',
        'phone',
        'email',
        'status',
        'configuration',
    ];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function electronicInvoiceSetting()
    {
        return $this->hasOne(CompanyElectronicInvoiceSetting::class);
    }
}

