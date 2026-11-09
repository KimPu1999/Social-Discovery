<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Privacy-aware post keyword search (full-text on pgsql, LIKE on sqlite). */
class PostSearchService
{
    /** Search published posts the viewer is allowed to see for `term`. */
    public function search(User $viewer, string $term, int $perPage = 10): LengthAwarePaginator
    {
        $term = trim($term);

        if ($term === '') {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $isPg = DB::connection()->getDriverName() === 'pgsql';

        return Post::query()
            ->with(['user.profile', 'media', 'hashtags', 'shareOf', 'shareOf.originalPost'])
            ->visibleTo($viewer)
            ->eligibleFor($viewer)
            ->where(static function ($query) use ($term, $isPg): void {
                if ($isPg) {
                    $query->whereRaw("to_tsvector('simple', coalesce(body, '')) @@ plainto_tsquery('simple', ?)", [$term])
                        ->orWhereRaw('body ilike ?', ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%']);

                    return;
                }

                $query->whereRaw('body like ?', ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%']);
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
