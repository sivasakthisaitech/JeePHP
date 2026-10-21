<?php
declare(strict_types=1);

namespace JeePHP\Debug;

use ErrorException;
use Throwable;

/** Development-only error page and in-page console panel. */
final class DeveloperConsole
{
    private static bool $enabled = false;
    private static bool $handlingException = false;
    private static array $entries = [];

    public static function boot(string $projectRoot): void
    {
        $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));
        self::$enabled = in_array($environment, ['local', 'development', 'dev'], true)
            && filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);

        if (!self::$enabled || PHP_SAPI === 'cli') {
            return;
        }

        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        ob_start([self::class, 'decorateResponse']);
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function warning(string $message, string $suggestion = ''): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$entries[] = [
            'kind' => 'JeePHP warning',
            'message' => $message,
            'file' => '',
            'line' => 0,
            'suggestion' => $suggestion,
            'trace' => '',
        ];
    }

    public static function handleException(Throwable $error): void
    {
        if (!self::$enabled || self::$handlingException) {
            error_log((string) $error);
            return;
        }

        self::$handlingException = true;
        self::$entries[] = self::exceptionEntry($error);
        if (self::expectsJson()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ob_start([self::class, 'decorateResponse']);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $error->getMessage(), 'file' => $error->getFile(), 'line' => $error->getLine(), 'suggestion' => self::suggestion($error->getMessage()), 'trace' => $error->getTraceAsString()], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start([self::class, 'decorateResponse']);
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo self::exceptionPage($error);
    }

    public static function handleShutdown(): void
    {
        if (!self::$enabled || self::$handlingException) {
            return;
        }

        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }

        self::$handlingException = true;
        $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        self::$entries[] = self::exceptionEntry($exception);
        if (self::expectsJson()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ob_start([self::class, 'decorateResponse']);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine(), 'suggestion' => self::suggestion($exception->getMessage()), 'trace' => $exception->getTraceAsString()], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start([self::class, 'decorateResponse']);
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo self::exceptionPage($exception);
    }

    public static function decorateResponse(string $content): string
    {
        if (!self::$enabled || trim($content) === '') {
            return $content;
        }

        $contentType = '';
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = strtolower($header);
                break;
            }
        }

        if (str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/plain')
            || (!preg_match('/<!doctype\s+html|<html\b|<body\b|<main\b|<h1\b/i', $content) && !str_contains($contentType, 'text/html'))) {
            return $content;
        }

        return self::injectBeforeBodyEnd($content, self::panel());
    }

    private static function exceptionEntry(Throwable $error): array
    {
        return [
            'kind' => 'PHP ' . (new \ReflectionClass($error))->getShortName(),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'suggestion' => self::suggestion($error->getMessage()),
            'trace' => $error->getTraceAsString(),
        ];
    }

    private static function expectsJson(): bool
    {
        return str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    private static function suggestion(string $message): string
    {
        $text = strtolower($message);
        return match (true) {
            str_contains($text, 'undefined variable') => 'Spelling and declare check pannunga. Indha variable-ku value assign pannitu use pannunga.',
            str_contains($text, 'undefined array key') => 'Array-la indha key irukka check pannunga; optional value-na ?? default value use pannunga.',
            str_contains($text, 'call to undefined function') => 'Function name/autoload check pannunga. Helper file composer.json-la autoload.files-kulla irukka verify pannitu composer dump-autoload run pannunga.',
            str_contains($text, 'class') && str_contains($text, 'not found') => 'Class file path, namespace, class name capitalization check pannunga. Pudhu class-na composer dump-autoload run pannunga.',
            str_contains($text, 'call to undefined method') => 'Method name and class API check pannunga. Correct object/class use aagudha verify pannunga.',
            str_contains($text, 'view not found') => 'app/Views-kulla correct folder/name-la .php view file create pannunga; load_view() name-la .php extension poda vendam.',
            str_contains($text, 'controller not found') => 'app/Controllers path, namespace App\\Controllers, class name check pannunga.',
            str_contains($text, 'controller method not found') => 'Route-la irukkura Controller@method name, controller public method-oda exact-aa match aagudha check pannunga.',
            str_contains($text, 'sqlstate') || str_contains($text, 'database') => '.duck-la DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS check pannunga. MySQL service running-aa, table/column spelling correct-aa verify pannunga.',
            str_contains($text, 'must be of type') || str_contains($text, 'typeerror') => 'Error message-la expected type paarunga. Function-ku pass panra value type-ai validate/cast pannunga.',
            str_contains($text, 'failed to open stream') || str_contains($text, 'no such file or directory') => 'File path, filename capitalization, and file permissions check pannunga.',
            str_contains($text, 'parse error') || str_contains($text, 'syntax error') => 'Indha file/line pakkathula missing semicolon, bracket, quote, or comma irukka check pannunga.',
            default => 'Error message and code context-ai paarunga. Indha file/line-la irukkura input, variable, route, and database values verify pannunga.',
        };
    }

    private static function exceptionPage(Throwable $error): string
    {
        $entry = self::exceptionEntry($error);
        $title = self::escape($entry['kind'] . ': ' . $entry['message']);
        $file = self::escape($entry['file']);
        $line = (int) $entry['line'];
        $suggestion = self::escape($entry['suggestion']);
        $trace = self::escape($entry['trace']);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>JeePHP Development Error</title><style>' . self::styles() . '</style></head><body>'
            . '<main class="jp-error-page"><p class="jp-eyebrow">JEEPHP / DEVELOPMENT ERROR</p><h1>' . $title . '</h1>'
            . '<p class="jp-location"><code>' . $file . ':' . $line . '</code></p>'
            . '<section><h2>Why this happened</h2><p>' . $title . '</p></section>'
            . '<section><h2>What to check</h2><p>' . $suggestion . '</p></section>'
            . '<details><summary>Stack trace</summary><pre>' . $trace . '</pre></details>'
            . '<p class="jp-footnote">This detailed page is visible only when APP_ENV is local/development and APP_DEBUG=true.</p></main>'
            . '</body></html>';
    }

    private static function panel(): string
    {
        $entries = json_encode(self::$entries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        $count = count(self::$entries);
        return '<style>' . self::styles() . '</style>'
            . '<aside id="jp-console" class="jp-console" data-entries="' . self::escape((string) $entries) . '">'
            . '<button id="jp-console-toggle" class="jp-console-tab" type="button" aria-expanded="false">'
            . '<span class="jp-console-dot"></span> Console <span id="jp-console-count" class="jp-console-count">' . $count . '</span></button>'
            . '<section id="jp-console-panel" class="jp-console-panel" aria-label="JeePHP developer console" hidden>'
            . '<header><strong>JeePHP Console</strong><button id="jp-console-close" type="button" aria-label="Close console">×</button></header>'
            . '<div id="jp-console-list" class="jp-console-list"><p class="jp-console-empty">No errors in this request.</p></div>'
            . '<footer>Development mode · details shown locally</footer></section></aside>'
            . '<script>' . self::script() . '</script>';
    }

    private static function styles(): string
    {
        return '.jp-console{font:13px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace;color:#111;z-index:2147483647}'
            . '.jp-console-tab{position:fixed;right:18px;bottom:0;border:1px solid #111;border-bottom:0;border-radius:7px 7px 0 0;background:#111;color:#fff;padding:9px 13px;cursor:pointer;box-shadow:0 0 0 1px #fff inset}'
            . '.jp-console-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:7px}'
            . '.jp-console-count{display:inline-grid;place-items:center;min-width:18px;height:18px;margin-left:8px;border-radius:9px;background:#fff;color:#111;font-size:11px}'
            . '.jp-console-panel{position:fixed;right:0;bottom:0;width:min(680px,100vw);height:min(390px,75vh);background:#fff;color:#111;border:1px solid #111;box-shadow:0 0 0 100vmax #0002;display:flex;flex-direction:column}'
            . '.jp-console-panel[hidden]{display:none}.jp-console-panel header,.jp-console-panel footer{padding:10px 14px;background:#111;color:#fff;display:flex;justify-content:space-between;align-items:center}'
            . '.jp-console-panel header button{border:0;background:transparent;color:white;font-size:22px;cursor:pointer}.jp-console-list{padding:12px;overflow:auto;flex:1}'
            . '.jp-console-entry{border:1px solid #bbb;border-left:4px solid #111;margin-bottom:10px;padding:9px 11px;overflow-wrap:anywhere}'
            . '.jp-console-entry h3{font-size:13px;margin:0 0 6px}.jp-console-entry p{margin:5px 0}.jp-console-entry code,.jp-location code{white-space:pre-wrap;overflow-wrap:anywhere}'
            . '.jp-console-entry details{margin-top:8px}.jp-console-entry pre,.jp-error-page pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f1f1f1;padding:10px;max-height:180px;overflow:auto}'
            . '.jp-console-empty{color:#555}.jp-console-panel footer{font-size:11px;color:#ddd}.jp-error-page{max-width:1000px;margin:7vh auto;padding:28px;font:15px/1.6 ui-monospace,SFMono-Regular,Consolas,monospace;color:#111}'
            . '.jp-error-page h1{font-size:22px;line-height:1.35;overflow-wrap:anywhere}.jp-error-page section,.jp-error-page details{border:1px solid #aaa;padding:12px 16px;margin:14px 0}.jp-error-page h2{font-size:15px;margin:0 0 6px}.jp-location,.jp-eyebrow{color:#444}.jp-footnote{font-size:12px;color:#555}';
    }

    private static function script(): string
    {
        return <<<'JS'
(()=>{
 const root=document.getElementById('jp-console');if(!root)return;
 const toggle=document.getElementById('jp-console-toggle'),panel=document.getElementById('jp-console-panel'),close=document.getElementById('jp-console-close'),list=document.getElementById('jp-console-list'),count=document.getElementById('jp-console-count');
 let entries=[];try{entries=JSON.parse(root.dataset.entries||'[]')}catch{}
 const esc=(v)=>String(v??'');
 const add=(entry)=>{entries.push(entry);count.textContent=String(entries.length);if(list.querySelector('.jp-console-empty'))list.innerHTML='';const item=document.createElement('article');item.className='jp-console-entry';const title=document.createElement('h3');title.textContent=esc(entry.kind||'JavaScript error');const msg=document.createElement('p');msg.textContent=esc(entry.message);item.append(title,msg);if(entry.file){const loc=document.createElement('p');loc.textContent=entry.file+(entry.line?':'+entry.line:'');item.append(loc)}if(entry.suggestion){const advice=document.createElement('p');advice.textContent='Suggestion: '+entry.suggestion;item.append(advice)}if(entry.trace){const details=document.createElement('details'),summary=document.createElement('summary'),pre=document.createElement('pre');summary.textContent='Stack trace';pre.textContent=entry.trace;details.append(summary,pre);item.append(details)}list.append(item)};
 const initialEntries=entries.slice();entries=[];for(const entry of initialEntries)add(entry);
 toggle.addEventListener('click',()=>{const open=panel.hidden;panel.hidden=!open;toggle.setAttribute('aria-expanded',String(open));if(open)document.getElementById('jp-console-close').focus()});
 close.addEventListener('click',()=>{panel.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.focus()});
 window.addEventListener('error',(event)=>{if(event.target&&event.target!==window){add({kind:'Asset load error',message:'Could not load '+(event.target.src||event.target.href||'a page asset'),file:event.target.src||event.target.href||'',suggestion:'Asset URL, file path, and web-server access check pannunga.'});return}add({kind:'JavaScript error',message:event.message,file:event.filename,line:event.lineno,suggestion:'Browser console-la stack trace paarunga. Error location-la variable/function name and script load order check pannunga.',trace:event.error?.stack||''})});
 window.addEventListener('unhandledrejection',(event)=>add({kind:'Unhandled Promise',message:event.reason?.message||event.reason,file:location.href,suggestion:'Promise-la catch() add pannunga; network request status and response format check pannunga.',trace:event.reason?.stack||''}));
 const originalError=console.error.bind(console);console.error=(...args)=>{add({kind:'console.error',message:args.map(x=>typeof x==='string'?x:(x?.message||JSON.stringify(x))).join(' '),file:location.href,suggestion:'Log message-oda context and call site inspect pannunga.'});originalError(...args)};
})();
JS;
    }

    private static function injectBeforeBodyEnd(string $html, string $panel): string
    {
        if (preg_match('/<\/body\s*>/i', $html) === 1) {
            return (string) preg_replace('/<\/body\s*>/i', $panel . '</body>', $html, 1);
        }
        return $html . $panel;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
