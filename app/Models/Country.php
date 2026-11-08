<?php

namespace App\Models;

use Altwaireb\World\Models\Country as Model;
use Illuminate\Database\Eloquent\Builder;

class Country extends Model
{
    protected $guarded = [];
    /**
     * Scope para filtrar solo los países activos
     */


    protected $casts = [
        'translations' => 'array',
    ];

    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }


    public function citiesList()
    {
        return $this->hasMany(City::class);
    }
    public function statesList()
    {
        return $this->hasMany(State::class);
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->users()->doesntExist()
            && $this->statesList()->doesntExist()
            && $this->citiesList()->doesntExist();
    }
}
