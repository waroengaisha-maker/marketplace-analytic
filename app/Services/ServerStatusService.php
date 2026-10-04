<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ServerStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $path = config('server-status.snapshot_path');

        if (! is_string($path) || ! File::isFile($path)) {
            return [
                'available' => false,
                'reason' => 'Server status snapshot is not available.',
            ];
        }

        $data = json_decode(File::get($path), true);

        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Invalid server status snapshot.');
        }

        $generatedAt = isset($data['generated_at'])
            ? Carbon::parse($data['generated_at'])
            : null;

        $data['available'] = true;
        $data['stale'] = $generatedAt === null
            || $generatedAt->lt(now()->subSeconds((int) config('server-status.stale_after_seconds', 120)));
        $data['generated_at_human'] = $generatedAt?->diffForHumans();

        return $data;
    }
}
