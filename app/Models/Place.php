<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $with = ['company'];
    protected $fillable = ['company_id', 'name', 'address', 'phone', 'email', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stations()
    {
        return $this->hasMany(Station::class);
    }

    public function electronicInvoiceSetting()
    {
        return $this->hasOne(PlaceElectronicInvoiceSetting::class);
    }

    public function posResolutionSetting()
    {
        return $this->hasOne(PlacePosResolutionSetting::class);
    }
}

