<?php

namespace App\Events;

use App\Models\ReportImportOperation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReportImportOperation $operation,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('imports.'.$this->operation->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImportStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->operation->id,
            'status' => $this->operation->status,
            'orders' => $this->operation->orders,
            'income' => $this->operation->income,
            'error' => $this->operation->status === 'failed'
                ? $this->operation->error_message
                : null,
        ];
    }
}
