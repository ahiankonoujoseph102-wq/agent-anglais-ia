<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CreditsController extends Controller
{
    public function __invoke(): View
    {
        return view('credits', [
            'credits' => config('avatar.credits', []),
            'modelName' => config('avatar.model.name'),
            'photos' => config('photos', []),
        ]);
    }
}
