<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    public function index()
    {
        return response()->json(Dealer::latest()->get());
    }

    public function show($id)
    {
        $dealer = Dealer::findOrFail($id);
        return response()->json($dealer);
    }

    public function store(Request $request)
    {
        $dealer = Dealer::create($request->all());
        return response()->json($dealer, 201);
    }

    public function update(Request $request, $id)
    {
        $dealer = Dealer::findOrFail($id);
        $dealer->update($request->all());
        return response()->json($dealer);
    }

    public function destroy($id)
    {
        $dealer = Dealer::findOrFail($id);
        $dealer->delete();
        return response()->json(['message' => 'Dealer deleted successfully']);
    }
}
