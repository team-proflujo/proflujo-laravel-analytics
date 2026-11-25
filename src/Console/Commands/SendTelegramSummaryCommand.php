<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Proflujo\Analytics\Services\TelegramService;

final class SendTelegramSummaryCommand extends Command
{
    protected $signature = 'analytics:telegram-summary
        {--period=daily : daily or weekly}
        {--date= : Last local date (Y-m-d) of the period; defaults to yesterday}';

    protected $description = 'Send an analytics summary to Telegram';

    public function handle(TelegramService $telegram): int
    {
        $period = $this->option('period');

        if (! in_array($period, ['daily', 'weekly'], true)) {
            $this->components->error('The --period option must be "daily" or "weekly".');

            return self::INVALID;
        }

        if (! $telegram->enabled()) {
            $this->components->warn('Telegram reporting is disabled (analytics.telegram.enabled).');

            return self::SUCCESS;
        }

        $date = $this->option('date');

        if ($telegram->sendSummary($period, is_string($date) ? $date : null)) {
            $this->components->info('Telegram summary sent.');

            return self::SUCCESS;
        }

        $this->components->error('Telegram summary could not be sent. See the application log.');

        return self::FAILURE;
    }
}
