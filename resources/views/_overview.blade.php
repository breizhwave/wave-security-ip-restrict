{{-- "Access right now" panel of the admin page; receives $overview (AccessOverview::build() + auto, admin_middleware, write_middleware). --}}
<?php
$m = 'ip-restrict::messages.';
$icons = array('open' => '✔', 'login' => '🔑', 'admin_open' => '⚠', 'restricted' => '✘', 'blocked' => '⛔', 'off' => '—');
$case = $overview['use_case'];
$auto = $overview['auto'];
?>
<section id="access" class="card">
    <div class="case">
        <div class="case-num {{ $case['number'] ? '' : 'none' }}">{{ $case['number'] ? $case['number'] : '?' }}</div>
        <div>
            <div class="case-kicker">{{ trans($m.'case_kicker') }}</div>
            <div class="case-title">{{ trans($m.$case['key'].'_title') }}</div>
            <p class="help">{{ trans($m.$case['key'].'_text') }}</p>
            @foreach ($case['notes'] as $note)
                <p class="help"><span class="tag grey">{{ trans($m.'note') }}</span> {{ trans($m.$note) }}</p>
            @endforeach
            @if ($case['number'])
                <a class="readme" href="{{ \Wave\SecurityIpRestrict\AccessOverview::link($case['number']) }}" target="_blank" rel="noopener">{{ trans($m.'case_readme', array('number' => $case['number'])) }} ↗</a>
            @else
                <a class="readme" href="{{ \Wave\SecurityIpRestrict\AccessOverview::link('cases') }}" target="_blank" rel="noopener">{{ trans($m.'case_readme_all') }} ↗</a>
            @endif
        </div>
    </div>

    @foreach ($overview['warnings'] as $warning)
        <div class="alert {{ $warning['level'] === 'error' ? 'error' : 'warn' }}">
            <strong>{{ trans($m.$warning['key'].'_title') }}</strong>
            {{ trans($m.$warning['key'].'_text', $warning['params']) }}
            @if ($warning['link'])<a href="{{ $warning['link'] }}" target="_blank" rel="noopener">README ↗</a>@endif
        </div>
    @endforeach

    <h3>{{ trans($m.'matrix_title') }}</h3>
    <div class="table">
        <table class="matrix">
            <thead>
                <tr>
                    <th>{{ trans($m.'matrix_who') }}</th>
                    @foreach ($overview['columns'] as $column)
                        <th>
                            {{ trans($m.'col_area_'.$column['key']) }}
                            @if ($column['paths'] !== '')<br><code>{{ $column['paths'] }}</code>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($overview['rows'] as $row)
                    <tr class="{{ $row['mine'] ? 'mine' : '' }}">
                        <th scope="row">
                            {{ trans($m.'row_'.$row['key']) }}
                            @if ($row['mine'])<span class="tag green">{{ trans($m.'tag_you') }}</span>@endif
                            <div class="muted">{{ trans($m.'row_'.$row['key'].'_hint') }}</div>
                        </th>
                        @foreach ($overview['columns'] as $column)
                            <?php $state = $row['cells'][$column['key']]; ?>
                            <td><span class="cell {{ $state }}" title="{{ trans($m.'state_'.$state.'_title') }}"><span aria-hidden="true">{{ $icons[$state] }}</span> {{ trans($m.'state_'.$state) }}</span></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="legend muted">
        @foreach (array('open', 'login', 'admin_open', 'restricted', 'blocked', 'off') as $state)
            <span class="cell {{ $state }}"><span aria-hidden="true">{{ $icons[$state] }}</span> {{ trans($m.'state_'.$state) }}</span> {{ trans($m.'state_'.$state.'_title') }}
        @endforeach
    </p>

    <div class="facts">
        <div class="fact">
            <div class="fact-label">{{ trans($m.'fact_blocking') }}</div>
            @if (! empty($auto['enabled']))
                <div class="fact-value">{{ trans($m.'fact_blocking_on', array('threshold' => (int) $auto['threshold'], 'window' => (int) $auto['window'])) }}</div>
                <div class="muted">{{ (int) $auto['ban'] > 0 ? trans($m.'for_minutes', array('minutes' => (int) $auto['ban'])) : trans($m.'until_unblocked') }} · {{ implode(', ', (array) $auto['types']) }}</div>
            @else
                <div class="fact-value">{{ trans($m.'fact_off') }}</div>
            @endif
        </div>
        <div class="fact">
            <div class="fact-label">{{ trans($m.'fact_admin_middleware') }}</div>
            <div class="fact-value"><code>{{ count($overview['admin_middleware']) ? implode(', ', $overview['admin_middleware']) : '—' }}</code></div>
            <div class="muted">{{ trans($m.'fact_write_middleware') }} <code>{{ count($overview['write_middleware']) ? implode(', ', $overview['write_middleware']) : '—' }}</code></div>
        </div>
    </div>

    <details>
        <summary>{{ trans($m.'routes_title') }}</summary>
        <div class="table">
            <table>
                <thead><tr><th>{{ trans($m.'col_method') }}</th><th>URL</th><th>{{ trans($m.'col_route') }}</th><th>{{ trans($m.'col_action') }}</th></tr></thead>
                <tbody>
                    @foreach (array('index' => 'GET', 'attempts' => 'GET', 'add' => 'POST', 'remove' => 'POST', 'restore' => 'POST', 'deny' => 'POST', 'undeny' => 'POST') as $name => $method)
                        <tr>
                            <td><span class="tag {{ $method === 'GET' ? 'blue' : 'grey' }}">{{ $method }}</span></td>
                            <td>
                                @if ($method === 'GET')
                                    <a href="{{ route('ip-restrict.'.$name) }}"><code>{{ parse_url(route('ip-restrict.'.$name), PHP_URL_PATH) }}</code></a>
                                @else
                                    <code>{{ parse_url(route('ip-restrict.'.$name), PHP_URL_PATH) }}</code>
                                @endif
                            </td>
                            <td><code>ip-restrict.{{ $name }}</code></td>
                            <td class="muted">{{ trans($m.'route_'.$name) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</section>
