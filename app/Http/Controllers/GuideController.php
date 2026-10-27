<?php

namespace App\Http\Controllers;

use App\Support\Guides;
use Illuminate\View\View;

/**
 * Guides gratuits (publics, indexés) : la liste et chaque guide (App\Support\Guides).
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', ['guides' => Guides::all()]);
    }

    public function show(string $guide): View
    {
        return view('guides.show', ['guide' => Guides::find($guide) ?? abort(404)]);
    }
}
