{{-- Shared by admin.blade.php and attempts.blade.php. --}}
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
        button.danger { border-color: #c53030; background: #c53030; }
        button.danger.secondary { background: #fff; color: #c53030; }
        h2 { font-size: 1.15rem; margin: 2rem 0 .5rem; }
        nav.tabs { display: flex; gap: .25rem; border-bottom: 1px solid #e2e8f0; margin: 1rem 0; }
        nav.tabs a { padding: .5rem .9rem; color: #2b6cb0; text-decoration: none; font-size: .9rem; border: 1px solid transparent; border-bottom: none; border-radius: .375rem .375rem 0 0; margin-bottom: -1px; }
        nav.tabs a.current { background: #fff; border-color: #e2e8f0; color: #1a202c; font-weight: 600; }
        form.add select { font: inherit; font-size: .9rem; font-weight: 400; padding: .45rem .6rem; border: 1px solid #cbd5e0; border-radius: .375rem; }
        .days { display: flex; flex-wrap: wrap; gap: .35rem; margin: .5rem 0 1rem; }
        .days a { font-size: .8rem; padding: .2rem .55rem; border: 1px solid #cbd5e0; border-radius: 1rem; color: #2b6cb0; text-decoration: none; background: #fff; }
        .days a.current { background: #2b6cb0; border-color: #2b6cb0; color: #fff; }
        td.wrap { max-width: 22rem; overflow-wrap: anywhere; }
        tr.blocked { background: #fff5f5; }
        @media (max-width: 40rem) { form.add input { min-width: 0; width: 100%; } form.add label { width: 100%; } }
    </style>
