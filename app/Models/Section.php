<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $guarded = [];

    protected $appends = ['name'];

    public function getNameAttribute()
    {
        $locale = auth()->check() ? (auth()->user()->lang ?? app()->getLocale()) : app()->getLocale();
        $nameColumn = $locale === 'ar' ? 'ar_name' : 'en_name';
        return $this->attributes[$nameColumn] ?? $this->attributes['en_name'];
    }


}
