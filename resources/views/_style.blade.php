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
        nav.tabs { flex-wrap: wrap; }
        nav.tabs .count { display: inline-block; min-width: 1.2rem; text-align: center; font-size: .72rem; padding: 0 .35rem; border-radius: 1rem; background: #edf2f7; color: #4a5568; font-weight: 600; }
        nav.tabs .count:empty { display: none; }
        nav.tabs .count.hot { background: #fed7d7; color: #9b2c2c; }
        .alert.warn { background: #fffaf0; border-color: #fbd38d; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1.25rem; margin: 1rem 0 2rem; }
        .card h3 { font-size: 1rem; margin: 1.25rem 0 .5rem; }
        .case { display: flex; gap: 1rem; align-items: flex-start; }
        .case-num { flex: none; width: 3rem; height: 3rem; border-radius: 50%; background: #2b6cb0; color: #fff; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .case-num.none { background: #a0aec0; }
        .case-kicker { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #718096; }
        .case-title { font-size: 1.1rem; font-weight: 700; margin: .1rem 0 .25rem; }
        .case .help { margin: .25rem 0; }
        a.readme { font-size: .85rem; color: #2b6cb0; }
        table.matrix th[scope=row] { font-weight: 600; min-width: 11rem; }
        table.matrix th code { font-weight: 400; color: #4a5568; }
        table.matrix tr.mine th[scope=row] { color: #22543d; }
        .cell { display: inline-flex; align-items: center; gap: .3rem; white-space: nowrap; font-size: .8rem; font-weight: 600; padding: .2rem .55rem; border-radius: 1rem; }
        .cell.open { background: #c6f6d5; color: #22543d; }
        .cell.login { background: #bee3f8; color: #2a4365; }
        .cell.admin_open { background: #feebc8; color: #7b341e; }
        .cell.restricted { background: #fed7d7; color: #822727; }
        .cell.blocked { background: #742a2a; color: #fff; }
        .cell.off { background: #edf2f7; color: #718096; }
        .legend { line-height: 2.2; }
        .legend .cell { margin-left: .75rem; line-height: 1.3; }
        .legend .cell:first-child { margin-left: 0; }
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .75rem; margin: 1rem 0; }
        .fact { border: 1px solid #edf2f7; border-radius: .5rem; padding: .6rem .8rem; background: #f7fafc; }
        .fact-label { font-size: .75rem; color: #718096; text-transform: uppercase; letter-spacing: .04em; }
        .fact-value { font-weight: 600; margin: .15rem 0; }
        details summary { cursor: pointer; font-weight: 600; font-size: .9rem; margin: .5rem 0; }
        @media (max-width: 40rem) { form.add input { min-width: 0; width: 100%; } form.add label { width: 100%; } }
    </style>
