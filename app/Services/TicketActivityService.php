<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class TicketActivityService
{
    public function log(Ticket $ticket, string $action, string $description, ?User $user = null, mixed $oldValue = null, mixed $newValue = null): TicketActivity
    {
        return TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user?->id,
            'action' => $action,
            'old_value' => $this->stringValue($oldValue),
            'new_value' => $this->stringValue($newValue),
            'description' => $description,
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }
}
