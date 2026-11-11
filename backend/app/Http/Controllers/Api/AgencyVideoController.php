<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyVideo;
use App\Models\BusinessRecord;
use App\Models\VideoComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyVideoController extends Controller
{
    use AuthorizesApi;

    /**
     * List agency videos with filtering.
     */
    public function index(Request $request)
    {
        $query = AgencyVideo::with('comments');

        if ($request->filled('phase') && $request->phase !== 'All') {
            $query->where('phase', $request->phase);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Show a single video.
     */
    public function show($id)
    {
        $video = AgencyVideo::with('comments')->findOrFail($id);
        $video->increment('views');

        return response()->json($video);
    }

    /**
     * Store a new video with validation.
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['upload_media', 'manage_media']);

        $data = $request->validate([
            'id' => 'nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'videoUrl' => 'required|url|max:2048',
            'thumbnailUrl' => 'nullable|url|max:2048',
            'agencyId' => 'nullable|string|max:50',
            'agencyName' => 'required|string|max:255',
            'dealerName' => 'nullable|string|max:255',
            'dealerAvatar' => 'nullable|string|max:500',
            'phase' => 'nullable|string|max:50',
            'sector' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:20',
            'tags' => 'nullable|array',
        ]);

        $data['id'] = $data['id'] ?? 'vid-' . Str::uuid();
        $data['likes'] = 0;
        $data['views'] = 0;

        // Sanitize
        $data['title'] = strip_tags($data['title']);
        $data['description'] = strip_tags($data['description'] ?? '');

        $video = AgencyVideo::create($data);

        return response()->json($video, 201);
    }

    /**
     * Like a video.
     *
     * Requires authentication — likes are attributed to the signed-in account
     * (one like per user per video), never to anonymous client input.
     */
    public function like(Request $request, $id)
    {
        $user = $request->user();

        $record = BusinessRecord::where('collection', 'videos')->where('record_id', $id)->first();
        if ($record) {
            $payload = $record->payload ?? [];
            $likedBy = array_map('strval', $payload['likedBy'] ?? []);
            $uid = 'u:' . $user->id;

            if (in_array($uid, $likedBy, true)) {
                // Idempotent: already liked by this user
                return response()->json([
                    'likes' => (int) ($payload['likes'] ?? 0),
                    'message' => 'Already liked',
                    'liked' => true,
                ]);
            }

            $likedBy[] = $uid;
            // Cap the stored id list so the payload cannot grow unbounded
            if (count($likedBy) > 5000) {
                $likedBy = array_slice($likedBy, -5000);
            }
            $payload['likedBy'] = $likedBy;
            $payload['likes'] = (int) ($payload['likes'] ?? 0) + 1;
            $record->payload = $payload;
            $record->save();

            return response()->json([
                'likes' => $payload['likes'],
                'message' => 'Liked successfully',
                'liked' => true,
            ]);
        }

        $video = AgencyVideo::findOrFail($id);

        // Dedupe via the like table when present, otherwise best-effort increment
        $alreadyLiked = \App\Models\VideoLike::where('agency_video_id', $video->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyLiked) {
            return response()->json([
                'likes' => $video->likes,
                'message' => 'Already liked',
                'liked' => true,
            ]);
        }

        [$like, $created] = \App\Models\VideoLike::firstOrCreate([
            'agency_video_id' => $video->id,
            'user_id' => $user->id,
        ]);

        if ($created) {
            $video->increment('likes');
        }

        return response()->json([
            'likes' => $video->fresh()->likes,
            'message' => 'Liked successfully',
            'liked' => true,
        ]);
    }

    /**
     * Add a comment to a video.
     *
     * Requires authentication. The display name and dealer flag come from the
     * server-side account — clients can no longer impersonate a dealer or
     * another person.
     */
    public function comment(Request $request, $id)
    {
        $user = $request->user();

        $data = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = strip_tags($data['comment']);
        $userName = strip_tags($user->name);
        $isDealer = in_array($user->role, ['dealer', 'admin', 'super_admin'], true)
            || $user->memberships()->where('status', 'active')->exists();

        $stored = BusinessRecord::where('collection', 'videos')->where('record_id', $id)->first();
        if ($stored) {
            $payload = $stored->payload ?? [];
            $row = [
                'id' => 'comm-' . Str::uuid(),
                'userName' => $userName,
                'comment' => $comment,
                'createdAt' => now()->toDateTimeString(),
                'isDealer' => $isDealer,
                'userId' => $user->id,
            ];
            $payload['comments'] = array_merge([$row], $payload['comments'] ?? []);
            $stored->payload = $payload;
            $stored->save();
            return response()->json($row, 201);
        }

        $video = AgencyVideo::findOrFail($id);

        $row = VideoComment::create([
            'id' => 'comm-' . Str::uuid(),
            'agency_video_id' => $video->id,
            'userName' => $userName,
            'comment' => $comment,
            'isDealer' => $isDealer,
            'user_id' => $user->id,
        ]);

        return response()->json($row, 201);
    }
}