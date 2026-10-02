<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToWorkspace;

class Tax extends BaseModel
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'name',
        'rate',
        'workspace_id',
    ];

    protected $casts = [
        'rate' => 'float',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}