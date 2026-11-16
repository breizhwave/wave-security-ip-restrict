{{-- Plain Blade only (no @csrf, @class, @js…): it must compile on Laravel 5.1 as well as 13. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trans('ip-restrict::messages.attempts_title') }}</title>
    @include('ip-restrict::_style')
</head>
<body>
<div class="ipr">
    <h1>{{ trans('ip-restrict::messages.admin_title') }}</h1>
    @include('ip-restrict::_nav', array('current' => 'attempts'))
    <p class="help">{{ trans('ip-restrict::messages.attempts_help') }}</p>

    @if ($ok)
        <div class="alert ok">{{ $ok }}</div>
    @endif
    @if ($error)
        <div class="alert error">{{ $error }}</div>
    @endif

    <div class="alert info">
        @if (! empty($auto['enabled']))
            {{ trans('ip-restrict::messages.auto_on', array('threshold' => (int) $auto['threshold'], 'window' => (int) $auto['window'], 'ban' => (int) $auto['ban'] > 0 ? trans('ip-restrict::messages.for_minutes', array('minutes' => (int) $auto['ban'])) : trans('ip-restrict::messages.until_unblocked'), 'types' => implode(', ', (array) $auto['types']))) }}
        @else
            {{ trans('ip-restrict::messages.auto_off') }}
        @endif
        {{ trans('ip-restrict::messages.your_address') }} <code class="my-ip">{{ $myIp }}</code>
    </div>

    @if (count($days) === 0)
        <p class="muted">{{ trans('ip-restrict::messages.no_attempts') }}</p>
    @else
        <div class="days">
            @foreach ($days as $day)
                <a href="{{ route('ip-restrict.attempts', array('date' => $day['date'])) }}" class="{{ $day['date'] === $date ? 'current' : '' }}">{{ date('d/m/Y', strtotime($day['date'])) }}</a>
            @endforeach
        </div>

        <h2>{{ trans('ip-restrict::messages.by_ip_title', array('date' => date('d/m/Y', strtotime($date)))) }}</h2>
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>{{ trans('ip-restrict::messages.col_address') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_count') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_types') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_seen') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_state') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary as $row)
                        <tr class="{{ $row['deny_entry'] ? 'blocked' : '' }}">
                            <td><a href="{{ route('ip-restrict.attempts', array('date' => $date, 'ip' => $row['ip'])) }}"><code>{{ $row['ip'] }}</code></a></td>
                            <td>{{ $row['count'] }}</td>
                            <td>
                                @foreach ($row['types'] as $type => $n)
                                    <span class="tag {{ $type === 'not_allowed' ? 'grey' : 'red' }}">{{ $type }} × {{ $n }}</span>
                                @endforeach
                            </td>
                            <td class="muted">{{ date('H:i:s', strtotime($row['first'])) }} → {{ date('H:i:s', strtotime($row['last'])) }}</td>
                            <td>
                                @if ($row['deny_entry'])
                                    <span class="tag red">{{ trans('ip-restrict::messages.state_blocked') }}</span>
                                    <span class="muted">{{ empty($row['deny_entry']['expires_at']) ? trans('ip-restrict::messages.until_unblocked') : trans('ip-restrict::messages.until', array('date' => date('d/m/Y H:i', strtotime($row['deny_entry']['expires_at'])))) }}</span>
                                @elseif ($row['trusted'])
                                    <span class="tag green">{{ trans('ip-restrict::messages.state_trusted') }}</span>
                                @elseif ($row['blocked'])
                                    <span class="tag grey">{{ trans('ip-restrict::messages.state_was_blocked') }}</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($row['deny_entry'] && $row['deny_entry']['source'] === 'env')
                                    <span class="muted" title="{{ trans('ip-restrict::messages.deny_env_title') }}">.env</span>
                                @elseif ($row['deny_entry'])
                                    <form method="POST" action="{{ route('ip-restrict.undeny') }}" class="inline">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="ip" value="{{ $row['deny_entry']['ip'] }}">
                                        <input type="hidden" name="from" value="attempts">
                                        <input type="hidden" name="date" value="{{ $date }}">
                                        <button type="submit" class="small secondary">{{ trans('ip-restrict::messages.btn_unblock') }}</button>
                                    </form>
                                @elseif ($row['ip'] !== $myIp)
                                    <form method="POST" action="{{ route('ip-restrict.deny') }}" class="inline"
                                          onsubmit="return confirm({{ json_encode(trans('ip-restrict::messages.confirm_deny', array('ip' => $row['ip']))) }})">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="ip" value="{{ $row['ip'] }}">
                                        <input type="hidden" name="label" value="{{ trans('ip-restrict::messages.blocked_from_log', array('date' => $date)) }}">
                                        <input type="hidden" name="from" value="attempts">
                                        <input type="hidden" name="date" value="{{ $date }}">
                                        <select name="minutes" aria-label="{{ trans('ip-restrict::messages.field_duration') }}">
                                            <option value="60">{{ trans('ip-restrict::messages.duration_hour') }}</option>
                                            <option value="1440">{{ trans('ip-restrict::messages.duration_day') }}</option>
                                            <option value="10080">{{ trans('ip-restrict::messages.duration_week') }}</option>
                                            <option value="0">{{ trans('ip-restrict::messages.until_unblocked') }}</option>
                                        </select>
                                        <button type="submit" class="small danger">{{ trans('ip-restrict::messages.btn_deny') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ trans('ip-restrict::messages.no_attempts_day') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2>{{ trans('ip-restrict::messages.detail_title') }}</h2>
        @if ($ipFilter !== '')
            <p class="help">
                {{ trans('ip-restrict::messages.filtered_on', array('ip' => $ipFilter)) }}
                <a href="{{ route('ip-restrict.attempts', array('date' => $date)) }}">{{ trans('ip-restrict::messages.show_all') }}</a>
            </p>
        @endif
        @if ($total > count($entries))
            <p class="muted">{{ trans('ip-restrict::messages.latest_only', array('shown' => count($entries), 'total' => $total)) }}</p>
        @endif
        <div class="table">
            <table>
                <thead>
                    <tr>
                        <th>{{ trans('ip-restrict::messages.col_time') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_address') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_type') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_detail') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_request') }}</th>
                        <th>{{ trans('ip-restrict::messages.col_user_agent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="{{ ! empty($entry['blocked']) ? 'blocked' : '' }}">
                            <td class="muted">{{ date('H:i:s', strtotime($entry['at'])) }}</td>
                            <td><code>{{ $entry['ip'] }}</code></td>
                            <td>
                                {{ $entry['type'] }}
                                @if (! empty($entry['blocked']))<span class="tag red">{{ trans('ip-restrict::messages.state_blocked') }}</span>@endif
                                @if (! empty($entry['trusted']))<span class="tag green">{{ trans('ip-restrict::messages.state_trusted') }}</span>@endif
                            </td>
                            <td class="wrap">{{ $entry['detail'] !== '' ? $entry['detail'] : '—' }}</td>
                            <td class="wrap"><code>{{ $entry['method'] }} {{ $entry['path'] }}</code></td>
                            <td class="wrap muted">{{ $entry['user_agent'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ trans('ip-restrict::messages.no_attempts_day') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
</body>
</html>
