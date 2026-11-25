<form method="GET" action="{{ route('analytics.dashboard') }}" class="pa-filters">
    @if (isset($errors) && $errors->any())
        <div class="pa-error" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif
    <label>From <input type="date" name="from" value="{{ $filters['from'] }}"></label>
    <label>To <input type="date" name="to" value="{{ $filters['to'] }}"></label>
    <label>Category
        <select name="category">
            <option value="">All</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </label>
    <label>Events
        <select name="events[]" multiple size="3">
            @foreach ($charts['event_options'] as $identifier => $name)
                <option value="{{ $identifier }}" @selected(in_array($identifier, $filters['events'], true))>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit">Apply</button>
    <a href="{{ route('analytics.dashboard') }}" class="pa-link">Reset</a>
</form>
