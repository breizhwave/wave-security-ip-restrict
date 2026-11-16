{{-- Tabs of the admin pages; receives $current (index or attempts). --}}
<nav class="tabs">
    <a href="{{ route('ip-restrict.index') }}" class="{{ $current === 'index' ? 'current' : '' }}">{{ trans('ip-restrict::messages.tab_lists') }}</a>
    <a href="{{ route('ip-restrict.attempts') }}" class="{{ $current === 'attempts' ? 'current' : '' }}">{{ trans('ip-restrict::messages.tab_attempts') }}</a>
</nav>
