<?php

namespace App\Services\Discovery;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, explainable interest-based recommendations.
 *
 * Primary signal: shared interests. Score is Jaccard similarity scaled
 * to a percentage:
 *
 *     score = shared / (mine + theirs - shared) * 100
 *
 * Computed in SQL (single aggregate pass over user_interests) so candidate
 * retrieval is bounded and recommendations never load the user table into
 * PHP memory to rank it.
 */
class RecommendationService
{
    public function __construct(private readonly DiscoveryEligibilityService $eligibility) {}

    /**
     * Ranked candidates for the viewer. Ordered by match score desc,
     * shared interest count desc, then user id asc — a deterministic
     * tie-breaker; ordering is never random.
     *
     * @return array<int, array{
     *     user: User,
     *     match: array{score: float, shared_count: int, unique_count: int},
     *     shared_interests: Collection,
     * }>
     */
    public function recommendedFor(User $viewer, int $limit = 8): array
    {
        $viewerInterestIds = self::viewerInterestIds($viewer);
        $viewerTotal = count($viewerInterestIds);

        // No interests = no signal. Return empty rather than a misleading score.
        if ($viewerTotal === 0) {
            return [];
        }

        $sharedSql = $this->interestCountSql($viewerInterestIds);
        $uniqueSql = '(select count(*) from user_interests candidate_total where candidate_total.user_id = users.id)';
        $divisor = "({$viewerTotal} + {$uniqueSql} - {$sharedSql})";
        // Jaccard scaled to a percentage; 100.0 forces floating-point math
        // (Postgres `/` on integers would truncate). NULLIF guards zero-division.
        $scoreSql = sprintf('(%s * 100.0) / NULLIF(%s, 0)', $sharedSql, $divisor);

        $rows = $this->eligibility->eligibleQuery($viewer, forRecommendations: true)
            ->select('users.id')
            ->selectRaw(sprintf('%s as shared_count', $sharedSql))
            ->selectRaw(sprintf('%s as unique_count', $uniqueSql))
            ->selectRaw(sprintf('%s as match_score', $scoreSql))
            ->orderByDesc('match_score')
            ->orderByDesc('shared_count')
            ->orderBy('users.id')
            ->limit($limit)
            ->get();

        return $this->hydrate($rows, $viewerInterestIds);
    }

    /**
     * Interests of candidate users, annotated with a `viewer_selected`
     * marker marking shared interests. One query for the whole page
     * (no N+1), bounded by page size.
     *
     * @return array<int, array<int, object{id: int, name: string, slug: string, category: string, viewer_selected: bool}>>
     */
    public function annotatedInterests(array $userIds, array $viewerInterestIds = []): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('user_interests as ui')
            ->join('interests', 'interests.id', '=', 'ui.interest_id')
            ->whereIn('ui.user_id', $userIds)
            ->where('interests.is_active', true)
            ->orderBy('interests.name')
            ->get(['ui.user_id', 'interests.id', 'interests.name', 'interests.slug', 'interests.category']);

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row->user_id] ??= [];
            $byUser[$row->user_id][] = (object) [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'category' => $row->category,
                'viewer_selected' => in_array((int) $row->id, $viewerInterestIds, true),
            ];
        }

        return $byUser;
    }

    /**
     * Sorting helper used by the people explorer for "relevance":
     * a shared-interest-count expression, or null when the viewer has
     * no interests (no signal to rank by).
     */
    public function sharedCountExpression(User $viewer): ?string
    {
        $ids = self::viewerInterestIds($viewer);

        return count($ids) ? $this->interestCountSql($ids) : null;
    }

    /** @return array<int, int> */
    public static function viewerInterestIds(User $viewer): array
    {
        return $viewer->interests()->orderByPivot('created_at')->pluck('interests.id')->map(static fn ($id) => (int) $id)->all();
    }

    /** @return array<int, array{user: User, match: array{score: float, shared_count: int, unique_count: int}, shared_interests: Collection}> */
    private function hydrate(Collection $rows, array $viewerInterestIds): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $interestsByUser = $this->annotatedInterests($rows->pluck('id')->all(), $viewerInterestIds);
        $users = User::query()->with('profile')->whereIn('id', $rows->pluck('id')->all())->get()->keyBy(static fn (User $user) => $user->getKey());

        return $rows->map(static function (object $row) use ($users, $interestsByUser): array {
            $user = $users[(int) $row->id];
            $all = collect($interestsByUser[$row->id] ?? []);
            $shared = $all->filter(static fn (object $interest): bool => $interest->viewer_selected)->values();

            // Annotate the user model so the shared discovery resource can
            // render interests (with viewer_selected flags) and match data.
            $user->discovery_interests = $all;
            $user->match_summary = ['shared' => $shared->isEmpty() ? null : ['score' => (float) $row->match_score, 'shared_count' => (int) $row->shared_count, 'shared_interests' => $shared]];

            return [
                'user' => $user,
                'match' => $user->match_summary['shared'],
                'shared_interests' => $shared,
            ];
        })->all();
    }

    /** Parameterized-safe count subquery (interest ids are integers from the DB). */
    private function interestCountSql(array $interestIds): string
    {
        return sprintf(
            '(select count(*) from user_interests shared where shared.user_id = users.id and shared.interest_id in (%s))',
            implode(',', array_map(static fn (int $id): string => (string) $id, $interestIds)),
        );
    }
}
