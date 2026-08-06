<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$docsRoot = $root . '/docs';
$out = $root . '/site';

function rrmdir(string $path): void
{
    if (!is_dir($path)) return;
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . '/' . $item;
        is_dir($full) ? rrmdir($full) : unlink($full);
    }
    rmdir($path);
}

function ensureDir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to create {$path}");
    }
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function titleFromMarkdown(string $markdown, string $fallback): string
{
    if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) return trim($m[1]);
    return ucwords(str_replace(['-', '_'], ' ', $fallback));
}

function groupName(string $relative): string
{
    $segment = explode('/', $relative)[0] ?? 'docs';
    return match ($segment) {
        'getting-started' => 'Getting started',
        'user-guide' => 'User guide',
        'hosting' => 'Hosting',
        'operations' => 'Operations',
        'extensions' => 'Extensions',
        'developers' => 'Developers',
        'contributing' => 'Contributing',
        default => 'Overview',
    };
}

function pageHref(string $relative): string
{
    if ($relative === 'architecture.md') return 'architecture.html';
    return preg_replace('/\.md$/', '.html', $relative) ?? $relative;
}

function inlineMd(string $text, string $sourceRelative): string
{
    $tokens = [];
    $tokenize = static function (string $html) use (&$tokens): string {
        $key = '%%ENVERIF' . count($tokens) . '%%';
        $tokens[$key] = $html;
        return $key;
    };

    $text = preg_replace_callback('/`([^`]+)`/', static fn(array $m): string => $tokenize('<code>' . esc($m[1]) . '</code>'), $text) ?? $text;
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $m) use ($sourceRelative, $tokenize): string {
        $label = esc($m[1]);
        $href = trim($m[2]);
        if (preg_match('/\.md(?:#.*)?$/', $href)) {
            [$file, $fragment] = array_pad(explode('#', $href, 2), 2, null);
            $href = preg_replace('/\.md$/', '.html', $file) ?? $file;
            if ($fragment !== null && $fragment !== '') $href .= '#' . rawurlencode($fragment);
        }
        $external = preg_match('#^https?://#i', $href) === 1;
        $attrs = $external ? ' target="_blank" rel="noopener"' : '';
        return $tokenize('<a href="' . esc($href) . '"' . $attrs . '>' . $label . '</a>');
    }, $text) ?? $text;

    $text = esc($text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    foreach ($tokens as $key => $html) $text = str_replace(esc($key), $html, $text);
    return $text;
}

function renderTable(array $rows, string $sourceRelative): string
{
    if (count($rows) < 2) return '';
    $parse = static fn(string $row): array => array_map('trim', explode('|', trim($row, " |\t")));
    $head = $parse($rows[0]);
    $body = array_slice($rows, 2);
    $html = '<div class="table-scroll"><table><thead><tr>';
    foreach ($head as $cell) $html .= '<th>' . inlineMd($cell, $sourceRelative) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($body as $row) {
        $html .= '<tr>';
        foreach ($parse($row) as $cell) $html .= '<td>' . inlineMd($cell, $sourceRelative) . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function renderMarkdown(string $markdown, string $sourceRelative): string
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $inCode = false;
    $code = [];
    $codeLanguage = '';
    $list = null;
    $table = [];
    $paragraph = [];

    $closeList = static function () use (&$html, &$list): void {
        if ($list !== null) { $html .= "</{$list}>"; $list = null; }
    };
    $flushTable = static function () use (&$html, &$table, $sourceRelative): void {
        if ($table !== []) { $html .= renderTable($table, $sourceRelative); $table = []; }
    };
    $flushParagraph = static function () use (&$html, &$paragraph, $sourceRelative): void {
        if ($paragraph !== []) { $html .= '<p>' . inlineMd(implode(' ', array_map('trim', $paragraph)), $sourceRelative) . '</p>'; $paragraph = []; }
    };

    foreach ($lines as $line) {
        if (preg_match('/^```([^`]*)$/', trim($line), $m)) {
            $flushParagraph(); $flushTable(); $closeList();
            if ($inCode) {
                $lang = $codeLanguage !== '' ? ' class="language-' . esc($codeLanguage) . '"' : '';
                $html .= '<pre><code' . $lang . '>' . esc(implode("\n", $code)) . '</code></pre>';
                $code = []; $codeLanguage = ''; $inCode = false;
            } else {
                $inCode = true; $codeLanguage = preg_replace('/[^a-z0-9_+-]/i', '', trim($m[1])) ?? '';
            }
            continue;
        }
        if ($inCode) { $code[] = $line; continue; }

        if (str_starts_with(trim($line), '|')) {
            $flushParagraph(); $closeList(); $table[] = $line; continue;
        }
        $flushTable();

        if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
            $flushParagraph(); $closeList();
            $level = strlen($m[1]);
            $plain = trim(preg_replace('/[`*_]/', '', $m[2]) ?? $m[2]);
            $id = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $plain) ?? '', '-'));
            $html .= "<h{$level} id=\"" . esc($id) . "\">" . inlineMd($m[2], $sourceRelative) . "</h{$level}>";
            continue;
        }
        if (preg_match('/^-\s+(.+)$/', $line, $m)) {
            $flushParagraph();
            if ($list !== 'ul') { $closeList(); $html .= '<ul>'; $list = 'ul'; }
            $html .= '<li>' . inlineMd($m[1], $sourceRelative) . '</li>';
            continue;
        }
        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            $flushParagraph();
            if ($list !== 'ol') { $closeList(); $html .= '<ol>'; $list = 'ol'; }
            $html .= '<li>' . inlineMd($m[1], $sourceRelative) . '</li>';
            continue;
        }

        $closeList();
        if (trim($line) === '') { $flushParagraph(); continue; }
        if (str_starts_with($line, '> ')) {
            $flushParagraph();
            $html .= '<blockquote>' . inlineMd(substr($line, 2), $sourceRelative) . '</blockquote>';
            continue;
        }
        $paragraph[] = $line;
    }

    $flushParagraph(); $flushTable(); $closeList();
    if ($inCode && $code !== []) $html .= '<pre><code>' . esc(implode("\n", $code)) . '</code></pre>';
    return $html;
}

function relativePrefix(string $relativeHtml): string
{
    $depth = substr_count($relativeHtml, '/');
    return str_repeat('../', $depth + 1);
}

function docsShell(string $title, string $group, string $body, string $relativeHtml, array $pages): string
{
    $prefix = relativePrefix($relativeHtml);
    $current = $relativeHtml;
    $nav = '';
    $groups = [];
    foreach ($pages as $page) $groups[$page['group']][] = $page;
    foreach ($groups as $name => $items) {
        $nav .= '<div class="nav-group"><strong>' . esc($name) . '</strong>';
        foreach ($items as $page) {
            $href = $prefix . 'docs/' . $page['href'];
            $active = $page['href'] === $current ? ' class="active"' : '';
            $nav .= '<a data-nav' . $active . ' href="' . esc($href) . '">' . esc($page['title']) . '</a>';
        }
        $nav .= '</div>';
    }
    $canonical = 'https://shubhamtuts.github.io/enverif/docs/' . $relativeHtml;
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . esc($title) . ' · Enverif Docs</title><meta name="description" content="Enverif documentation for self-hosting, revenue agents, workflows, email automation, plugins, skills and contributors.">'
        . '<link rel="canonical" href="' . esc($canonical) . '"><link rel="icon" href="' . esc($prefix . 'assets/enverif-mark.svg') . '"><link rel="stylesheet" href="' . esc($prefix . 'assets/docs.css') . '"></head><body>'
        . '<div class="docs-layout"><aside class="docs-aside"><a class="docs-brand" href="' . esc($prefix) . '"><img src="' . esc($prefix . 'assets/enverif-mark.svg') . '" alt="Enverif"><span><b>Enverif</b><small>Docs · by Codefreex</small></span></a>'
        . '<div class="docs-search"><input type="search" placeholder="Filter documentation…" aria-label="Filter documentation" oninput="document.querySelectorAll(\'[data-nav]\').forEach(a=>a.hidden=!a.textContent.toLowerCase().includes(this.value.toLowerCase()))"></div><nav>' . $nav . '</nav>'
        . '<div class="docs-aside-foot"><a href="https://github.com/ShubhamTuts/enverif" target="_blank" rel="noopener">GitHub repository ↗</a><a href="' . esc($prefix . 'docs/contributing/index.html') . '">Contribute to Enverif</a><span>MIT · Enverif by Codefreex</span></div></aside>'
        . '<main class="docs-main"><header class="docs-header"><button class="docs-mobile" type="button" onclick="document.body.classList.toggle(\'docs-nav-open\')">☰</button><span>' . esc($group) . ' <i>/</i> ' . esc($title) . '</span><div><a href="' . esc($prefix . 'docs/getting-started/installation.html') . '">Install</a><a href="https://github.com/ShubhamTuts/enverif" target="_blank" rel="noopener">GitHub ↗</a></div></header>'
        . '<article class="docs-article">' . $body . '<footer class="docs-footer"><span>Missing something? Open a documentation issue or pull request.</span><span>Enverif by Codefreex</span></footer></article></main></div><script>document.addEventListener("click",e=>{if(innerWidth<=900&&e.target.closest("[data-nav]"))document.body.classList.remove("docs-nav-open")})</script></body></html>';
}

function marketingHtml(): string
{
    $year = date('Y');
    return <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enverif — Open-source revenue agents that actually operate</title><meta name="description" content="Enverif by Codefreex is an open-source, self-hosted AI revenue-agent operating system with agentic chat, visual workflows, Gmail/Outlook/SMTP automation, leads, schedules, skills, plugins and approval-first execution."><link rel="canonical" href="https://shubhamtuts.github.io/enverif/"><meta property="og:title" content="Enverif — The open-source agent operating system for revenue"><meta property="og:description" content="Research, qualify, automate and coordinate revenue work from one agentic workspace. Self-hosted, BYOK and MIT licensed."><meta property="og:type" content="website"><meta property="og:url" content="https://shubhamtuts.github.io/enverif/"><meta property="og:image" content="https://shubhamtuts.github.io/enverif/assets/social-preview.svg"><meta name="twitter:card" content="summary_large_image"><link rel="icon" href="assets/enverif-mark.svg"><link rel="stylesheet" href="assets/site.css"><script type="application/ld+json">{"@context":"https://schema.org","@type":"SoftwareApplication","name":"Enverif","applicationCategory":"BusinessApplication","operatingSystem":"Web","license":"https://opensource.org/license/mit","url":"https://github.com/ShubhamTuts/enverif","creator":{"@type":"Organization","name":"Codefreex"},"description":"Open-source self-hosted agent operating system for revenue."}</script></head><body>
<nav class="site-nav"><a class="site-brand" href="./"><img src="assets/enverif-mark.svg" alt="Enverif"><span><strong>Enverif</strong><small>by Codefreex</small></span></a><div class="site-links"><a href="#product">Product</a><a href="docs/index.html">Docs</a><a href="#hosting">Self-host</a><a href="docs/contributing/index.html">Contribute</a><a class="site-nav-cta" href="https://github.com/ShubhamTuts/enverif" target="_blank" rel="noopener">Star on GitHub ↗</a></div></nav>
<main><section class="site-hero"><div class="site-kicker"><span></span> OPEN SOURCE · SELF-HOSTED · BYOK</div><h1>Your revenue team,<br><em>operating as agents.</em></h1><p>One ChatGPT-like workspace for research, prospecting, qualification, follow-up, schedules and durable workflows — with Gmail, Outlook, SMTP, plugins, skills and human approval exactly where external actions need it.</p><div class="site-actions"><a class="site-button primary" href="docs/getting-started/installation.html">Install Enverif</a><a class="site-button" href="https://github.com/ShubhamTuts/enverif" target="_blank" rel="noopener">Explore source</a></div><div class="site-proof"><span>Laravel 13</span><span>Redis optional</span><span>Shared-host ready</span><span>MIT licensed</span><span>English · Français · Nederlands</span></div></section>
<section class="product-shell" aria-label="Enverif agentic chat preview"><aside><div class="mini-brand"><img src="assets/enverif-mark.svg" alt=""><b>Enverif</b></div><button>＋ New chat</button><small>Workspace</small><a>Agents</a><a>Schedules</a><a>Leads</a><a>Campaigns</a><a>Skills</a><a>Plugins</a><a>Workflows</a><div class="mini-bottom"><a>Help & docs</a><a>Settings</a><span>● Operator</span></div></aside><div class="product-chat"><div class="preview-top"><span>Revenue workspace</span><span>Approval queue · 0</span></div><div class="preview-center"><img src="assets/enverif-mark.svg" alt=""><h2>What should the revenue team do?</h2><p>Choose an agent, model and effort; add @plugins, @skills, @workflows or private files; then give Enverif the outcome.</p><div class="prompt-pills"><span>Research 50 qualified companies</span><span>Build a follow-up workflow</span><span>Review replies and update leads</span></div></div><div class="preview-composer"><div><b>@SDR Agent</b><b>@Apollo</b><b>@Gmail</b></div><p>Find high-intent prospects, enrich decision makers, draft personalized outreach and prepare each send for approval.</p><span>＋ Context · Attach files · Model · Effort <i>↗</i></span></div></div></section>
<section class="stack-strip"><span>PLUG INTO YOUR REVENUE STACK</span><div><b>G</b> Gmail <b>O</b> Outlook <b>SMTP</b> Mail <b>AP</b> Apify <b>AO</b> Apollo <b>GA</b> Analytics <b>GSC</b> Search Console <b>C</b> Calendly <b>MCP</b> MCP <b>↗</b> n8n · Zapier · Make</div></section>
<section class="site-section" id="product"><div class="site-heading"><span>FROM PROMPT TO OPERATION</span><h2>Not another chatbot. A durable operating layer for revenue work.</h2><p>Enverif keeps the conversational interface simple while execution remains persisted, permissioned, inspectable and resumable underneath.</p></div><div class="feature-cards"><article><b>01</b><h3>Agentic chat</h3><p>Searchable persistent threads remember agent, model and effort. Override per message, add private files and structured @context from the composer.</p></article><article><b>02</b><h3>Visual workflows</h3><p>Connect triggers, agents, connectors, conditions, delays, approvals, leads and campaigns, then validate with dry runs, retries and per-node inspection.</p></article><article><b>03</b><h3>Email automation</h3><p>Gmail OAuth, Microsoft Outlook OAuth and SMTP. Draft freely; external sends require approval by default.</p></article><article><b>04</b><h3>Durable agents</h3><p>MySQL-backed runs snapshot instructions, model, effort, skills, connector permissions and workflow definitions so edits never rewrite in-flight work.</p></article><article><b>05</b><h3>Revenue workspace</h3><p>Leads, provenance, activity history, campaigns, recurring schedules and reusable sales skills stay in one system.</p></article><article><b>06</b><h3>Open extension layer</h3><p>First-party plugins by Codefreex plus external connectors, SKILL.md playbooks and remote MCP servers.</p></article></div></section>
<section class="site-split" id="hosting"><div><span class="site-label">RUN IT ALMOST ANYWHERE</span><h2>VPS speed when you have Redis.<br>Shared-host simplicity when you don’t.</h2><p>Enverif adapts to the capabilities of the server instead of making Redis and permanent queue workers mandatory.</p><ul><li><strong>Performance mode</strong> — Redis queue/cache and persistent workers.</li><li><strong>Shared mode</strong> — MySQL queue/cache and one bounded cron command.</li><li><strong>Compatibility mode</strong> — signed Web Cron for hosts without CLI cron.</li><li>Root domain, subdomain and subfolder installations.</li><li>Apache routing and sensitive-path protection included.</li></ul><a class="site-text-link" href="docs/hosting/shared-hosting.html">Shared-hosting guide →</a></div><div class="terminal-card"><div><i></i><i></i><i></i><span>System health</span></div><pre><strong>Runtime</strong>             Shared hosting ✓
<strong>Queue</strong>               Database ✓
<strong>Cache</strong>               Database ✓
<strong>Scheduler heartbeat</strong> 34 seconds ago ✓
<strong>Redis</strong>               Optional / unavailable
<strong>Storage</strong>             Writable ✓

php /home/account/enverif/artisan enverif:tick</pre></div></section>
<section class="site-split reverse"><div class="workflow-card"><div class="wf-node trigger"><small>TRIGGER</small><b>New lead</b></div><span>→</span><div class="wf-node"><small>AGENT</small><b>Research & score</b></div><span>→</span><div class="wf-node"><small>CONDITION</small><b>Fit ≥ 80?</b></div><span>→</span><div class="wf-node approval"><small>APPROVAL</small><b>Send email</b></div></div><div><span class="site-label">CONTROLLED AUTONOMY</span><h2>Let agents work. Keep irreversible actions deliberate.</h2><p>Read, research, enrichment and internal organization can stay autonomous. Email sends and other external writes ask by default. Secrets always ask. Destructive actions never become silent.</p><a class="site-text-link" href="docs/operations/security.html">Read the safety model →</a></div></section>
<section class="site-open"><div><span class="site-label">BUILT IN THE OPEN</span><h2>Extend Enverif instead of waiting for a roadmap.</h2><p>Contribute to the core, publish a connector, create a sales skill, improve translations or harden the docs. The first-party integration layer is maintained by Codefreex; third-party extensions retain their own attribution.</p><div class="site-actions left"><a class="site-button primary" href="docs/contributing/index.html">Contributor guide</a><a class="site-button" href="docs/developers/core.html">Developer docs</a></div></div><div class="code-card"><div><i></i><i></i><i></i><span>enverif.plugin/v1</span></div><pre>{
  "name": "Your CRM",
  "developer": "Your team",
  "type": "connector",
  "capabilities": [
    "read",
    "network",
    "external_write"
  ]
}</pre></div></section>
<section class="site-cta"><img src="assets/enverif-mark.svg" alt=""><h2>Build an AI revenue team you can inspect, control and own.</h2><p>Start with one agent. Add your models and email. Then turn repeatable revenue work into durable workflows.</p><div class="site-actions"><a class="site-button primary" href="docs/getting-started/installation.html">Deploy Enverif</a><a class="site-button" href="https://github.com/ShubhamTuts/enverif" target="_blank" rel="noopener">GitHub ↗</a></div></section></main>
<footer class="site-footer"><a class="site-brand" href="./"><img src="assets/enverif-mark.svg" alt=""><span><strong>Enverif</strong><small>by Codefreex</small></span></a><p>The open-source agent operating system for revenue.</p><div><a href="docs/index.html">Documentation</a><a href="docs/contributing/index.html">Contribute</a><a href="https://github.com/ShubhamTuts/enverif">GitHub</a><span>MIT © {$year} Codefreex and Enverif Contributors</span></div></footer></body></html>
HTML;
}

rrmdir($out);
ensureDir($out . '/assets');
ensureDir($out . '/docs');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot, FilesystemIterator::SKIP_DOTS));
$pages = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($docsRoot) + 1));
    $markdown = (string) file_get_contents($file->getPathname());
    $pages[] = [
        'relative' => $relative,
        'href' => pageHref($relative),
        'title' => titleFromMarkdown($markdown, pathinfo($relative, PATHINFO_FILENAME)),
        'group' => groupName($relative),
        'markdown' => $markdown,
    ];
}
usort($pages, static fn(array $a, array $b): int => [$a['group'], $a['title']] <=> [$b['group'], $b['title']]);

file_put_contents($out . '/index.html', marketingHtml());
file_put_contents($out . '/.nojekyll', '');
copy($root . '/public/assets/enverif-mark.svg', $out . '/assets/enverif-mark.svg');
copy($root . '/websites/enverif.com/assets/site.css', $out . '/assets/site.css');
copy($root . '/websites/docs.enverif.com/assets/docs.css', $out . '/assets/docs.css');

foreach ($pages as $page) {
    $destination = $out . '/docs/' . $page['href'];
    ensureDir(dirname($destination));
    $body = renderMarkdown($page['markdown'], $page['relative']);
    file_put_contents($destination, docsShell($page['title'], $page['group'], $body, $page['href'], $pages));
}

$cards = [
    ['Install Enverif', 'Server requirements, installer, native hosting and first boot.', 'getting-started/installation.html'],
    ['Shared hosting', 'Hostinger, cPanel, Plesk, database queues and cron.', 'hosting/shared-hosting.html'],
    ['Agentic chat', 'Tag agents, plugins, skills and workflows from one composer.', 'user-guide/chat.html'],
    ['Visual workflows', 'Build durable automations with agents, connectors and approvals.', 'user-guide/workflows.html'],
    ['Email automation', 'Gmail, Outlook and SMTP with approval-first sends.', 'user-guide/email-automation.html'],
    ['Contributing', 'Core, plugins, skills, translations, documentation and releases.', 'contributing/index.html'],
];
$cardHtml = '';
foreach ($cards as [$name, $copy, $href]) $cardHtml .= '<a class="docs-home-card" href="' . esc($href) . '"><strong>' . esc($name) . '</strong><span>' . esc($copy) . '</span><b>Read →</b></a>';
$docsIndex = '<div class="docs-home-hero"><span>ENVERIF DOCUMENTATION</span><h1>Operate, extend and deploy Enverif with confidence.</h1><p>Complete operator and contributor documentation for the agentic workspace, workflows, email, hosting modes, safety and extension system.</p><div class="docs-home-actions"><a href="getting-started/installation.html">Install Enverif</a><a href="contributing/index.html">Start contributing</a></div></div><div class="docs-home-grid">' . $cardHtml . '</div>';
file_put_contents($out . '/docs/index.html', docsShell('Documentation', 'Start here', $docsIndex, 'index.html', $pages));

$social = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630"><defs><linearGradient id="bg" x1="100" y1="0" x2="1100" y2="630"><stop stop-color="#080b12"/><stop offset="1" stop-color="#11162a"/></linearGradient><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#7c5cff"/><stop offset=".52" stop-color="#5668ff"/><stop offset="1" stop-color="#20c7aa"/></linearGradient><filter id="blur"><feGaussianBlur stdDeviation="60"/></filter></defs><rect width="1200" height="630" rx="36" fill="url(#bg)"/><circle cx="980" cy="90" r="180" fill="#655fff" opacity=".18" filter="url(#blur)"/><circle cx="160" cy="580" r="180" fill="#1fc7aa" opacity=".11" filter="url(#blur)"/><g transform="translate(82 83)"><rect width="92" height="92" rx="28" fill="url(#g)"/><path d="M28 25h38v13H41v10h21v13H41v17H28V25Z" fill="white"/><circle cx="69" cy="25" r="10" fill="#baffeb"/><path d="m64 25 3.4 3.5 7-7.5" fill="none" stroke="#116958" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></g><text x="200" y="135" fill="white" font-family="Inter,Arial,sans-serif" font-size="46" font-weight="750">Enverif</text><text x="202" y="169" fill="#8f9ab2" font-family="Inter,Arial,sans-serif" font-size="20">by Codefreex</text><text x="82" y="300" fill="white" font-family="Inter,Arial,sans-serif" font-size="66" font-weight="760" letter-spacing="-2">The open-source agent</text><text x="82" y="376" fill="white" font-family="Inter,Arial,sans-serif" font-size="66" font-weight="760" letter-spacing="-2">operating system for revenue.</text><text x="84" y="444" fill="#9ca7bc" font-family="Inter,Arial,sans-serif" font-size="25">Agentic chat · Workflows · Gmail/Outlook/SMTP · Shared hosting · BYOK</text><g transform="translate(82 515)" font-family="Inter,Arial,sans-serif" font-size="18" font-weight="650"><rect width="185" height="46" rx="23" fill="#fff" fill-opacity=".08" stroke="#fff" stroke-opacity=".12"/><text x="27" y="30" fill="#e8ebff">MIT licensed</text><rect x="204" width="245" height="46" rx="23" fill="#fff" fill-opacity=".08" stroke="#fff" stroke-opacity=".12"/><text x="232" y="30" fill="#e8ebff">Self-hosted · BYOK</text></g></svg>
SVG;
file_put_contents($out . '/assets/social-preview.svg', $social);

file_put_contents($out . '/robots.txt', "User-agent: *\nAllow: /\nSitemap: https://shubhamtuts.github.io/enverif/sitemap.xml\n");
$urls = ['https://shubhamtuts.github.io/enverif/'];
foreach ($pages as $page) $urls[] = 'https://shubhamtuts.github.io/enverif/docs/' . $page['href'];
$urls[] = 'https://shubhamtuts.github.io/enverif/docs/index.html';
$sitemap = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as $url) $sitemap .= '<url><loc>' . esc($url) . '</loc></url>';
$sitemap .= '</urlset>';
file_put_contents($out . '/sitemap.xml', $sitemap);
file_put_contents($out . '/llms.txt', "# Enverif\n\nEnverif by Codefreex is an MIT-licensed, self-hosted agent operating system for revenue.\n\n- Documentation: https://shubhamtuts.github.io/enverif/docs/index.html\n- Installation: https://shubhamtuts.github.io/enverif/docs/getting-started/installation.html\n- GitHub: https://github.com/ShubhamTuts/enverif\n- Contributing: https://shubhamtuts.github.io/enverif/docs/contributing/index.html\n");

echo 'Built GitHub Pages site: ' . count($pages) . " docs pages + homepage\n";
