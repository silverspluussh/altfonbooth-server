<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        $countries = Country::active()
            ->ordered()
            ->get(['id', 'name', 'iso_code', 'dial_code']);

        return response()->json([
            'status' => true,
            'data' => $countries,
        ]);
    }
}
