<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PackageCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->center_id) && Auth::check()) {
                $model->center_id = Auth::user()->center_id;
            }
        });

        // Seguridad extra: aunque se intente borrar por código, si está usada no se borra
        static::deleting(function ($model) {
            if ($model->packages()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'No se puede eliminar una categoría que ya está usada en pedidos.',
                ]);
            }
        });
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->packages()->doesntExist();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeMyCenter($query)
    {
        return $query->where('package_categories.center_id', Auth::user()?->center_id ?? -1);
    }
}
