<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trans(isset($reason) && $reason === 'denied' ? 'ip-restrict::messages.denied_page_title' : 'ip-restrict::messages.forbidden_title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; background: #f7fafc; color: #1a202c; padding: 1rem; box-sizing: border-box; }
        main { max-width: 28rem; background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1.5rem; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
        p { margin: .5rem 0; line-height: 1.5; color: #4a5568; }
        code { background: #edf2f7; padding: .1rem .4rem; border-radius: .25rem; font-size: .9rem; color: #1a202c; }
    </style>
</head>
<body>
<main>
    <?php $denied = isset($reason) && $reason === 'denied'; ?>
    <h1>{{ trans($denied ? 'ip-restrict::messages.denied_page_title' : 'ip-restrict::messages.forbidden_title') }}</h1>
    <p>{{ trans($denied ? 'ip-restrict::messages.denied_page_text' : 'ip-restrict::messages.forbidden_text') }}</p>
    <p>{{ trans('ip-restrict::messages.your_address') }} <code>{{ $ip }}</code></p>
    <p>{{ trans($denied ? 'ip-restrict::messages.denied_page_help' : 'ip-restrict::messages.forbidden_help') }}</p>
</main>
</body>
</html>
