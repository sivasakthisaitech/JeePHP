<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title>FrogO · JeePHP API Workbench</title>
    <style>
        :root{color-scheme:light;--ink:#111;--muted:#737373;--line:#dedede;--paper:#fff;--soft:#f5f5f5;--green:#176b45;--red:#b42318;--amber:#8b5e00;--mono:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;--sans:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        *{box-sizing:border-box}html{min-height:100%;background:#f7f7f7}body{min-height:100vh;margin:0;color:var(--ink);font:14px/1.5 var(--sans)}button,input,select,textarea{font:inherit}button{cursor:pointer}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.app{min-height:100vh}.sidebar{position:fixed;inset:0 auto 0 0;width:236px;padding:25px 17px;background:#111;color:#fff;display:flex;flex-direction:column;z-index:4}.brand{display:flex;align-items:center;gap:12px;padding:2px 7px 26px;border-bottom:1px solid #383838}.brand-mark{width:38px;height:38px;border:1px solid #fff;display:grid;place-items:center;font:700 16px var(--mono);letter-spacing:-2px}.brand-name{font:700 16px var(--mono);letter-spacing:.13em}.brand-sub{display:block;margin-top:4px;color:#9b9b9b;font:10px var(--mono);letter-spacing:.13em}.nav-label{margin:25px 8px 10px;color:#8a8a8a;font:10px var(--mono);letter-spacing:.14em}.nav-item{display:flex;align-items:center;gap:10px;padding:10px 11px;color:#ddd;border:1px solid transparent;border-radius:5px;text-decoration:none;font-size:13px}.nav-item.active{background:#fff;color:#111;font-weight:650}.nav-dot{width:7px;height:7px;border:1px solid currentColor;border-radius:50%}.sidebar-note{margin-top:auto;padding:14px 11px;border:1px solid #393939;border-radius:5px;color:#aaa;font:11px/1.7 var(--mono)}.sidebar-note strong{display:flex;align-items:center;gap:7px;color:#fff;font-size:11px}.live-dot{width:7px;height:7px;border-radius:50%;background:#a8f0c7;box-shadow:0 0 0 3px #a8f0c71b}.main{margin-left:236px;min-height:100vh}.topbar{height:62px;padding:0 36px;border-bottom:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:space-between}.crumb{font:11px var(--mono);letter-spacing:.07em;color:#777}.crumb b{color:#111;font-weight:600}.local-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid #b9ddc8;background:#f4fbf6;border-radius:99px;color:#185b3d;font:10px var(--mono);letter-spacing:.04em}.local-badge span{width:6px;height:6px;background:#32865c;border-radius:50%}.wrap{max-width:1480px;margin:0 auto;padding:35px 36px 48px}.intro{display:flex;justify-content:space-between;align-items:flex-end;gap:22px;margin-bottom:25px}.eyebrow{margin:0 0 9px;color:#666;font:10px var(--mono);letter-spacing:.16em;text-transform:uppercase}.intro h1{margin:0;font:500 clamp(24px,3vw,34px)/1.18 var(--mono);letter-spacing:-.07em}.intro p{max-width:690px;margin:10px 0 0;color:#686868;font-size:13px}.intro-mark{color:#b9b9b9;font:11px/1.7 var(--mono);text-align:right;white-space:nowrap}.workspace{display:grid;grid-template-columns:minmax(360px,.95fr) minmax(390px,1.05fr);gap:17px;align-items:stretch}.card{min-width:0;background:var(--paper);border:1px solid var(--line);border-radius:7px;box-shadow:0 2px 8px #00000005}.card-head{min-height:52px;padding:13px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px}.card-title{display:flex;align-items:center;gap:9px;font:600 12px var(--mono);letter-spacing:.02em}.card-icon{width:21px;height:21px;border:1px solid #ccc;display:grid;place-items:center;font:11px var(--mono)}.tiny-label{color:#888;font:10px var(--mono);letter-spacing:.08em;text-transform:uppercase}.request-card{display:flex;flex-direction:column}.request-url{padding:15px 15px 12px;display:flex;gap:8px}.method{width:98px;flex:none;padding:0 10px;border:1px solid #bdbdbd;border-radius:4px;background:#fff;color:#111;font:700 11px var(--mono);letter-spacing:.05em}.url-input{width:100%;min-width:0;height:40px;padding:0 12px;border:1px solid #cfcfcf;border-radius:4px;background:#fff;font:12px var(--mono);outline:none}.url-input:focus,.editor:focus{border-color:#111;box-shadow:0 0 0 2px #1111110b}.send{min-width:87px;padding:0 14px;border:1px solid #111;border-radius:4px;background:#111;color:#fff;font:600 11px var(--mono);transition:.15s}.send:hover{background:#333}.send:disabled{cursor:wait;opacity:.55}.send .arrow{margin-left:8px}.request-hint{display:flex;align-items:center;gap:7px;padding:0 16px 12px;color:#777;font:10px/1.45 var(--mono)}.hint-mark{width:14px;height:14px;border:1px solid #c7c7c7;border-radius:50%;display:grid;place-items:center;font:9px var(--mono)}.tabs{display:flex;gap:21px;padding:0 16px;border-bottom:1px solid var(--line)}.tab{position:relative;padding:11px 1px 10px;border:0;background:transparent;color:#777;font:11px var(--mono)}.tab[aria-selected="true"]{color:#111;font-weight:700}.tab[aria-selected="true"]:after{position:absolute;right:0;bottom:-1px;left:0;height:2px;background:#111;content:""}.tab-count{margin-left:5px;color:#999;font-size:9px}.editor-label{display:flex;justify-content:space-between;margin:14px 16px 7px;color:#606060;font:10px var(--mono);letter-spacing:.06em;text-transform:uppercase}.editor{display:block;width:calc(100% - 32px);min-height:185px;margin:0 16px;padding:13px 14px;border:1px solid #d3d3d3;border-radius:4px;background:#fbfbfb;color:#252525;font:12px/1.65 var(--mono);resize:vertical;outline:none;tab-size:2}.editor-body{min-height:188px}.editor-note{margin:8px 16px 15px;color:#888;font:10px/1.55 var(--mono)}.request-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--line);margin-top:auto;color:#777;font:10px var(--mono)}.request-footer strong{color:#333;font-weight:500}.response-card{display:flex;flex-direction:column;min-height:490px}.response-toolbar{min-height:52px;padding:11px 15px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:10px}.response-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.status{padding:5px 8px;border:1px solid #d5d5d5;border-radius:4px;background:#f5f5f5;color:#777;font:700 10px var(--mono)}.status.ok{border-color:#b9ddc8;background:#f1faf4;color:var(--green)}.status.bad{border-color:#efc2bf;background:#fff5f4;color:var(--red)}.response-stat{color:#777;font:10px var(--mono)}.copy-btn{padding:6px 8px;border:1px solid #d1d1d1;border-radius:4px;background:#fff;color:#444;font:10px var(--mono)}.copy-btn:hover{border-color:#111;color:#111}.response-tabs{display:flex;gap:18px;padding:0 16px;border-bottom:1px solid var(--line)}.response-body{position:relative;flex:1;min-height:355px;padding:15px 16px}.empty-state{min-height:310px;display:grid;place-content:center;text-align:center;color:#777}.empty-icon{width:43px;height:43px;margin:0 auto 13px;border:1px solid #d5d5d5;border-radius:50%;display:grid;place-items:center;color:#444;font:16px var(--mono)}.empty-state strong{color:#333;font:500 12px var(--mono)}.empty-state p{max-width:240px;margin:7px auto 0;font:10px/1.65 var(--mono)}.response-pre{margin:0;max-height:480px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere;color:#222;font:11px/1.7 var(--mono)}.diagnosis{display:none;margin:0 16px 15px;padding:13px 14px;border:1px solid #e4d2a8;border-left:3px solid #8b5e00;border-radius:3px;background:#fffcf4}.diagnosis.show{display:block}.diagnosis h3{margin:0 0 6px;color:#684700;font:600 11px var(--mono)}.diagnosis p{margin:5px 0;color:#5c5140;font:11px/1.6 var(--mono)}.diagnosis b{color:#29231a}.bottom-note{display:flex;justify-content:space-between;gap:20px;margin-top:15px;padding:13px 15px;border:1px solid #e0e0e0;border-radius:5px;background:#fff;color:#666;font:10px/1.65 var(--mono)}.bottom-note strong{color:#111}.foot{margin-top:23px;color:#999;text-align:center;font:10px var(--mono)}.toast{position:fixed;right:22px;bottom:22px;z-index:9;max-width:min(420px,calc(100vw - 32px));padding:12px 15px;border:1px solid #111;border-radius:4px;background:#111;color:#fff;font:11px/1.5 var(--mono);box-shadow:0 6px 24px #0002;opacity:0;transform:translateY(8px);transition:.18s;pointer-events:none}.toast.show{opacity:1;transform:translateY(0)}[hidden]{display:none!important}
        @media(max-width:1050px){.workspace{grid-template-columns:1fr}.response-card{min-height:420px}.response-body{min-height:300px}.wrap{padding:27px 24px 38px}.topbar{padding:0 24px}}
        @media(max-width:680px){.sidebar{position:static;width:auto;height:auto;padding:11px 14px;flex-direction:row;align-items:center}.brand{padding:0;border:0}.brand-mark{width:32px;height:32px}.brand-name{font-size:13px}.brand-sub{font-size:9px}.sidebar .nav-label,.sidebar .nav-item,.sidebar-note{display:none}.main{margin-left:0}.topbar{height:50px;padding:0 16px}.wrap{padding:24px 14px 32px}.intro{display:block}.intro-mark{margin-top:14px;text-align:left}.workspace{grid-template-columns:minmax(0,1fr);gap:12px}.request-url{gap:6px;padding:12px}.method{width:82px;padding:0 6px}.send{min-width:68px;padding:0 8px}.request-hint{padding:0 12px 10px}.editor{width:calc(100% - 24px);margin:0 12px}.editor-label{margin-right:12px;margin-left:12px}.editor-note{margin-right:12px;margin-left:12px}.card-head,.response-toolbar{padding-right:12px;padding-left:12px}.response-body{padding:13px 12px}.diagnosis{margin-right:12px;margin-left:12px}.bottom-note{display:block}.bottom-note span{display:block;margin-top:5px}.intro h1{font-size:25px}}
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="FrogO navigation">
        <div class="brand"><div class="brand-mark" aria-hidden="true">F/</div><div><div class="brand-name">FROGO</div><span class="brand-sub">JEEPHP API WORKBENCH</span></div></div>
        <div class="nav-label">WORKSPACE</div>
        <a class="nav-item active" href="#request"><span class="nav-dot"></span> Request tester</a>
        <a class="nav-item" href="#response"><span class="nav-dot"></span> Response inspector</a>
        <div class="sidebar-note"><strong><span class="live-dot"></span> LOCAL MODE ONLY</strong><br>Requests run in this browser.<br>No request history is saved.</div>
    </aside>

    <main class="main">
        <header class="topbar"><div class="crumb">JEEPHP <span aria-hidden="true">/</span> <b>FROGO</b></div><div class="local-badge"><span></span> LOCAL DEVELOPMENT TOOL</div></header>
        <div class="wrap">
            <section class="intro">
                <div><p class="eyebrow">API PLAYGROUND · FROGO 1.0</p><h1>Test an endpoint.<br>Understand the response.</h1><p>Send a request to your JeePHP API, inspect the response, and get a clear next step when something fails.</p></div>
                <div class="intro-mark">METHOD / HEADERS / BODY<br>STATUS / TIME / RESPONSE</div>
            </section>

            <section class="workspace" aria-label="API request and response">
                <article class="card request-card" id="request">
                    <div class="card-head"><div class="card-title"><span class="card-icon">&gt;_</span> NEW REQUEST</div><span class="tiny-label">SEND TO YOUR APP</span></div>
                    <form id="request-form" autocomplete="off">
                        <div class="request-url">
                            <label class="sr-only" for="method">HTTP method</label>
                            <select class="method" id="method" aria-label="HTTP method"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option><option>HEAD</option></select>
                            <label class="sr-only" for="url">Request URL</label>
                            <input class="url-input" id="url" type="text" inputmode="url" spellcheck="false" value="/frogo/ping" placeholder="/api/users">
                            <button class="send" id="send" type="submit">SEND <span class="arrow">↗</span></button>
                        </div>
                        <div class="request-hint"><span class="hint-mark">i</span> Relative paths or this app's own origin only.</div>
                        <div class="tabs" role="tablist" aria-label="Request editor">
                            <button class="tab" id="headers-tab" type="button" role="tab" aria-selected="true" aria-controls="headers-panel">Headers <span class="tab-count">2</span></button>
                            <button class="tab" id="body-tab" type="button" role="tab" aria-selected="false" aria-controls="body-panel">Body</button>
                        </div>
                        <section id="headers-panel" role="tabpanel" aria-labelledby="headers-tab">
                            <label class="editor-label" for="headers">REQUEST HEADERS <span>JSON</span></label>
                            <textarea class="editor" id="headers" spellcheck="false" autocapitalize="off">{
  "Accept": "application/json",
  "Content-Type": "application/json"
}</textarea>
                            <p class="editor-note">Add auth here, for example: "Authorization": "Bearer YOUR_TOKEN". Headers stay in this page only.</p>
                        </section>
                        <section id="body-panel" role="tabpanel" aria-labelledby="body-tab" hidden>
                            <label class="editor-label" for="body">REQUEST BODY <span>RAW TEXT OR JSON</span></label>
                            <textarea class="editor editor-body" id="body" spellcheck="false" autocapitalize="off">{
  "name": "Siva",
  "email": "siva@example.com"
}</textarea>
                            <p class="editor-note">GET and HEAD requests do not send a body. Ensure Content-Type matches your payload.</p>
                        </section>
                        <div class="request-footer"><span>METHOD <strong id="method-label">GET</strong></span><span>SESSION <strong>NOT SAVED</strong></span></div>
                    </form>
                </article>

                <article class="card response-card" id="response">
                    <div class="card-head"><div class="card-title"><span class="card-icon">&lt;/&gt;</span> RESPONSE</div><span class="tiny-label" id="response-label">WAITING FOR REQUEST</span></div>
                    <div class="response-toolbar"><div class="response-meta"><span class="status" id="status">NO RESPONSE</span><span class="response-stat" id="duration">— ms</span><span class="response-stat" id="size">— B</span></div><button class="copy-btn" id="copy-response" type="button" disabled> COPY BODY </button></div>
                    <div class="response-tabs" role="tablist" aria-label="Response details">
                        <button class="tab response-tab" id="response-body-tab" type="button" role="tab" aria-selected="true">Body</button>
                        <button class="tab response-tab" id="response-headers-tab" type="button" role="tab" aria-selected="false">Headers</button>
                    </div>
                    <div class="response-body" id="response-body-panel" role="tabpanel" aria-labelledby="response-body-tab"><div class="empty-state" id="empty-state"><div class="empty-icon">{ }</div><strong>Response will appear here</strong><p>Choose a method, enter an endpoint, and press SEND to see the result.</p></div><pre class="response-pre" id="response-content" hidden></pre></div>
                    <div class="response-body" id="response-headers-panel" role="tabpanel" aria-labelledby="response-headers-tab" hidden><div class="empty-state"><div class="empty-icon">≡</div><strong>Response headers</strong><p>Send a request to inspect headers returned by the API.</p></div><pre class="response-pre" id="response-headers-content" hidden></pre></div>
                    <section class="diagnosis" id="diagnosis" aria-live="polite"><h3 id="diagnosis-title">WHAT HAPPENED?</h3><p><b>Why:</b> <span id="diagnosis-why"></span></p><p><b>Try:</b> <span id="diagnosis-fix"></span></p></section>
                </article>
            </section>

            <aside class="bottom-note"><strong>Safety by default</strong><span>FrogO only opens in local development when APP_DEBUG=true and the browser connects from this computer. Requests go directly from your browser to this app. No server-side proxy, saved history, or production access.</span></aside>
            <footer class="foot">FROGO · BUILT INTO JEEPHP · LOCAL API TESTING</footer>
        </div>
    </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>
(() => {
    const form = document.getElementById('request-form');
    const method = document.getElementById('method');
    const methodLabel = document.getElementById('method-label');
    const urlInput = document.getElementById('url');
    const headersInput = document.getElementById('headers');
    const bodyInput = document.getElementById('body');
    const sendButton = document.getElementById('send');
    const statusBadge = document.getElementById('status');
    const duration = document.getElementById('duration');
    const size = document.getElementById('size');
    const responseContent = document.getElementById('response-content');
    const responseHeadersContent = document.getElementById('response-headers-content');
    const emptyState = document.getElementById('empty-state');
    const diagnosis = document.getElementById('diagnosis');
    const diagnosisTitle = document.getElementById('diagnosis-title');
    const diagnosisWhy = document.getElementById('diagnosis-why');
    const diagnosisFix = document.getElementById('diagnosis-fix');
    const responseLabel = document.getElementById('response-label');
    const copyButton = document.getElementById('copy-response');
    const toast = document.getElementById('toast');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let rawResponse = '';
    let toastTimer;
        let requestTimeout;

    function showToast(message) {
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2800);
    }

    function setMethod() {
        methodLabel.textContent = method.value;
        document.getElementById('body-tab').disabled = ['GET', 'HEAD'].includes(method.value);
        if (['GET', 'HEAD'].includes(method.value) && document.getElementById('body-panel').hidden === false) {
            selectRequestTab('headers');
        }
    }

    function selectRequestTab(name) {
        const showBody = name === 'body';
        document.getElementById('headers-tab').setAttribute('aria-selected', String(!showBody));
        document.getElementById('body-tab').setAttribute('aria-selected', String(showBody));
        document.getElementById('headers-panel').hidden = showBody;
        document.getElementById('body-panel').hidden = !showBody;
    }

    function selectResponseTab(name) {
        const showHeaders = name === 'headers';
        document.getElementById('response-body-tab').setAttribute('aria-selected', String(!showHeaders));
        document.getElementById('response-headers-tab').setAttribute('aria-selected', String(showHeaders));
        document.getElementById('response-body-panel').hidden = showHeaders;
        document.getElementById('response-headers-panel').hidden = !showHeaders;
    }

    function displayResponse(text, contentType) {
        rawResponse = text;
        emptyState.hidden = true;
        responseContent.hidden = false;
        responseHeadersContent.hidden = false;
        if (contentType.includes('json')) {
            try {
                responseContent.textContent = JSON.stringify(JSON.parse(text), null, 2);
            } catch {
                responseContent.textContent = text || '(empty response body)';
            }
        } else {
            responseContent.textContent = text || '(empty response body)';
        }
        copyButton.disabled = text.length === 0;
    }

    function explain(statusCode, contentType, bodyText) {
        const map = {
            400: ['Bad request', 'Server-ku anuppina input format sari illa-nu API solludhu.', 'Request body JSON valid-aa irukka, required fields and Content-Type correct-aa irukka check pannunga.'],
            401: ['Authentication needed', 'API valid login/token credentials receive pannala.', 'Headers tab-la Authorization: Bearer YOUR_TOKEN add pannunga; token expired/revoked aagala-nu check pannunga.'],
            403: ['Access denied', 'Credentials irundhaalum indha action-ku permission illa, illa CSRF protection request-ai reject pannirukkalaam.', 'User role/permission and token ability check pannunga. Browser route-na page reload panni CSRF token refresh pannunga.'],
            404: ['Route not found', 'Indha URL-ku match aagura route app-la register aagala, illa spelling/method mismatch.', 'routes/api.php or routes/web.php-la route path and HTTP method check pannunga.'],
            405: ['Method not allowed', 'URL irukku; aana indha HTTP method route-ku allow pannala.', 'Route definition-la GET/POST/PUT/PATCH/DELETE method-ai verify panni matching method select pannunga.'],
            419: ['Request verification failed', 'JeePHP CSRF token missing, mismatch, illa session expire aagirukkalaam.', 'FrogO page reload pannitu retry pannunga. API token use panreenga-na valid APIO Bearer token anuppunga.'],
            422: ['Validation failed', 'API request reach aachu; input validation rules fail aayiduchu.', 'Response body-la field errors paarthu missing/wrong value correct pannunga.'],
            429: ['Too many requests', 'API rate limit request-ai temporary-aa block panniduchu.', 'Konjam wait pannunga; retry interval and server rate-limit config check pannunga.'],
            500: ['Server error', 'Request app-kulla reach aachu, aana PHP/controller/database side-la exception vandhirukku.', 'Response details and local JeePHP Console paarunga; error file/line, .duck database settings, and recent code changes check pannunga.'],
            502: ['Upstream service error', 'App connect panra API/proxy service invalid response kuduthirukkalaam.', 'Dependent service running-aa, proxy URL and server logs sariyaa-nu check pannunga.'],
            503: ['Service unavailable', 'App or dependent service ippo ready illa.', 'PHP server, database, and required services start aagirukka-nu check panni retry pannunga.']
        };
        let message = map[statusCode];
        if (!message && statusCode >= 200 && statusCode < 300) {
            message = ['Request succeeded', 'API success status return pannirukku.', 'Response body-la expected data irukka check pannunga.'];
        } else if (!message && statusCode >= 300 && statusCode < 400) {
            message = ['Redirect response', 'API vera URL-ku redirect pannirukku.', 'Final URL and redirect settings verify pannunga.'];
        } else if (!message) {
            message = ['Request failed', 'API HTTP ' + statusCode + ' status return pannirukku.', 'Response body and server log paarthu endpoint behavior verify pannunga.'];
        }
        if (contentType.includes('json') && bodyText) {
            try {
                const parsed = JSON.parse(bodyText);
                const detail = parsed.message || parsed.error;
                if (typeof detail === 'string' && detail.trim()) message[1] += ' Server detail: ' + detail.trim().slice(0, 220);
            } catch {
                message[1] += ' Response JSON parse aagala; raw response Body tab-la irukku.';
            }
        }
        diagnosisTitle.textContent = String(statusCode >= 200 && statusCode < 300 ? 'RESULT · ' : 'WHY IT FAILED · ') + message[0].toUpperCase();
        diagnosisWhy.textContent = message[1];
        diagnosisFix.textContent = message[2];
        diagnosis.classList.add('show');
        diagnosis.style.borderColor = statusCode >= 200 && statusCode < 300 ? '#b9ddc8' : '';
        diagnosis.style.borderLeftColor = statusCode >= 200 && statusCode < 300 ? '#176b45' : '';
        diagnosis.style.background = statusCode >= 200 && statusCode < 300 ? '#f4fbf6' : '';
    }

    function parseHeaders() {
        let values;
        try {
            values = JSON.parse(headersInput.value || '{}');
        } catch (error) {
            throw new Error('Headers JSON parse aagala: ' + error.message + '. Quotes, commas, and braces check pannunga.');
        }
        if (!values || Array.isArray(values) || typeof values !== 'object') {
            throw new Error('Headers object format-la irukkanum. Example: {"Accept":"application/json"}.');
        }
        const headers = new Headers();
        Object.entries(values).forEach(([name, value]) => {
            if (typeof value !== 'string' && typeof value !== 'number') {
                throw new Error('Header value text-aa irukkanum: ' + name);
            }
            headers.set(name, String(value));
        });
        if (!headers.has('Accept')) headers.set('Accept', 'application/json, text/plain, */*');
        if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', csrfToken);
        return headers;
    }

    async function readLimited(response, maxBytes) {
        if (!response.body) return { text: '', bytes: 0, truncated: false };
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        const parts = [];
        let total = 0;
        let text = '';
        let truncated = false;
        try {
            while (true) {
                const item = await reader.read();
                if (item.done) break;
                const remaining = maxBytes - total;
                if (item.value.byteLength > remaining) {
                    const part = item.value.subarray(0, Math.max(remaining, 0));
                    if (part.byteLength) parts.push(part);
                    total += part.byteLength;
                    truncated = true;
                    await reader.cancel();
                    break;
                }
                parts.push(item.value);
                total += item.value.byteLength;
            }
        } finally {
            reader.releaseLock();
        }
        const joined = new Uint8Array(total);
        let offset = 0;
        for (const part of parts) {
            joined.set(part, offset);
            offset += part.byteLength;
        }
        text = decoder.decode(joined);
        if (truncated) text += '\n\n[FrogO stopped reading after 2 MB to keep this page responsive.]';
        return { text, bytes: total, truncated };
    }

    function showLocalError(message, cause, fix) {
        emptyState.hidden = true;
        responseContent.hidden = false;
        responseHeadersContent.hidden = true;
        responseContent.textContent = message;
        statusBadge.className = 'status bad';
        statusBadge.textContent = 'REQUEST ERROR';
        duration.textContent = '— ms';
        size.textContent = '— B';
        responseLabel.textContent = 'REQUEST COULD NOT BE SENT';
        diagnosisTitle.textContent = 'WHY IT FAILED · REQUEST ERROR';
        diagnosisWhy.textContent = cause;
        diagnosisFix.textContent = fix;
        diagnosis.classList.add('show');
        copyButton.disabled = true;
        selectResponseTab('body');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        diagnosis.classList.remove('show');
        responseContent.hidden = true;
        responseHeadersContent.hidden = true;
        emptyState.hidden = false;
        statusBadge.className = 'status';
        statusBadge.textContent = 'SENDING…';
        responseLabel.textContent = 'WAITING FOR API';
        sendButton.disabled = true;
        sendButton.textContent = 'WAIT…';
        const started = performance.now();
        let responseReceived = false;

        try {
            const rawUrl = urlInput.value.trim();
            if (!rawUrl) throw new Error('URL empty-aa irukku. API path enter pannunga.');
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            const appBase = currentPath.endsWith('/frogo')
                ? currentPath.slice(0, -'/frogo'.length)
                : '';
            const alreadyIncludesBase = appBase !== '' && (rawUrl === appBase || rawUrl.startsWith(appBase + '/'));
            const resolvedUrl = rawUrl.startsWith('/') && appBase !== '' && !alreadyIncludesBase
                ? appBase + rawUrl
                : rawUrl;
            const target = new URL(resolvedUrl, window.location.href);
            if (target.origin !== window.location.origin) {
                throw new Error('FrogO same-origin request mattum allow pannum. URL path-ai /api/users maadhiri kudunga; vera domain/browser port support pannadhu.');
            }
            if (!['http:', 'https:'].includes(target.protocol)) {
                throw new Error('HTTP or HTTPS URL mattum use pannunga.');
            }
            const requestMethod = method.value;
            const headers = parseHeaders();
            const controller = new AbortController();
            requestTimeout = window.setTimeout(() => controller.abort(), 30000);
            const options = { method: requestMethod, headers, credentials: 'same-origin', redirect: 'follow', signal: controller.signal };
            if (!['GET', 'HEAD'].includes(requestMethod) && bodyInput.value.trim() !== '') {
                if (new TextEncoder().encode(bodyInput.value).byteLength > 2 * 1024 * 1024) {
                    throw new Error('Request body 2 MB limit-ai thaandiduchu. Payload-ai reduce pannunga.');
                }
                const contentType = headers.get('Content-Type') || '';
                if (contentType.toLowerCase().includes('application/json')) {
                    try { JSON.parse(bodyInput.value); } catch (error) {
                        throw new Error('Request body JSON valid illa: ' + error.message + '. Comma, quote, bracket check pannunga.');
                    }
                }
                options.body = bodyInput.value;
            }
            const response = await fetch(target.href, options);
            responseReceived = true;
            const result = await readLimited(response, 2 * 1024 * 1024);
            const elapsed = Math.round(performance.now() - started);
            const contentType = response.headers.get('content-type') || '';
            const responseHeaders = {};
            response.headers.forEach((value, key) => { responseHeaders[key] = value; });
            displayResponse(result.text, contentType);
            responseHeadersContent.textContent = Object.keys(responseHeaders).length
                ? JSON.stringify(responseHeaders, null, 2)
                : '(No readable response headers)';
            statusBadge.className = response.ok ? 'status ok' : 'status bad';
            statusBadge.textContent = response.status + ' ' + response.statusText.toUpperCase();
            duration.textContent = elapsed + ' ms';
            size.textContent = result.bytes.toLocaleString() + ' B' + (result.truncated ? '+' : '');
            responseLabel.textContent = response.ok ? 'RESPONSE RECEIVED' : 'HTTP ERROR RESPONSE';
            explain(response.status, contentType.toLowerCase(), result.text);
            selectResponseTab('body');
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            if (error instanceof DOMException && error.name === 'AbortError') {
                showLocalError('Request timed out after 30 seconds.', '30 seconds-kulla server response complete aagala.', 'Endpoint slow-aa irukka, database query stuck-aa, illa server waiting-aa-nu check pannunga. Large data-na pagination use pannunga.');
            } else if (responseReceived) {
                showLocalError('Could not read the response body. ' + message, 'Response stream read pannumbodhu browser error vandhirukku.', 'Connection stable-aa irukka check pannunga; response romba perusa irundhaal API pagination add pannunga.');
            } else if (message.includes('Failed to fetch') || message.includes('NetworkError')) {
                showLocalError(message, 'Browser server-ai reach panna mudiyala; server offline, URL mismatch, TLS issue, illa browser network block aagirukkalaam.', 'php jeep serve run aagudha check pannunga. URL same origin-oda port/path match aaganum; page-ai HTTPS-la open pannina API-yum HTTPS-la irukkanum.');
            } else {
                showLocalError(message, 'URL, method, headers, or request body validation fail aayiduchu.', 'Error message follow pannitu URL same-origin-aa, headers valid JSON-aa, body JSON-aa-nu check pannunga.');
            }
        } finally {
            window.clearTimeout(requestTimeout);
            sendButton.disabled = false;
            sendButton.innerHTML = 'SEND <span class="arrow">↗</span>';
        }
    });

    method.addEventListener('change', setMethod);
    document.getElementById('headers-tab').addEventListener('click', () => selectRequestTab('headers'));
    document.getElementById('body-tab').addEventListener('click', () => selectRequestTab('body'));
    document.getElementById('response-body-tab').addEventListener('click', () => selectResponseTab('body'));
    document.getElementById('response-headers-tab').addEventListener('click', () => selectResponseTab('headers'));
    copyButton.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(rawResponse); showToast('Response body copied.'); }
        catch { showToast('Copy failed. Select and copy the response text manually.'); }
    });
    setMethod();
})();
</script>
</body>
</html>
