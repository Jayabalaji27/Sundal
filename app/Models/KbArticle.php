<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

class KbArticle extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id','kb_category_id','title','content','is_published','views','created_by'];
    protected $casts = ['is_published' => 'boolean'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function category(): BelongsTo { return $this->belongsTo(KbCategory::class,'kb_category_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function attachments(): HasMany { return $this->hasMany(KbAttachment::class,'kb_article_id'); }
}
