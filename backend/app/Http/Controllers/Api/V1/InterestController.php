<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InterestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterestController
{
    public function index(): JsonResponse { return response()->json(['interests' => InterestResource::collection(\App\Models\Interest::where('is_active', true)->orderBy('category')->orderBy('name')->get())]); }
    public function mine(Request $request): JsonResponse { return response()->json(['interests' => InterestResource::collection($request->user()->interests()->orderBy('name')->get())]); }
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['interest_ids' => ['required', 'array', 'max:30'], 'interest_ids.*' => ['integer', 'exists:interests,id']]);
        $request->user()->interests()->sync($data['interest_ids']);
        return response()->json(['interests' => InterestResource::collection($request->user()->interests()->orderBy('name')->get())]);
    }
}
