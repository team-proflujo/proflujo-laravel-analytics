<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Proflujo\Analytics\Support\DateRange;
use Proflujo\Analytics\Support\EventDefinitionRegistry;
use Proflujo\Analytics\Support\InstanceIdentifier;
use Throwable;

final class TelegramService
{
    private const int MAX_MESSAGE_LENGTH = 4000; // Telegram hard limit is 4096

    public function __construct(
        private readonly SummaryService $summaries,
        private readonly AggregationService $aggregation,
        private readonly EventDefinitionRegistry $registry,
        private readonly InstanceIdentifier $instance,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('analytics.enabled') && (bool) config('analytics.telegram.enabled');
    }

    /** Daily = the single day `$date`; weekly = the 7 days ending on `$date`. */
    public function periodFor(string $period, ?string $date = null): DateRange
    {
        $end = $date !== null ? DateRange::day($date) : DateRange::today()->subDay();

        return $period === 'weekly'
            ? DateRange::make($end->subDays(6), $end)
            : DateRange::make($end);
    }

    public function sendSummary(string $period, ?string $date = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $range = $this->periodFor($period, $date);

        // Idempotent; guarantees the report is complete even if a scheduled run was missed.
        $this->aggregation->aggregate($range->from, $range->to);

        return $this->send($this->buildMessage($period, $range));
    }

    public function buildMessage(string $period, DateRange $range): string
    {
        $summary = $this->summaries->summary($range);
        $label = $period === 'weekly' ? 'Weekly Summary' : 'Daily Summary';
        $dates = $period === 'weekly'
            ? $range->from->format('j F Y').' - '.$range->to->format('j F Y')
            : $range->from->format('j F Y');

        $lines = [
            'Proflujo Analytics - '.$this->instance->name(),
            '',
            $label,
            ($period === 'weekly' ? 'Period: ' : 'Date: ').$dates,
            '',
            'Total Events: '.number_format($summary['total']),
        ];

        $grouped = [];

        foreach ($summary['events'] as $event => $count) {
            $grouped[$this->registry->category($event)][$this->registry->name($event)] = $count;
        }

        ksort($grouped);

        // Uncategorized last.
        if (isset($grouped[EventDefinitionRegistry::UNCATEGORIZED])) {
            $uncategorized = $grouped[EventDefinitionRegistry::UNCATEGORIZED];
            unset($grouped[EventDefinitionRegistry::UNCATEGORIZED]);
            $grouped[EventDefinitionRegistry::UNCATEGORIZED] = $uncategorized;
        }

        foreach ($grouped as $category => $events) {
            ksort($events);
            $lines[] = '';
            $lines[] = (string) $category;

            foreach ($events as $name => $count) {
                $lines[] = '- '.$name.': '.number_format($count);
            }
        }

        if ($summary['total'] === 0) {
            $lines[] = '';
            $lines[] = 'No events were recorded in this period.';
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    public function chunk(string $message): array
    {
        $chunks = [];
        $current = '';

        foreach (explode("\n", $message) as $line) {
            $line = mb_substr($line, 0, self::MAX_MESSAGE_LENGTH);

            if ($current !== '' && mb_strlen($current) + mb_strlen($line) + 1 > self::MAX_MESSAGE_LENGTH) {
                $chunks[] = $current;
                $current = $line;

                continue;
            }

            $current = $current === '' ? $line : $current."\n".$line;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function send(string $message): bool
    {
        $token = (string) config('analytics.telegram.bot_token');
        $chatId = (string) config('analytics.telegram.chat_id');
        $timeout = (int) config('analytics.telegram.timeout', 10);

        try {
            foreach ($this->chunk($message) as $text) {
                $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))
                    ->retry(2, 500, throw: false)
                    ->asJson()
                    ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text,
                        'disable_web_page_preview' => true,
                    ]);

                if ($response->failed()) {
                    Log::warning('Analytics Telegram summary failed.', [
                        'status' => $response->status(),
                        'description' => $this->scrub((string) $response->json('description', ''), $token),
                    ]);

                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Analytics Telegram summary failed.', [
                'exception' => $e::class,
                'message' => $this->scrub($e->getMessage(), $token),
            ]);

            return false;
        }
    }

    private function scrub(string $text, string $token): string
    {
        return $token === '' ? $text : str_replace($token, '[redacted]', $text);
    }
}
