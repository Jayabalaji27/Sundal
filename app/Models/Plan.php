<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plan_type',
        'sort_order',
        'legacy_addon_access',
        'price',
        'yearly_price',
        'duration',
        'description',
        'max_users_per_workspace',
        'max_clients_per_workspace',
        'max_managers_per_workspace',
        'max_projects_per_workspace',
        'workspace_limit',
        'enable_chatgpt',
        'storage_limit',
        'is_trial',
        'trial_day',
        'is_plan_enable',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'legacy_addon_access' => 'boolean',
        'price' => 'float',
        'yearly_price' => 'float',
    ];

    /**
     * Base (default 'base') plans grant the core module set; addon plans are
     * the purchasable bundle unlocking AI + Knowledge Base + Chat + Meetings.
     */
    public function isAddon(): bool
    {
        return $this->plan_type === 'addon';
    }

    public function scopeBase($query)
    {
        return $query->where('plan_type', 'base');
    }

    public function scopeAddon($query)
    {
        return $query->where('plan_type', 'addon');
    }
    
    /**
     * Get the default plan
     *
     * @return Plan|null
     */
    public static function getDefaultPlan()
    {
        return self::where('is_default', true)->first();
    }
    
    /**
     * Check if the plan is the default plan
     *
     * @return bool
     */
    public function isDefault()
    {
        return (bool) $this->is_default;
    }
    
    /**
     * Get the price based on billing cycle
     *
     * @param string $cycle 'monthly' or 'yearly'
     * @return float
     */
    public function getPriceForCycle($cycle = 'monthly')
    {
        if ($cycle === 'yearly' && $this->yearly_price) {
            return $this->yearly_price;
        }
        
        return $this->price;
    }
    
    /**
     * Get users subscribed to this plan
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    /**
     * Assign default plan to user when their plan expires
     *
     * @param User $user
     * @return bool
     */
    public static function assignDefaultPlanToUser($user)
    {
        $defaultPlan = self::getDefaultPlan();
        
        if ($defaultPlan) {
            $user->update([
                'plan_id' => $defaultPlan->id,
                'plan_is_active' => 1,
                'plan_expire_date' => null
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if plan has unlimited workspaces
     */
    public function hasUnlimitedWorkspaces(): bool
    {
        return !$this->workspace_limit;
    }
    
    /**
     * Check if plan has unlimited users per workspace
     */
    public function hasUnlimitedUsersPerWorkspace(): bool
    {
        return !$this->max_users_per_workspace;
    }
    
    /**
     * Check if plan has unlimited clients per workspace
     */
    public function hasUnlimitedClientsPerWorkspace(): bool
    {
        return !$this->max_clients_per_workspace;
    }
    
    /**
     * Check if plan has unlimited managers per workspace
     */
    public function hasUnlimitedManagersPerWorkspace(): bool
    {
        return !$this->max_managers_per_workspace;
    }
    
    /**
     * Check if plan has unlimited projects per workspace
     */
    public function hasUnlimitedProjectsPerWorkspace(): bool
    {
        return !$this->max_projects_per_workspace;
    }
    
    /**
     * Check if plan has unlimited storage
     */
    public function hasUnlimitedStorage(): bool
    {
        return !$this->storage_limit;
    }
    
    /**
     * Get storage limit in bytes
     */
    public function getStorageLimitInBytes(): ?int
    {
        return $this->storage_limit ? $this->storage_limit * 1024 * 1024 * 1024 : null;
    }
    
    /**
     * Get plan features as array
     */
    public function getFeatures(): array
    {
        return [
            'workspaces' => $this->workspace_limit ?: __('Unlimited'),
            'users_per_workspace' => $this->max_users_per_workspace ?: __('Unlimited'),
            'clients_per_workspace' => $this->max_clients_per_workspace ?: __('Unlimited'),
            'managers_per_workspace' => $this->max_managers_per_workspace ?: __('Unlimited'),
            'projects_per_workspace' => $this->max_projects_per_workspace ?: __('Unlimited'),
            'storage' => $this->storage_limit ? $this->storage_limit . ' ' . __('GB') : __('Unlimited'),
            'chatgpt' => $this->enable_chatgpt ? __('Enabled') : __('Disabled'),
        ];
    }
}