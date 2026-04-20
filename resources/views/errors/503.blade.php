<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 - Maintenance</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Figtree', sans-serif; background: #f9fafb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .container { max-width: 32rem; width: 100%; text-align: center; }
        .code { font-size: 10rem; font-weight: 700; line-height: 1; color: #e5e7eb; user-select: none; position: relative; }
        .icon-wrap { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
        .icon-wrap svg { height: 7rem; width: 7rem; color: #60a5fa; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 0.75rem; }
        p { color: #6b7280; margin-bottom: 2rem; max-width: 24rem; margin-left: auto; margin-right: auto; }
        .pulse { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #6b7280; background: #f3f4f6; padding: 0.5rem 1rem; border-radius: 9999px; }
        .dot { height: 0.5rem; width: 0.5rem; border-radius: 9999px; background: #60a5fa; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">
            503
            <div class="icon-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="0.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-3.258-3.258a2.25 2.25 0 00-3.182 0l-.861.86a2.25 2.25 0 000 3.183l6.38 6.38a2.25 2.25 0 003.183 0l.861-.86a2.25 2.25 0 000-3.183L11.42 15.17zM21.13 9.13l-2.782-2.782a2.25 2.25 0 00-3.182 0l-.861.86a2.25 2.25 0 000 3.183l2.782 2.782a2.25 2.25 0 003.182 0l.861-.86a2.25 2.25 0 000-3.183z" />
                </svg>
            </div>
        </div>
        <h1>We'll be right back</h1>
        <p>We're performing scheduled maintenance to improve your experience. This won't take long.</p>
        <div class="pulse">
            <span class="dot"></span>
            Maintenance in progress
        </div>
    </div>
</body>
</html>
