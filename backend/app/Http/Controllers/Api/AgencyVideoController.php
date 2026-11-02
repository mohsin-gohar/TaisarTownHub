<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyVideo;
use App\Models\VideoComment;
use Illuminate\Http\Request;

class AgencyVideoController extends Controller
{
    public function index(Request $request)
    {
        $query = AgencyVideo::with('comments');

        if ($request->filled('phase') && $request->phase !== 'All') {
            $query->where('phase', $request->phase);
        }

        return response()->json($query->latest()->get());
    }

    public function show($id)
    {
        $video = AgencyVideo::with('comments')->findOrFail($id);
        $video->increment('views');
        return response()->json($video);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|string',
            'title' => 'required|string',
            'videoUrl' => 'required|string',
            'agencyName' => 'required|string',
        ]);

        $video = AgencyVideo::create($request->all());
        return response()->json($video, 201);
    }

    public function like($id)
    {
        $video = AgencyVideo::findOrFail($id);
        $video->increment('likes');
        return response()->json(['likes' => $video->likes, 'message' => 'Liked successfully']);
    }

    public function comment(Request $request, $id)
    {
        $request->validate([
            'userName' => 'required|string',
            'comment' => 'required|string',
        ]);

        $video = AgencyVideo::findOrFail($id);

        $comment = VideoComment::create([
            'id' => 'comm-' . now()->timestamp . '-' . rand(100, 999),
            'agency_video_id' => $video->id,
            'userName' => $request->userName,
            'comment' => $request->comment,
            'isDealer' => $request->boolean('isDealer', false)
        ]);

        return response()->json($comment, 201);
    }
}
