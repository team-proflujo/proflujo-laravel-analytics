<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Support\DateRange;

final class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is governed by the configured analytics.dashboard.middleware.
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'events' => ['nullable', 'array', 'max:100'],
            'events.*' => ['string', 'max:'.AnalyticsEventData::MAX_NAME_LENGTH, 'regex:'.AnalyticsEventData::NAME_PATTERN],
            'category' => ['nullable', 'string', 'max:128'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /** @return array<int, Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$from, $to] = $this->bounds();

                if ($to->lessThan($from)) {
                    $validator->errors()->add('to', 'The end date must not be before the start date.');

                    return;
                }

                $max = (int) config('analytics.dashboard.max_date_range_days', 365);

                if ((int) $from->diffInDays($to) + 1 > $max) {
                    $validator->errors()->add('to', "The date range may not exceed {$max} days.");
                }
            },
        ];
    }

    public function range(): DateRange
    {
        return DateRange::make(...$this->bounds());
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function bounds(): array
    {
        $to = $this->filled('to') ? DateRange::day($this->string('to')->toString()) : DateRange::today();
        $default = (int) config('analytics.dashboard.default_range_days', 30);
        $from = $this->filled('from')
            ? DateRange::day($this->string('from')->toString())
            : $to->subDays($default - 1)->startOfDay();

        return [$from, $to];
    }

    /** @return list<string> */
    public function selectedEvents(): array
    {
        $events = $this->input('events', []);

        return is_array($events) ? array_values(array_filter($events, is_string(...))) : [];
    }

    public function selectedCategory(): ?string
    {
        return $this->filled('category') ? $this->string('category')->toString() : null;
    }

    public function pageNumber(): int
    {
        return max(1, $this->integer('page', 1));
    }
}
