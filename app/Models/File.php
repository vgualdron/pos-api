<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    public $table = "files";
    protected $appends = ['full_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'url',
        'model_id',
        'model_name',
        'status',
        'latitude',
        'longitude',
        'type',
        'extension',
        'observation',
        'registered_by',
        'registered_date',
        'reviewed_by',
        'reviewed_date',
    ];

    public function getFullUrlAttribute()
    {
        $base = rtrim((string) config('filesystems.url_files', env('URL_FILES', '')), '/');
        $path = ltrim((string) $this->url, '/');

        if ($base === '' || $path === '') {
            return $path;
        }

        return "{$base}/{$path}";
    }
}
