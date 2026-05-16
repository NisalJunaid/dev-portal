<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __invoke(Request $request, string $section): View
    {
        abort_unless($request->user()->can('view '.$section), 403);

        return view('app.placeholder', [
            'section' => $section,
            'title' => Str::headline($section),
        ]);
    }
}
