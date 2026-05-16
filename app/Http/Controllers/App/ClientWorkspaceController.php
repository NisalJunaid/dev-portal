<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Contracts\View\View;

class ClientWorkspaceController extends Controller
{
    public function show(Client $client): View
    {
        return view('app.client-workspace', [
            'client' => $client,
        ]);
    }
}
