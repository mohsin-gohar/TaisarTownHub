<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function index()
    {
        return response()->json(Inquiry::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'fullName' => 'required|string',
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $inquiry = Inquiry::create([
            'id' => 'inq-' . now()->timestamp,
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->input('subject', 'Property Consultation'),
            'message' => $request->message,
            'preferredPropertyOrProject' => $request->preferredPropertyOrProject,
            'budgetRange' => $request->budgetRange,
            'status' => 'Pending',
            'adminNotes' => 'Received via API integration.',
            'source' => $request->input('source', 'React Web Portal'),
        ]);

        return response()->json($inquiry, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $inquiry = Inquiry::findOrFail($id);
        $inquiry->update(['status' => $request->status]);
        return response()->json($inquiry);
    }
}
