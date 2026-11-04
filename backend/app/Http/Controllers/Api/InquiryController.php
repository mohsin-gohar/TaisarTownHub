<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryController extends Controller
{
    use AuthorizesApi;

    /**
     * List inquiries with filtering.
     */
    public function index(Request $request)
    {
        $this->requireAny($request, ['view_inquiries']);

        $query = Inquiry::query();

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Mask CNIC in responses
        $inquiries = $query->latest()->get();
        foreach ($inquiries as $inquiry) {
            // Ensure CNIC is masked if present
            if ($inquiry->cnic) {
                $inquiry->cnic = $this->maskCnic($inquiry->cnic);
            }
        }

        return response()->json($inquiries);
    }

    /**
     * Store a new inquiry with validation.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fullName' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:2000',
            'email' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'preferredPropertyOrProject' => 'nullable|string|max:255',
            'budgetRange' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:50',
        ]);

        // Sanitize input
        $data['fullName'] = strip_tags($data['fullName']);
        $data['message'] = strip_tags($data['message']);

        $record = [
            'id' => 'inq-' . Str::uuid(),
            'fullName' => $data['fullName'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'subject' => $data['subject'] ?? 'Property Consultation',
            'message' => $data['message'],
            'preferredPropertyOrProject' => $data['preferredPropertyOrProject'] ?? null,
            'budgetRange' => $data['budgetRange'] ?? null,
            'status' => 'Pending',
            'adminNotes' => 'Received via the public website.',
            'source' => $data['source'] ?? 'React Web Portal',
            'submittedAt' => now()->format('Y-m-d H:i'),
        ];
        BusinessRecord::put('inquiries', $record['id'], $record);

        Inquiry::updateOrCreate(['id' => $record['id']], [
            'fullName' => $record['fullName'],
            'email' => $record['email'],
            'phone' => $record['phone'],
            'subject' => $record['subject'],
            'message' => $record['message'],
            'preferredPropertyOrProject' => $record['preferredPropertyOrProject'],
            'budgetRange' => $record['budgetRange'],
            'status' => 'Pending',
            'adminNotes' => $record['adminNotes'],
            'source' => $record['source'],
        ]);

        return response()->json($record, 201);
    }

    /**
     * Update inquiry status.
     */
    public function updateStatus(Request $request, $id)
    {
        $this->requireAny($request, ['view_inquiries']);

        $request->validate([
            'status' => 'required|string|in:Pending,Contacted,In Progress,Resolved',
        ]);

        $inquiry = Inquiry::findOrFail($id);
        $inquiry->update(['status' => $request->status]);

        return response()->json($inquiry);
    }

    private function maskCnic(string $cnic): string
    {
        $clean = preg_replace('/[^0-9]/', '', $cnic);
        if (strlen($clean) < 7) {
            return str_repeat('*', strlen($clean));
        }
        return substr($clean, 0, 5) . str_repeat('*', strlen($clean) - 9) . substr($clean, -4);
    }
}