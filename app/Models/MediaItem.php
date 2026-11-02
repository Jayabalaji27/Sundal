<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\StorageConfigService;

class MediaItem extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    // workspace_id and user_id must be fillable: the Media Library only lists a
    // workspace's own items, so an upload saved without them never showed up again.
    protected $fillable = ['name', 'description', 'workspace_id', 'user_id'];
    
    protected $appends = ['url', 'thumb_url', 'mime_type'];
    
    protected $visible = ['id', 'name', 'description', 'url', 'thumb_url', 'mime_type', 'created_at', 'updated_at'];
    
    public function getUrlAttribute()
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->getUrl() : null;
    }
    
    public function getMimeTypeAttribute()
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->mime_type : null;
    }
    
    public function getThumbUrlAttribute()
    {
        $media = $this->getFirstMedia('images');
        // Only images get a thumbnail; a PDF's "thumb" URL would point at nothing.
        return $media ? $media->getUrl($media->hasGeneratedConversion('thumb') ? 'thumb' : '') : $this->getUrlAttribute();
    }
    
    public function toArray()
    {
        $array = parent::toArray();
        // Ensure appended attributes are always included
        $array['url'] = $this->url;
        $array['thumb_url'] = $this->thumb_url;
        return $array;
    }

    public function registerMediaCollections(): void
    {
        $config = StorageConfigService::getStorageConfig();
        $allowedExtensions = array_map('trim', explode(',', strtolower($config['allowed_file_types'])));
        // Same limit the upload endpoint validates against (stored in KB).
        $maxSizeBytes = ($config['max_file_size_kb'] ?? 2048) * 1024;
        
        $this->addMediaCollection('images')
            ->acceptsFile(function ($file) use ($allowedExtensions, $maxSizeBytes) {
                // Check file extension
                $fileName = $file->name ?? $file->getFilename();
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($extension, $allowedExtensions)) {
                    return false;
                }
                
                // Check file size
                $fileSize = $file->size ?? filesize($file->getPathname());
                if ($fileSize > $maxSizeBytes) {
                    return false;
                }
                
                return true;
            })
            ->useDisk(StorageConfigService::getActiveDisk());
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('images')
            ->nonQueued();
    }
}