{{-- Menu of the admin pages; receives $current (index or attempts) and $nav (allowed, denied, today). --}}
<?php $index = route('ip-restrict.index'); $here = $current === 'index'; ?>
<nav class="tabs" aria-label="{{ trans('ip-restrict::messages.admin_title') }}">
    <a href="{{ $here ? '' : $index }}#access" class="{{ $here ? 'current' : '' }}"><span aria-hidden="true">🛡</span> {{ trans('ip-restrict::messages.tab_access') }}</a>
    <a href="{{ $here ? '' : $index }}#allow"><span aria-hidden="true">✔</span> {{ trans('ip-restrict::messages.tab_allowed') }} <span class="count">{{ isset($nav['allowed']) ? $nav['allowed'] : '' }}</span></a>
    <a href="{{ $here ? '' : $index }}#deny"><span aria-hidden="true">⛔</span> {{ trans('ip-restrict::messages.tab_denied') }} <span class="count">{{ isset($nav['denied']) ? $nav['denied'] : '' }}</span></a>
    <a href="{{ route('ip-restrict.attempts') }}" class="{{ $current === 'attempts' ? 'current' : '' }}"><span aria-hidden="true">⚠</span> {{ trans('ip-restrict::messages.tab_attempts') }}
        @if (! empty($nav['today']))<span class="count hot">{{ trans('ip-restrict::messages.today_count', array('count' => $nav['today'])) }}</span>@endif
    </a>
</nav>
