<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

final class InstanceIdentifier
{
    private ?string $resolved = null;

    public function id(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $configured = config('analytics.instance.id');

        if (is_string($configured) && $configured !== '') {
            return $this->resolved = $configured;
        }

        $path = storage_path('app/analytics/instance-id');

        try {
            if (File::exists($path)) {
                $stored = trim(File::get($path));

                if ($stored !== '') {
                    return $this->resolved = $stored;
                }
            }

            $id = (string) Str::uuid();
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $id, true);

            return $this->resolved = $id;
        } catch (Throwable) {
            // Read-only filesystem: derive a stable, installation-specific id from the app key.
            return $this->resolved = $this->derived();
        }
    }

    public function name(): string
    {
        $name = config('analytics.instance.name');

        return is_string($name) && $name !== '' ? $name : 'Laravel';
    }

    /** @return array{id: string, name: string, timezone: string, enabled: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'timezone' => DateRange::timezone(),
            'enabled' => (bool) config('analytics.enabled'),
        ];
    }

    private function derived(): string
    {
        $key = config('app.key');

        return hash('sha256', 'proflujo-analytics|'.(is_string($key) ? $key : '').'|'.base_path());
    }
}
