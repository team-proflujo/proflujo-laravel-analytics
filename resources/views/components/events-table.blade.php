@if ($rows === [])
    <p class="pa-empty">No event records match the selected filters.</p>
@else
    <div class="pa-table-wrap">
        <table class="pa-table">
            <thead>
                <tr><th>Event</th><th>Identifier</th><th>Category</th><th>User ID</th><th>Occurred at</th><th>Properties</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td><code>{{ $row['event'] }}</code></td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['user_id'] ?? '—' }}</td>
                        <td>{{ $row['occurred_at'] }}</td>
                        <td>
                            @if ($row['properties'] !== null)
                                <details><summary>View</summary><pre>{{ $row['properties'] }}</pre></details>
                            @else
                                <span class="pa-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <nav class="pa-pager" aria-label="Pagination">
        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Newer</a>
        @else
            <span class="pa-muted">&larr; Newer</span>
        @endif
        <span class="pa-muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ number_format($paginator->total()) }} events</span>
        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Older &rarr;</a>
        @else
            <span class="pa-muted">Older &rarr;</span>
        @endif
    </nav>
@endif
