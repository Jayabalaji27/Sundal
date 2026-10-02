<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'user_id',
    ];

    public function notificationTemplateLangs(): HasMany
    {
        return $this->hasMany(NotificationTemplateLang::class, 'parent_id');
    }
}