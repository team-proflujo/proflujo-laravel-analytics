@extends('analytics::layout')

@section('title', 'Dashboard')

@section('content')
    @include('analytics::components.filters')

    <section class="pa-cards" aria-label="Overview">
        <div class="pa-card"><span class="pa-muted">Total events</span><strong>{{ number_format($charts['total']) }}</strong></div>
        <div class="pa-card"><span class="pa-muted">Event types</span><strong>{{ number_format($charts['event_types']) }}</strong></div>
        <div class="pa-card"><span class="pa-muted">Period ({{ $timezone }})</span><strong class="pa-small">{{ $range->from->format('j M Y') }} – {{ $range->to->format('j M Y') }}</strong></div>
    </section>

    <h2>Events over time</h2>
    @if ($charts['charts'] === [])
        <p class="pa-empty">No events were recorded in this period.</p>
    @else
        @if ($charts['limited'])
            <p class="pa-notice">Showing the top {{ $charts['limit'] }} of {{ $charts['event_types'] }} event types. Use the event filter to choose others.</p>
        @endif
        <div class="pa-charts">
            @foreach ($charts['charts'] as $i => $chart)
                <article class="pa-card pa-chart">
                    <h3>{{ $chart['name'] }}</h3>
                    <p class="pa-muted"><code>{{ $chart['event'] }}</code> · {{ $chart['category'] }} · {{ number_format($chart['total']) }} total</p>
                    <div class="pa-canvas"><canvas data-chart="{{ $i }}" role="img" aria-label="Daily count of {{ $chart['name'] }}"></canvas></div>
                </article>
            @endforeach
        </div>
        <script type="application/json" id="analytics-charts">{!! json_encode(['labels' => $charts['labels'], 'charts' => $charts['charts']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <h2>Event records</h2>
    @include('analytics::components.events-table')
@endsection
