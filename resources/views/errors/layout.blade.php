<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} {{ $title }} - DMIMS</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
            background: #0a0e1a;
            color: #e2e8f0;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            margin: 1.5rem;
            padding: 2.5rem 2rem;
            border-radius: 0.75rem;
            background: #0f172a;
            border: 1px solid #1e293b;
            text-align: center;
        }
        .brand {
            font-weight: 700;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }
        .code {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            color: #f1f5f9;
            margin: 0 0 0.5rem;
        }
        h1 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            color: #f1f5f9;
        }
        p {
            font-size: 0.875rem;
            color: #94a3b8;
            line-height: 1.5;
            margin: 0 0 1.75rem;
        }
        .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        a.btn-primary { background: #4f46e5; color: #fff; }
        a.btn-primary:hover { background: #4338ca; }
        a.btn-secondary { background: transparent; color: #cbd5e1; border: 1px solid #334155; }
        a.btn-secondary:hover { border-color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">DMIMS</div>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            @auth
                <a class="btn btn-primary" href="{{ url('/admin') }}">Go to Dashboard</a>
            @else
                <a class="btn btn-primary" href="{{ url('/admin/login') }}">Sign in</a>
            @endauth
            <a class="btn btn-secondary" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Go Back</a>
        </div>
    </div>
</body>
</html>
