<?php

namespace App\Services\Ai\Attachments;

use App\Models\BugAttachment;
use App\Models\MediaItem;
use App\Models\ProjectAttachment;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\Tools\RecordResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Documents already in Sundal that can be given to the assistant: files in
 * the current workspace's media library. The owner sees every one; a
 * manager sees their own uploads and the files on tasks, bugs and projects
 * they can see.
 */
class SundalFiles
{
    private const LIMIT = 100;

    public function __construct(private readonly RecordResolver $resolver) {}

    /** @return array<int, array{id: int, name: string, size: int, extension: string, where: ?string, created_at: ?string}> */
    public function list(User $user, string $search = ''): array
    {
        $media = $this->visible($user)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('file_name', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $where = $this->whereAttached($media->pluck('model_id'));

        return $media->map(fn (Media $m) => [
            'id' => $m->id,
            'name' => $m->file_name,
            'size' => (int) $m->size,
            'extension' => strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)),
            'where' => $where[$m->model_id] ?? null,
            'created_at' => $m->created_at?->toIso8601String(),
        ])->values()->all();
    }

    public function find(User $user, int $mediaId): ?Media
    {
        return $this->visible($user)->whereKey($mediaId)->first();
    }

    public function contents(Media $media): string
    {
        $stream = $media->stream();
        try {
            return (string) stream_get_contents($stream, AttachmentStore::maxBytes() + 1);
        } finally {
            is_resource($stream) && fclose($stream);
        }
    }

    /** Media library files of an allowed type in the user's workspace that they may see. */
    private function visible(User $user): Builder
    {
        $items = MediaItem::query()->where('workspace_id', $user->current_workspace_id)->select('id');

        $query = Media::query()
            ->where('model_type', MediaItem::class)
            ->whereIn('model_id', $items)
            ->where('size', '<=', AttachmentStore::maxBytes())
            ->where(function ($q) {
                foreach (AttachmentStore::extensions() as $extension) {
                    $q->orWhere('file_name', 'like', '%.' . $extension);
                }
            });

        if (AiAccess::role($user) === 'owner') {
            return $query;
        }

        // Managers: their own uploads, and files on records they can see.
        $onRecords = TaskAttachment::query()->whereIn('task_id', $this->resolver->tasks($user)->select('id'))->select('media_item_id')
            ->union(BugAttachment::query()->whereIn('bug_id', $this->resolver->bugs($user)->select('id'))->select('media_item_id'))
            ->union(ProjectAttachment::query()->whereIn('project_id', $this->resolver->projects($user)->select('id'))->select('media_item_id'));

        return $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('model_id', $onRecords));
    }

    /** media item id => "Task: Login page", for the files that hang on a record. */
    private function whereAttached(Collection $itemIds): array
    {
        $where = [];
        foreach (TaskAttachment::with('task:id,title')->whereIn('media_item_id', $itemIds)->get() as $a) {
            $where[$a->media_item_id] ??= __('Task: :title', ['title' => $a->task?->title]);
        }
        foreach (BugAttachment::with('bug:id,title')->whereIn('media_item_id', $itemIds)->get() as $a) {
            $where[$a->media_item_id] ??= __('Bug: :title', ['title' => $a->bug?->title]);
        }
        foreach (ProjectAttachment::with('project:id,title')->whereIn('media_item_id', $itemIds)->get() as $a) {
            $where[$a->media_item_id] ??= __('Project: :title', ['title' => $a->project?->title]);
        }

        return $where;
    }
}
