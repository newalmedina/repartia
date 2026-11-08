<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /** Lista plana de permisos: ['packages.view_own', 'users.view', ...] */
    public function permissionList(): array
    {
        return collect($this->permissions ?? [])->flatten()->filter()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionList(), true);
    }

    /** Un rol asignado a usuarios no se puede borrar */
    public function getCanDeleteAttribute(): bool
    {
        return $this->users()->doesntExist();
    }
}
