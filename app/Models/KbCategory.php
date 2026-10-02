<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

class KbCategory extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id','name','icon','description','sort_order','created_by'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function articles(): HasMany { return $this->hasMany(KbArticle::class); }
    public function publishedArticles(): HasMany { return $this->hasMany(KbArticle::class)->where('is_published',true); }
}
