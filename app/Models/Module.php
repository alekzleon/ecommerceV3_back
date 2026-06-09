<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
     protected $fillable = [
        'name',
        'display_name',
        'description',
        'group_key',
        'sort_order',
        'route_name',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_module')
            ->withTimestamps();
    }
}
