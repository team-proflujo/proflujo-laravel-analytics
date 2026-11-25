<?php

namespace Workbench\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a user and 30 days of demo events.
     */
    public function run(): void
    {
        UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $events = ['student.created', 'student.updated', 'payment.completed', 'report.downloaded', 'inventory.stock.adjusted'];

        foreach (range(0, 29) as $daysAgo) {
            foreach ($events as $i => $event) {
                foreach (range(1, random_int(0, 6 + $i)) as $n) {
                    AnalyticsEvent::query()->create([
                        'event_name' => $event,
                        'user_id' => (string) random_int(1, 5),
                        'properties' => ['seed' => true, 'n' => $n],
                        'occurred_at' => CarbonImmutable::now('UTC')->subDays($daysAgo)->subMinutes(random_int(0, 1000))->format('Y-m-d H:i:s.u'),
                        'created_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u'),
                    ]);
                }
            }
        }
    }
}
