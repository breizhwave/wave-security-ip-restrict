{{-- Plain Blade only (no @csrf, @class, @js…): it must compile on Laravel 5.1 as well as 13. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trans('ip-restrict::messages.admin_title') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; background: #f7fafc; color: #1a202c; }
        .ipr { max-width: 70rem; margin: 0 auto; padding: 1.5rem 1rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
        .help { color: #4a5568; font-size: .9rem; line-height: 1.5; }
        .alert { padding: .75rem 1rem; border-radius: .5rem; margin: 1rem 0; font-size: .9rem; border: 1px solid; }
        .alert.info { background: #ebf8ff; border-color: #90cdf4; }
        .alert.ok { background: #f0fff4; border-color: #9ae6b4; }
        .alert.error { background: #fff5f5; border-color: #feb2b2; }
        .table { overflow-x: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; }
        table { border-collapse: collapse; width: 100%; font-size: .9rem; }
        th, td { text-align: left; padding: .5rem .75rem; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        th { background: #f7fafc; font-size: .8rem; }
        td.actions { text-align: right; white-space: nowrap; }
        tr.hidden td { opacity: .55; }
        tr.mine { background: #f0fff4; box-shadow: inset 4px 0 0 #2f855a; }
        tr.mine code { font-weight: 700; }
        code { font-size: .85rem; }
        .my-ip { background: #c6f6d5; color: #22543d; padding: .1rem .4rem; border-radius: .25rem; font-weight: 700; }
        .tag { display: inline-block; font-size: .75rem; padding: .05rem .4rem; border-radius: .25rem; margin-left: .25rem; }
        .tag.grey { background: #edf2f7; } .tag.blue { background: #bee3f8; } .tag.red { background: #fed7d7; } .tag.green { background: #c6f6d5; }
        .muted { color: #718096; font-size: .8rem; }
        form.inline { display: inline; margin: 0; }
        form.add { display: flex; gap: .5rem; flex-wrap: wrap; align-items: flex-end; background: #fff; border: 1px solid #e2e8f0; border-radius: .5rem; padding: 1rem; margin: 1rem 0; }
        form.add label { display: flex; flex-direction: column; gap: .2rem; font-size: .8rem; font-weight: 600; }
        form.add input { font: inherit; font-size: .9rem; font-weight: 400; padding: .45rem .6rem; border: 1px solid #cbd5e0; border-radius: .375rem; min-width: 14rem; }
        button { font: inherit; font-size: .85rem; padding: .4rem .8rem; border-radius: .375rem; border: 1px solid #2b6cb0; background: #2b6cb0; color: #fff; cursor: pointer; }
        button.secondary { background: #fff; color: #2b6cb0; }
        button.small { font-size: .8rem; padding: .2rem .6rem; }
    </style>
</head>
<body>
<div class="ipr">
    <h1>{{ trans('ip-restrict::messages.admin_title') }}</h1>
    <p class="help">{{ trans('ip-restrict::messages.admin_help') }}</p>

    @if ($ok)
        <div class="alert ok">{{ $ok }}</div>
    @endif
    @if ($error)
        <div class="alert error">{{ $error }}</div>
    @endif

    <div class="alert {{ $active ? 'info' : 'error' }}">
        {{ trans($active ? 'ip-restrict::messages.active' : 'ip-restrict::messages.inactive') }}
        {{ trans('ip-restrict::messages.your_address') }} <code class="my-ip">{{ $myIp }}</code>
        ({{ trans($myIpAllowed ? 'ip-restrict::messages.mine_allowed' : 'ip-restrict::messages.mine_not_listed') }}).
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
</div>
</body>
</html>
