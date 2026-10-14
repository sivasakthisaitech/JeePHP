<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#050505">
    <title>Welcome to JeePHP — Version 1.0</title>
    <style>
        :root { color-scheme: dark; font-family: "IBM Plex Mono", "Cascadia Code", "Consolas", monospace; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; min-height: 100vh; background: #050505; color: #f5f5f5; overflow-x: hidden; }
        .matrix { position: fixed; inset: 0; overflow: hidden; pointer-events: none; opacity: .22; mask-image: linear-gradient(to bottom, #000 0%, #000 55%, transparent 100%); }
        .matrix-column { position: absolute; top: -125vh; left: var(--x); width: 1ch; color: #fff; font-size: clamp(13px, 1.2vw, 17px); line-height: 1.75; white-space: pre; animation: fall var(--duration) linear var(--delay) infinite; text-shadow: 0 0 9px #fff; }
        @keyframes fall { to { transform: translateY(235vh); } }
        .scanline { position: fixed; inset: 0 0 auto; height: 1px; background: rgba(255,255,255,.17); box-shadow: 0 0 16px #fff; animation: scan 8s linear infinite; pointer-events: none; }
        @keyframes scan { to { transform: translateY(100vh); } }
        .page { position: relative; z-index: 1; max-width: 1120px; margin: auto; padding: 30px 28px 54px; }
        nav { display: flex; align-items: center; justify-content: space-between; padding-bottom: 22px; border-bottom: 1px solid #333; color: #aaa; font-size: 12px; letter-spacing: .11em; }
        .brand { color: #fff; font-weight: 700; letter-spacing: .08em; }
        .brand span { color: #777; }
        .status { display: flex; align-items: center; gap: 9px; }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: #fff; box-shadow: 0 0 10px #fff; animation: blink 1.4s steps(2) infinite; }
        @keyframes blink { 50% { opacity: .25; } }
        main { padding: clamp(88px, 15vh, 150px) 0 80px; }
        .eyebrow { color: #888; font-size: 12px; letter-spacing: .2em; }
        h1 { max-width: 900px; margin: 24px 0 18px; font-size: clamp(42px, 8.6vw, 104px); line-height: .98; letter-spacing: -.08em; font-weight: 700; }
        h1 span { display: block; color: #777; font-size: .29em; letter-spacing: .18em; margin-bottom: 20px; }
        .version { display: inline-flex; gap: 10px; align-items: center; border: 1px solid #666; padding: 8px 12px; color: #ddd; font-size: 12px; letter-spacing: .13em; }
        .intro { max-width: 590px; margin-top: 26px; color: #aaa; font-size: 15px; line-height: 1.9; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        .button { display: inline-block; padding: 13px 18px; border: 1px solid #eee; color: #050505; background: #f5f5f5; text-decoration: none; font-size: 12px; font-weight: 700; letter-spacing: .06em; transition: .2s ease; }
        .button:hover { color: white; background: #050505; }
        .button.secondary { color: #eee; background: transparent; border-color: #555; }
        .button.secondary:hover { border-color: #fff; }
        .terminal { max-width: 660px; margin-top: 64px; border: 1px solid #333; background: rgba(5,5,5,.78); box-shadow: 0 18px 70px rgba(0,0,0,.38); }
        .terminal-head { display: flex; gap: 7px; align-items: center; padding: 12px 15px; border-bottom: 1px solid #292929; color: #777; font-size: 11px; }
        .terminal-head span:last-child { margin-left: 8px; }
        .terminal pre { margin: 0; padding: 20px; overflow-x: auto; color: #ddd; font-size: 13px; line-height: 1.9; }
        .terminal code { font-family: inherit; }
        .dim { color: #777; }
        .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; margin-top: 72px; border: 1px solid #333; background: #333; }
        .feature { min-height: 130px; padding: 21px; background: #080808; }
        .feature b { display: block; margin-bottom: 12px; font-size: 12px; letter-spacing: .09em; }
        .feature p { margin: 0; color: #888; font-size: 12px; line-height: 1.8; }
        footer { display: flex; justify-content: space-between; gap: 20px; margin-top: 62px; border-top: 1px solid #333; padding-top: 18px; color: #666; font-size: 11px; letter-spacing: .06em; }
        @media (max-width: 680px) { .page { padding-inline: 18px; } .features { grid-template-columns: 1fr; } .feature { min-height: auto; } nav { font-size: 10px; } .intro { font-size: 14px; } footer { flex-direction: column; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; } }
    </style>
</head>
<body>
    <div class="matrix" id="matrix" aria-hidden="true"></div>
    <div class="scanline" aria-hidden="true"></div>
    <div class="page">
        <nav aria-label="Main navigation">
            <div class="brand">Jee<span>PHP</span></div>
            <div class="status"><span class="dot"></span> SYSTEM ONLINE</div>
        </nav>
        <main>
            <div class="eyebrow">A SIMPLE PHP FRAMEWORK</div>
            <h1><span>WELCOME TO</span>JeePHP</h1>
            <div class="version">VERSION <strong>1.0</strong> <span class="dim">/ FIRST RELEASE</span></div>
            <p class="intro">Build your PHP application with clear routes, reusable models, helpful shortcuts, and database code that feels simple from the first line.</p>
            <div class="actions">
                <a class="button" href="#quick-start">EXPLORE JEEPHP <span aria-hidden="true">→</span></a>
                <a class="button secondary" href="https://www.php.net/">PHP DOCUMENTATION ↗</a>
            </div>
            <section class="terminal" id="quick-start" aria-label="Quick start example">
                <div class="terminal-head"><span>●</span><span>●</span><span>●</span><span>app/Models/Customer.php</span></div>
                <pre><code><span class="dim">// Make a model for your customers</span>
class Customer extends Model
{
    protected static string $table = 'customers';

    protected array $fillable = ['name', 'mobile'];
}

$customer = Customer::find(1);</code></pre>
            </section>
            <section class="features" aria-label="JeePHP features">
                <article class="feature"><b>01 / MODELS</b><p>Keep table data and common database actions together in reusable PHP classes.</p></article>
                <article class="feature"><b>02 / HELPERS</b><p>Use short functions for database access, views, sessions, validation, and safe output.</p></article>
                <article class="feature"><b>03 / ROUTES</b><p>Connect a clear URL to the controller that handles the request.</p></article>
            </section>
        </main>
        <footer><span>JeePHP · VERSION 1.0</span><span>SMALL STEPS. CLEAN PHP.</span></footer>
    </div>
    <script>
        const matrix = document.getElementById('matrix');
        const count = Math.min(48, Math.ceil(window.innerWidth / 28));
        for (let i = 0; i < count; i++) {
            const column = document.createElement('span');
            column.className = 'matrix-column';
            column.style.setProperty('--x', `${(i / count) * 100}%`);
            column.style.setProperty('--duration', `${12 + Math.random() * 20}s`);
            column.style.setProperty('--delay', `${-Math.random() * 28}s`);
            column.textContent = Array.from({length: 42}, () => Math.random() > .5 ? '1' : '0').join('\n');
            matrix.appendChild(column);
        }
    </script>
</body>
</html>
