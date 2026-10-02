<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    protected $fillable = [
        'workspace_id','name','description','system_prompt',
        'greeting_message','kb_category_ids','model','is_active','created_by'
    ];
    protected $casts = [
        'is_active'       => 'boolean',
        'kb_category_ids' => 'array',
    ];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
