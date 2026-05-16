<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $clientCount = $user->isClientUser()
            ? Client::whereKey($user->client_id)->count()
            : Client::count();

        $openTicketCount = $user->isClientUser()
            ? Ticket::where('client_id', $user->client_id)->where('status', '!=', Ticket::STATUS_REJECTED)->count()
            : Ticket::where('status', '!=', Ticket::STATUS_REJECTED)->count();

        return view('app.dashboard', [
            'clientCount' => $clientCount,
            'openTicketCount' => $openTicketCount,
            'workspaceLabel' => $user->isClientUser() ? $user->client?->name : 'Kiel global workspace',
        ]);
    }
}
