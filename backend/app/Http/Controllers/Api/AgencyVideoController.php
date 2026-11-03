<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyVideo;
use App\Models\VideoComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyVideoController extends Controller
{
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
     */
    public function like($id)
    {
        $video = AgencyVideo::findOrFail($id);
        $video->increment('likes');

        return response()->json(['likes' => $video->likes, 'message' => 'Liked successfully']);
    }

    /**
     * Add a comment to a video.
     */
    public function comment(Request $request, $id)
    {
        $data = $request->validate([
            'userName' => 'required|string|max:100',
            'comment' => 'required|string|max:1000',
            'isDealer' => 'nullable|boolean',
        ]);

        $video = AgencyVideo::findOrFail($id);

        // Sanitize comment
        $data['comment'] = strip_tags($data['comment']);
        $data['userName'] = strip_tags($data['userName']);

        $comment = VideoComment::create([
            'id' => 'comm-' . Str::uuid(),
            'agency_video_id' => $video->id,
            'userName' => $data['userName'],
            'comment' => $data['comment'],
            'isDealer' => $data['isDealer'] ?? false,
        ]);

        return response()->json($comment, 201);
    }
}