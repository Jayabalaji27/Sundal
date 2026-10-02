<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbAttachment extends Model
{
    protected $fillable = ['kb_article_id','name','path','mime_type','size','uploaded_by'];

    public function article(): BelongsTo { return $this->belongsTo(KbArticle::class,'kb_article_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class,'uploaded_by'); }

    public function getSizeFormattedAttribute(): string
    {
        if ($this->size < 1024) return $this->size . ' B';
        if ($this->size < 1048576) return round($this->size / 1024, 1) . ' KB';
        return round($this->size / 1048576, 1) . ' MB';
    }
}
