<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToWorkspace;

class ZapierHook extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id','created_by','name','url','event','is_active','last_triggered_at','trigger_count'];
    protected $casts = ['is_active'=>'boolean','last_triggered_at'=>'datetime'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }

    public static function fire(string $event, int $workspaceId, array $payload): void
    {
        $hooks = self::where('workspace_id',$workspaceId)
            ->where('event',$event)->where('is_active',true)->get();

        foreach ($hooks as $hook) {
            try {
                \Illuminate\Support\Facades\Http::timeout(5)
                    ->post($hook->url, array_merge($payload, ['event'=>$event,'workspace_id'=>$workspaceId]));
                $hook->increment('trigger_count');
                $hook->update(['last_triggered_at'=>now()]);
            } catch (\Throwable) { /* silent fail */ }
        }
    }
}
