<?php

namespace App\Services;

use App\Models\Ticket;

class TicketNumberService
{
    public const PREFIX = 'KIEL';

    public function next(): string
    {
        $latestTicketNo = Ticket::query()
            ->where('ticket_no', 'like', self::PREFIX.'-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('ticket_no');

        $nextNumber = $latestTicketNo
            ? ((int) str($latestTicketNo)->after(self::PREFIX.'-')->toString()) + 1
            : 1;

        return sprintf('%s-%06d', self::PREFIX, $nextNumber);
    }
}
