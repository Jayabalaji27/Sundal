<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\User;
use App\Services\StorageConfigService;
use App\Services\DynamicStorageService;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
class MediaController extends Controller
{
    public function __construct(private PlanLimitService $planLimitService)
    {
    }
    public function index()
    {
        $user = auth()->user();
        $workspaceId = $user->current_workspace_id;
        
        $query = MediaItem::with('media');
        
        if ($user->type !== 'superadmin' && $workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }
        
        $mediaItems = $query->latest()->get();

        $media = $mediaItems->flatMap(function ($item) use ($user) {
            $mediaCollection = $item->getMedia('images');

            // Filter based on user role in workspace
            if ($user->type === 'superadmin') {
                // Superadmin sees all media
                $filteredMedia = $mediaCollection;
            } elseif ($user->current_workspace_id) {
                // Check user's role in current workspace
                $workspaceMember = \DB::table('workspace_members')
                    ->where('workspace_id', $user->current_workspace_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if ($workspaceMember && $workspaceMember->role === 'owner') {
                    // Owner sees all media from workspace members
                    $workspaceUserIds = \DB::table('workspace_members')
                        ->where('workspace_id', $user->current_workspace_id)
                        ->where('status', 'active')
                        ->pluck('user_id')
                        ->toArray();
                    $filteredMedia = $mediaCollection->filter(function ($media) use ($workspaceUserIds) {
                        return in_array($media->user_id, $workspaceUserIds);
                    });
                } else {
                    // Manager, member, client see only their own media
                    $filteredMedia = $mediaCollection->filter(function ($media) use ($user) {
                        return $media->user_id === $user->id;
                    });
                }
            } else {
                // No workspace - see only own media
                $filteredMedia = $mediaCollection->filter(function ($media) use ($user) {
                    return $media->user_id === $user->id;
                });
            }

            return $filteredMedia->map(function ($media) use ($item) {
                try {
                    $originalUrl = $this->getFullUrl($media->getUrl());
                    $thumbUrl = $originalUrl;

                    try {
                        $thumbUrl = $this->getFullUrl($media->getUrl('thumb'));
                    } catch (\Exception $e) {
                        // If thumb conversion fails, use original
                    }

                    return [
                        'id' => $item->id,
                        'media_id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'url' => $originalUrl,
                        'thumb_url' => $thumbUrl,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                        'user_id' => $media->user_id,
                        'created_at' => $media->created_at,
                    ];
                } catch (\Exception $e) {
                    // Skip media files with unavailable storage disks
                    return null;
                }
            })->filter(); // Remove null entries
        });

        return response()->json($media);
    }

    private function getFullUrl($url)
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $baseUrl = request()->getSchemeAndHttpHost();
        return $baseUrl . $url;
    }

    private function getUserFriendlyError(\Exception $e, $fileName): string
    {
        $message = $e->getMessage();
        $extension = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));

        // Handle media library collection errors
        if (str_contains($message, 'was not accepted into the collection')) {
            if (str_contains($message, 'mime:')) {
                return __('File type not allowed: :extension', ['extension' => $extension]);
            }
            return __('File format not supported: :extension', ['extension' => $extension]);
        }

        // Handle storage errors
        if (str_contains($message, 'storage') || str_contains($message, 'disk')) {
            return __('Storage error: :extension', ['extension' => $extension]);
        }

        // Handle file size errors
        if (str_contains($message, 'size') || str_contains($message, 'large')) {
            return __('File too large: :extension', ['extension' => $extension]);
        }

        // Handle permission errors
        if (str_contains($message, 'permission') || str_contains($message, 'denied')) {
            return __('Permission denied: :extension', ['extension' => $extension]);
        }

        // Generic fallback
        return __('Upload failed: :extension', ['extension' => $extension]);
    }

    public function batchStore(Request $request)
    {
        // Debug: Check user permissions
        $user = auth()->user();

        // Check storage limits using plan limit service
        $totalSize = collect($request->file('files'))->sum(fn($file) => $file->getSize());

        $limitCheck = $this->planLimitService->canUploadFile($user, $totalSize);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message' => __('Storage limit exceeded'),
                'errors' => [$limitCheck['message']]
            ], 422);
        }
        DynamicStorageService::configureDynamicDisks();
        $config = StorageConfigService::getStorageConfig();

        $allowedTypes    = $config['allowed_file_types'] ?? 'jpg,jpeg,png,webp,gif,pdf,doc,docx,zip,rar';
        $normalizedTypes = strtolower($allowedTypes);
        // Value is stored in KB directly (UI label says "Max Upload Size (KB)")
        $maxKb           = (int) ($config['max_file_size_kb'] ?? 2048);
        $maxMbDisplay    = round($maxKb / 1024, 2);

        $validator = \Validator::make($request->all(), [
            'files'   => 'required|array',
            'files.*' => ['required', 'file', 'mimes:' . $normalizedTypes, 'max:' . $maxKb],
        ], [
            'files.*.mimes' => __('Only these file types are allowed: :type', [
                'type' => strtoupper(str_replace(',', ', ', $allowedTypes))
            ]),
            'files.*.max' => __('File size cannot exceed :max MB.', ['max' => $maxMbDisplay]),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message'      => __('File validation failed'),
                'errors'       => $validator->errors()->all(),
                'allowed_types' => $config['allowed_file_types'],
                'max_size_mb'   => $maxMbDisplay,
            ], 422);
        }

        $uploadedMedia = [];
        $errors = [];

        // Override Spatie media-library max_file_size with DB settings before addMedia() calls
        // maxKb is in KB, convert to bytes
        config(['media-library.max_file_size' => $maxKb * 1024]);

        foreach ($request->file('files') as $file) {
            try {
                $mediaItem = MediaItem::create([
                    'name' => $file->getClientOriginalName(),
                    'workspace_id' => auth()->user()->current_workspace_id,
                    'user_id' => auth()->id(),
                ]);

                $media = $mediaItem->addMedia($file)
                    ->toMediaCollection('images');

                $media->user_id = auth()->id();
                $media->save();

                // Update user storage usage
                $this->updateStorageUsage(auth()->user(), $media->size);

                // Force thumbnail generationAdd commentMore actions
                try {
                    $media->getUrl('thumb');
                } catch (\Exception $e) {
                    // Thumbnail generation failed, but continue
                }

                $originalUrl = $this->getFullUrl($media->getUrl());
                $thumbUrl = $originalUrl; // Default to original

                try {
                    $thumbUrl = $this->getFullUrl($media->getUrl('thumb'));
                } catch (\Exception $e) {
                    // If thumb conversion fails, use original
                }

                $uploadedMedia[] = [
                    'id' => $mediaItem->id,
                    'media_id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $originalUrl,
                    'thumb_url' => $thumbUrl,
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                    'user_id' => $media->user_id,
                    'created_at' => $media->created_at,
                ];
            } catch (\Exception $e) {
                if (isset($mediaItem)) {
                    $mediaItem->delete();
                }
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $this->getUserFriendlyError($e, $file->getClientOriginalName())
                ];
            }
        }

        if (count($uploadedMedia) > 0 && empty($errors)) {
            return response()->json([
                'message' => __(':count file(s) uploaded successfully', ['count' => count($uploadedMedia)]),
                'data' => $uploadedMedia
            ]);
        } elseif (count($uploadedMedia) > 0 && !empty($errors)) {
            return response()->json([
                'message' => __(':uploaded uploaded, :failed failed', ['uploaded' => count($uploadedMedia), 'failed' => count($errors)]),
                'data' => $uploadedMedia,
                'errors' => array_column($errors, 'error')
            ]);
        } else {
            return response()->json([
                'message' => __('Upload failed'),
                'errors' => array_column($errors, 'error')
            ], 422);
        }
    }

    // Mirrors download() but returns the file inline (Content-Disposition: inline)
    // instead of forcing a save-as, so the "View" action can open it in a new tab.
    public function view($id)
    {
        $user = auth()->user();
        $query = Media::where('id', $id);

        if ($user->type === 'superadmin') {
            // Superadmin can view any media
        } elseif ($user->current_workspace_id) {
            $workspaceMember = \DB::table('workspace_members')
                ->where('workspace_id', $user->current_workspace_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($workspaceMember && $workspaceMember->role === 'owner') {
                $workspaceUserIds = \DB::table('workspace_members')
                    ->where('workspace_id', $user->current_workspace_id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();
                $query->whereIn('user_id', $workspaceUserIds);
            } else {
                $query->where('user_id', $user->id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        $media = $query->firstOrFail();

        try {
            $filePath = $media->getPath();

            if (!file_exists($filePath)) {
                abort(404, __('File not found'));
            }

            return response()->file($filePath, [
                'Content-Type' => $media->mime_type,
            ]);
        } catch (\Exception $e) {
            abort(404, __('File storage unavailable'));
        }
    }

    public function download($id)
    {
        $user = auth()->user();
        $query = Media::where('id', $id);

        if ($user->type === 'superadmin') {
            // Superadmin can download any media
        } elseif ($user->current_workspace_id) {
            $workspaceMember = \DB::table('workspace_members')
                ->where('workspace_id', $user->current_workspace_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($workspaceMember && $workspaceMember->role === 'owner') {
                // Owner can download media from workspace members
                $workspaceUserIds = \DB::table('workspace_members')
                    ->where('workspace_id', $user->current_workspace_id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();
                $query->whereIn('user_id', $workspaceUserIds);
            } else {
                // Others can download only their own media
                $query->where('user_id', $user->id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        $media = $query->firstOrFail();

        try {
            $filePath = $media->getPath();

            if (!file_exists($filePath)) {
                abort(404, __('File not found'));
            }

            return response()->download($filePath, $media->file_name);
        } catch (\Exception $e) {
            abort(404, __('File storage unavailable'));
        }
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $query = Media::where('id', $id);

        if ($user->type === 'superadmin') {
            // Superadmin can delete any media
        } elseif ($user->current_workspace_id) {
            $workspaceMember = \DB::table('workspace_members')
                ->where('workspace_id', $user->current_workspace_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($workspaceMember && $workspaceMember->role === 'owner') {
                // Owner can delete media from workspace members
                $workspaceUserIds = \DB::table('workspace_members')
                    ->where('workspace_id', $user->current_workspace_id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->toArray();
                $query->whereIn('user_id', $workspaceUserIds);
            } else {
                // Others can delete only their own media
                $query->where('user_id', $user->id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        $media = $query->firstOrFail();
        $mediaItem = $media->model;

        $fileSize = $media->size;

        try {
            $media->delete();
        } catch (\Exception $e) {
            // If storage disk is unavailable, force delete from database
            $media->forceDelete();
        }

        // Update user storage usage
        $this->updateStorageUsage(auth()->user(), -$fileSize);

        // Delete the MediaItem if it has no more media files
        if ($mediaItem && $mediaItem->getMedia()->count() === 0) {
            $mediaItem->delete();
        }

        return response()->json(['message' => __('Media deleted successfully')]);
    }

    private function checkStorageLimit($files)
    {
        $user = auth()->user();
        if ($user->type === 'superadmin') return null;

        $limit = $this->getUserStorageLimit($user);
        if (!$limit) return null;

        $uploadSize = collect($files)->sum('size');
        $currentUsage = $this->getUserStorageUsage($user);

        if (($currentUsage + $uploadSize) > $limit) {
            return response()->json([
                'message' => __('Storage limit exceeded'),
                'errors' => [__('Please delete files or upgrade plan')]
            ], 422);
        }

        return null;
    }

    private function getUserStorageLimit($user)
    {
        if (!isSaasMode()) {
            return null; // No storage limits in non-SaaS mode
        }

        if ($user->type === 'company' && $user->plan) {
            return $user->plan->storage_limit * 1024 * 1024;
        }

        if ($user->created_by) {
            $company = User::find($user->created_by);
            if ($company && $company->plan) {
                return $company->plan->storage_limit * 1024 * 1024;
            }
        }

        return null;
    }

    private function getUserStorageUsage($user)
    {
        if ($user->type === 'company') {
            return User::where('created_by', $user->id)
                ->orWhere('id', $user->id)
                ->sum('storage_limit');
        }

        if ($user->created_by) {
            $company = User::find($user->created_by);
            if ($company) {
                return User::where('created_by', $company->id)
                    ->orWhere('id', $company->id)
                    ->sum('storage_limit');
            }
        }

        return $user->storage_limit;
    }

    private function updateStorageUsage($user, $size)
    {
        // Storage usage is now tracked via media table, no need to update user field
    }
}