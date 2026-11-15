<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-dashboard', function () {
    \ = App\Models\User::where('email', 'superadmin@taisartownhub.com')->first();
    \ = \->createToken('test')->plainTextToken;
    \ = ['Authorization' => 'Bearer ' . \, 'Accept' => 'application/json'];
    \ = new \Illuminate\Support\Facades\Http::withHeaders(['Authorization' => 'Bearer ' . \, 'Accept' => 'application/json']);
    \ = \->get('http://127.0.0.1:8080/api/admin/dashboard');
    return [
        'status' => \->status(),
        'body' => \->json(),
    ];
});
