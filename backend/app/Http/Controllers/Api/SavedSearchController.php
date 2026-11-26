<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Support\PropertySearchFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Per-account marketplace saved searches.
 *
 * Ownership is enforced from the authenticated user only — a saved-search id
 * from another account is reported as 404 (enumeration-safe), and no agency
 * id is accepted or trusted.
 */
class SavedSearchController extends Controller
{
    use AuthorizesApi;

    private const MAX_PER_USER = 20;

    /** GET /api/saved-searches — newest first, bounded. */
    public function index(Request $request)
    {
        $searches = SavedSearch::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(self::MAX_PER_USER)
            ->get()
            ->map(fn (SavedSearch $s) => $this->present($s));

        return response()->json([
            'success' => true,
            'saved_searches' => $searches,
            'limit' => self::MAX_PER_USER,
        ]);
    }

    /** POST /api/saved-searches — save the current filter set. */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validatePayload($request);
        $data['filters'] = $this->normaliseFilters($data['filters']);
        $this->assertPriceRange($data['filters']);

        // Lock the owner row while checking the per-user cap. This prevents
        // concurrent requests from creating more than MAX_PER_USER searches.
        $search = DB::transaction(function () use ($user, $data): ?SavedSearch {
            \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (SavedSearch::where('user_id', $user->id)->count() >= self::MAX_PER_USER) {
                return null;
            }

            return SavedSearch::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'filters' => $data['filters'],
                'frequency' => $data['frequency'] ?? 'daily',
                'alerts_enabled' => $data['alerts_enabled'] ?? true,
                'last_run_at' => now(),
            ]);
        }, 3);

        if (!$search) {
            return response()->json([
                'success' => false,
                'message' => 'You can save up to ' . self::MAX_PER_USER . ' searches. Delete one to add another.',
            ], 422);
        }

        $presented = $this->present($search);

        return response()->json([
            'success' => true,
            'saved_search' => $presented,
            // Retain the earlier response key while clients transition.
            'search' => $presented,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $search = $this->findOwned($request, $id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:120',
            'filters' => 'sometimes|required|array',
            'filters.search' => 'nullable|string|max:120',
            'filters.type' => 'nullable|string|max:80',
            'filters.phase' => 'nullable|string|max:40',
            'filters.sector' => 'nullable|string|max:80',
            'filters.block' => 'nullable|string|max:80',
            'filters.minPrice' => 'nullable|integer|min:0|max:100000000000',
            'filters.maxPrice' => 'nullable|integer|min:0|max:100000000000',
            'filters.sizeSqYards' => 'nullable|integer|min:1|max:1000000',
            'filters.possessionOnly' => 'nullable|boolean',
            'filters.listingPurpose' => 'nullable|string|in:sale',
            'filters.bedrooms' => 'nullable|integer|min:0|max:20',
            'filters.featured' => 'nullable|boolean',
            'frequency' => 'sometimes|required|string|in:' . implode(',', SavedSearch::FREQUENCIES),
            'alerts_enabled' => 'sometimes|required|boolean',
        ]);

        if (array_key_exists('name', $data)) {
            $search->name = strip_tags($data['name']);
        }
        if (array_key_exists('filters', $data)) {
            $filters = $this->normaliseFilters($data['filters']);
            $this->assertPriceRange($filters);
            $search->filters = $filters;
        }
        if (array_key_exists('frequency', $data)) {
            $search->frequency = $data['frequency'];
        }
        if (array_key_exists('alerts_enabled', $data)) {
            $search->alerts_enabled = (bool) $data['alerts_enabled'];
            // Re-enabling alerts should not email a stale backlog: start the
            // next window at the moment alerts are switched back on.
            if ($search->alerts_enabled) {
                $search->last_run_at = now();
            }
        }

        $search->save();

        return response()->json([
            'success' => true,
            'message' => 'Saved search updated.',
            'saved_search' => $this->present($search),
        ]);
    }

    /** DELETE /api/saved-searches/{id} */
    public function destroy(Request $request, string $id)
    {
        $search = $this->findOwned($request, $id);
        $search->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved search deleted.',
        ]);
    }

    /* ------------------------------ internals ------------------------------ */

    private function findOwned(Request $request, string $id): SavedSearch
    {
        $search = SavedSearch::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$search) {
            abort(404, 'Saved search not found.');
        }

        return $search;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|min:2|max:120',
            'filters' => 'required|array',
            'filters.search' => 'nullable|string|max:120',
            'filters.type' => 'nullable|string|max:80',
            'filters.phase' => 'nullable|string|max:40',
            'filters.sector' => 'nullable|string|max:80',
            'filters.block' => 'nullable|string|max:80',
            'filters.minPrice' => 'nullable|integer|min:0|max:100000000000',
            'filters.maxPrice' => 'nullable|integer|min:0|max:100000000000',
            'filters.sizeSqYards' => 'nullable|integer|min:1|max:1000000',
            'filters.possessionOnly' => 'nullable|boolean',
            'filters.listingPurpose' => 'nullable|string|in:sale',
            'filters.bedrooms' => 'nullable|integer|min:0|max:20',
            'filters.featured' => 'nullable|boolean',
            'frequency' => 'nullable|string|in:' . implode(',', SavedSearch::FREQUENCIES),
            'alerts_enabled' => 'nullable|boolean',
        ]);
    }

    /**
     * Keep only allow-listed, meaningful values. This prevents arbitrary JSON
     * from being persisted and keeps alert matching deterministic.
     */
    private function normaliseFilters(array $filters): array
    {
        $out = [];

        foreach (PropertySearchFilters::KEYS as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];

            if (is_string($value)) {
                $value = trim(strip_tags($value));
            }

            if ($value === null || $value === '' || $value === 'All') {
                continue;
            }

            if (in_array($key, ['possessionOnly', 'featured'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                if ($value) {
                    $out[$key] = true;
                }
                continue;
            }

            if (in_array($key, ['minPrice', 'maxPrice', 'sizeSqYards', 'bedrooms'], true)) {
                $value = (int) $value;
                if ($value > 0) {
                    $out[$key] = $value;
                }
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    private function assertPriceRange(array $filters): void
    {
        $min = (int) ($filters['minPrice'] ?? 0);
        $max = (int) ($filters['maxPrice'] ?? 0);

        if ($min > 0 && $max > 0 && $min > $max) {
            throw ValidationException::withMessages([
                'filters.minPrice' => ['Minimum price cannot be greater than maximum price.'],
            ]);
        }
    }

    private function present(SavedSearch $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'filters' => $s->filters ?? [],
            'frequency' => $s->frequency,
            'alerts_enabled' => (bool) $s->alerts_enabled,
            'summary' => $s->describeFilters(),
            'last_run_at' => $s->last_run_at?->toIso8601String(),
            'last_notified_at' => $s->last_notified_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
