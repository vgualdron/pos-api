<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $with = ['company'];
    protected $fillable = ['company_id', 'name', 'address', 'phone', 'email', 'print_api_url', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function stations()
    {
        return $this->hasMany(Station::class);
    }

    public function turns()
    {
        return $this->hasMany(Turn::class);
    }
}
