{{-- Plain Blade only (no @csrf, @class, @js…): it must compile on Laravel 5.1 as well as 13. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trans('ip-restrict::messages.admin_title') }}</title>
    @include('ip-restrict::_style')
</head>
<body>
<div class="ipr">
    <h1>{{ trans('ip-restrict::messages.admin_title') }}</h1>
    @include('ip-restrict::_nav', array('current' => 'index'))
    <p class="help">{{ trans('ip-restrict::messages.admin_help') }}</p>

    @if ($ok)
        <div class="alert ok">{{ $ok }}</div>
    @endif
    @if ($error)
        <div class="alert error">{{ $error }}</div>
    @endif

    <div class="alert {{ $active ? 'info' : 'error' }}">
        {{ trans($active ? 'ip-restrict::messages.active' : 'ip-restrict::messages.inactive') }}
        @if ($active)
            {{ count($scope) > 0 ? trans('ip-restrict::messages.scope_paths', array('paths' => implode(', ', $scope))) : trans('ip-restrict::messages.scope_all') }}
        @endif
        {{ trans('ip-restrict::messages.your_address') }} <code class="my-ip">{{ $myIp }}</code>
        ({{ trans($myIpAllowed ? 'ip-restrict::messages.mine_allowed' : 'ip-restrict::messages.mine_not_listed') }}).
        @if ($myIpDenied)
            <strong>{{ trans('ip-restrict::messages.mine_denied') }}</strong>
        @endif
    </div>

    <div class="table">
        <table>
            <thead>
                <tr>
                    <th>{{ trans('ip-restrict::messages.col_ip') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_label') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_source') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_added') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <?php $mine = ! $entry['hidden'] && \Wave\SecurityIpRestrict\IpList::covers($entry['ip'], $myIp); ?>
                    <tr class="{{ $entry['hidden'] ? 'hidden' : '' }} {{ $mine ? 'mine' : '' }}">
                        <td>
                            <code>{{ $entry['ip'] }}</code>
                            @if ($mine)<span class="tag green">{{ trans('ip-restrict::messages.tag_mine') }}</span>@endif
                        </td>
                        <td>{{ $entry['label'] !== '' ? $entry['label'] : '—' }}</td>
                        <td>
                            @if ($entry['source'] === 'env')
                                <span class="tag grey">{{ trans('ip-restrict::messages.source_env') }}</span>
                            @else
                                <span class="tag blue">{{ trans('ip-restrict::messages.source_added') }}</span>
                            @endif
                            @if ($entry['hidden'])<span class="tag red">{{ trans('ip-restrict::messages.tag_hidden') }}</span>@endif
                        </td>
                        <td class="muted">
                            @if (isset($entry['added_at']))
                                {{ trans('ip-restrict::messages.added_on', array('date' => date('d/m/Y H:i', strtotime($entry['added_at'])), 'by' => isset($entry['added_by']) ? $entry['added_by'] : '?')) }}
                            @endif
                            @if (isset($entry['hidden_at']))
                                {{ trans('ip-restrict::messages.hidden_on', array('date' => date('d/m/Y H:i', strtotime($entry['hidden_at'])))) }}
                            @endif
                        </td>
                        <td class="actions">
                            @if ($entry['hidden'])
                                <form method="POST" action="{{ route('ip-restrict.restore') }}" class="inline">
                                    {!! csrf_field() !!}
                                    <input type="hidden" name="ip" value="{{ $entry['ip'] }}">
                                    <button type="submit" class="small secondary">{{ trans('ip-restrict::messages.btn_restore') }}</button>
                                </form>
                            @elseif ($entry['protected'])
                                <span class="muted" title="{{ trans('ip-restrict::messages.protected_title') }}">{{ trans('ip-restrict::messages.protected_tag') }}</span>
                            @elseif ($mine)
                                <span class="muted" title="{{ trans('ip-restrict::messages.in_use_title') }}">{{ trans('ip-restrict::messages.in_use_tag') }}</span>
                            @else
                                <form method="POST" action="{{ route('ip-restrict.remove') }}" class="inline"
                                      onsubmit="return confirm({{ json_encode(trans('ip-restrict::messages.confirm_remove', array('ip' => $entry['ip']))) }})">
                                    {!! csrf_field() !!}
                                    <input type="hidden" name="ip" value="{{ $entry['ip'] }}">
                                    <button type="submit" class="small secondary">{{ trans('ip-restrict::messages.btn_remove') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ trans('ip-restrict::messages.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('ip-restrict.add') }}" class="add">
        {!! csrf_field() !!}
        <label>{{ trans('ip-restrict::messages.field_ip') }}
            <input name="ip" required maxlength="64" value="{{ old('ip') }}" placeholder="{{ trans('ip-restrict::messages.field_ip_placeholder') }}"></label>
        <label>{{ trans('ip-restrict::messages.field_label') }}
            <input name="label" required maxlength="100" value="{{ old('label') }}" placeholder="{{ trans('ip-restrict::messages.field_label_placeholder') }}"></label>
        <button type="submit">{{ trans('ip-restrict::messages.btn_add') }}</button>
        @if (! $myIpAllowed)
            <button type="submit" class="secondary" onclick="this.form.ip.value = {{ json_encode((string) $myIp) }}">{{ trans('ip-restrict::messages.btn_add_mine') }}</button>
        @endif
    </form>
    <p class="help">{{ trans('ip-restrict::messages.ipv6_help') }}</p>

    <h2 id="deny">{{ trans('ip-restrict::messages.deny_title') }}</h2>
    <p class="help">{{ trans('ip-restrict::messages.deny_help') }}</p>

    <div class="table">
        <table>
            <thead>
                <tr>
                    <th>{{ trans('ip-restrict::messages.col_ip') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_label') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_source') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_added') }}</th>
                    <th>{{ trans('ip-restrict::messages.col_until') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($denied as $entry)
                    <tr>
                        <td><code>{{ $entry['ip'] }}</code></td>
                        <td>{{ $entry['label'] !== '' ? $entry['label'] : '—' }}</td>
                        <td>
                            @if ($entry['source'] === 'env')
                                <span class="tag grey">{{ trans('ip-restrict::messages.source_env') }}</span>
                            @elseif (isset($entry['added_by']) && $entry['added_by'] === 'auto')
                                <span class="tag red">{{ trans('ip-restrict::messages.source_auto') }}</span>
                            @else
                                <span class="tag blue">{{ trans('ip-restrict::messages.source_added') }}</span>
                            @endif
                        </td>
                        <td class="muted">
                            @if (isset($entry['added_at']))
                                {{ trans('ip-restrict::messages.added_on', array('date' => date('d/m/Y H:i', strtotime($entry['added_at'])), 'by' => isset($entry['added_by']) ? $entry['added_by'] : '?')) }}
                            @endif
                        </td>
                        <td class="muted">
                            {{ empty($entry['expires_at']) ? trans('ip-restrict::messages.until_unblocked') : date('d/m/Y H:i', strtotime($entry['expires_at'])) }}
                        </td>
                        <td class="actions">
                            @if ($entry['source'] === 'env')
                                <span class="muted" title="{{ trans('ip-restrict::messages.deny_env_title') }}">.env</span>
                            @else
                                <form method="POST" action="{{ route('ip-restrict.undeny') }}" class="inline">
                                    {!! csrf_field() !!}
                                    <input type="hidden" name="ip" value="{{ $entry['ip'] }}">
                                    <button type="submit" class="small secondary">{{ trans('ip-restrict::messages.btn_unblock') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ trans('ip-restrict::messages.none_denied') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('ip-restrict.deny') }}" class="add">
        {!! csrf_field() !!}
        <label>{{ trans('ip-restrict::messages.field_ip') }}
            <input name="ip" required maxlength="64" placeholder="{{ trans('ip-restrict::messages.field_ip_placeholder') }}"></label>
        <label>{{ trans('ip-restrict::messages.field_reason') }}
            <input name="label" required maxlength="100" placeholder="{{ trans('ip-restrict::messages.field_reason_placeholder') }}"></label>
        <label>{{ trans('ip-restrict::messages.field_duration') }}
            <select name="minutes">
                <option value="60">{{ trans('ip-restrict::messages.duration_hour') }}</option>
                <option value="1440">{{ trans('ip-restrict::messages.duration_day') }}</option>
                <option value="10080">{{ trans('ip-restrict::messages.duration_week') }}</option>
                <option value="0" selected>{{ trans('ip-restrict::messages.until_unblocked') }}</option>
            </select></label>
        <button type="submit" class="danger">{{ trans('ip-restrict::messages.btn_deny') }}</button>
    </form>
</div>
</body>
</html>
