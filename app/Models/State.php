<?php

namespace App\Models;

use Altwaireb\World\Models\State as Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class State extends Model
{

    protected $guarded = [];
    /**
     * Relación con el modelo Country
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function citiesList()
    {
        return $this->hasMany(City::class);
    }
    public function getCanDeleteAttribute(): bool
    {
        return $this->users()->doesntExist()
            && $this->citiesList()->doesntExist();
    }
}
