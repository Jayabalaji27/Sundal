<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\Concerns\BelongsToWorkspace;

class ApiKey extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id','user_id','name','key','key_prefix','is_active','last_used_at','expires_at'];
    protected $casts = ['is_active'=>'boolean','last_used_at'=>'datetime','expires_at'=>'datetime'];
    protected $hidden = ['key'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function generate(): array
    {
        $raw    = 'swt_' . Str::random(40);
        $hashed = hash('sha256', $raw);
        $prefix = substr($raw, 0, 12);
        return ['raw' => $raw, 'hashed' => $hashed, 'prefix' => $prefix];
    }

    public static function findByRaw(string $raw): ?self
    {
        return self::where('key', hash('sha256', $raw))->where('is_active', true)->first();
    }
}
