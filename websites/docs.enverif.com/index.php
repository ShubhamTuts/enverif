<?php
declare(strict_types=1);
$pages=[
'overview'=>['Overview','content/architecture.md','Start here'],
'product-requirements'=>['Product requirements','content/PRODUCT-REQUIREMENTS.md','Start here'],
'installation'=>['Installation','content/getting-started/installation.md','Getting started'],
'first-agent'=>['First agent','content/getting-started/first-agent.md','Getting started'],
'chat'=>['Agentic chat','content/user-guide/chat.md','User guide'],
'agents'=>['Agents','content/user-guide/agents.md','User guide'],
'workflows'=>['Workflows','content/user-guide/workflows.md','User guide'],
'schedules'=>['Schedules','content/user-guide/schedules.md','User guide'],
'email'=>['Email automation','content/user-guide/email-automation.md','User guide'],
'leads-campaigns'=>['Leads & campaigns','content/user-guide/leads-campaigns.md','User guide'],
'connectors'=>['Plugins & connectors','content/user-guide/connectors.md','User guide'],
'models'=>['AI models','content/user-guide/models.md','User guide'],
'memory-delegation'=>['Memory & delegation','content/user-guide/memory-delegation.md','User guide'],
'shared-hosting'=>['Shared hosting','content/hosting/shared-hosting.md','Hosting'],
'hostinger'=>['Hostinger','content/hosting/hostinger.md','Hosting'],
'cpanel'=>['cPanel','content/hosting/cpanel.md','Hosting'],
'plesk'=>['Plesk','content/hosting/plesk.md','Hosting'],
'docker-vps'=>['Docker & VPS','content/hosting/docker-vps.md','Hosting'],
'runtime'=>['Runtime & queues','content/operations/runtime.md','Operations'],
'security'=>['Security & approvals','content/operations/security.md','Operations'],
'troubleshooting'=>['Troubleshooting','content/operations/troubleshooting.md','Operations'],
'deployment'=>['Deployment','content/operations/deployment.md','Operations'],
'skills'=>['Skills','content/extensions/skills.md','Developers'],
'plugins'=>['Plugin development','content/extensions/plugins.md','Developers'],
'mcp'=>['MCP servers','content/extensions/mcp.md','Developers'],
'core'=>['Core development','content/developers/core.md','Developers'],
'release'=>['Release process','content/developers/release.md','Developers'],
'contributing'=>['Contributing','content/contributing/index.md','Contributing'],
'translations'=>['Translations','content/contributing/translations.md','Contributing'],
];
$path=trim((string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??''),'/');
$key=(string)($_GET['page']??($path?:'overview'));
if(str_starts_with($key,'index.php'))$key=(string)($_GET['page']??'overview');
if(!isset($pages[$key])){http_response_code(404);$key='overview';}
[$title,$source,$group]=$pages[$key];
$markdown=(string)file_get_contents(__DIR__.'/'.$source);
$GLOBALS['docsLinkMap']=[];
foreach($pages as $slug=>$page){$GLOBALS['docsLinkMap'][basename($page[1])]=$slug;}
function inlineMd(string $text):string{
    $text=htmlspecialchars($text,ENT_QUOTES,'UTF-8');
    $text=preg_replace('/`([^`]+)`/','<code>$1</code>',$text)??$text;
    $text=preg_replace('/\*\*([^*]+)\*\*/','<strong>$1</strong>',$text)??$text;
    $text=preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/',static function(array $m):string{
        $href=html_entity_decode($m[2],ENT_QUOTES,'UTF-8');
        if(preg_match('/\.md(?:#.*)?$/i',$href)){
            [$file,$fragment]=array_pad(explode('#',$href,2),2,null);
            $slug=$GLOBALS['docsLinkMap'][basename($file)]??null;
            if($slug!==null){$href='/'.$slug.($fragment!==null&&$fragment!==''?'#'.rawurlencode($fragment):'');}
        }
        $external=preg_match('#^https?://#i',$href)===1;
        return '<a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'"'.($external?' target="_blank" rel="noopener"':'').'>'.$m[1].'</a>';
    },$text)??$text;
    return $text;
}
function renderTable(array $rows):string{if(count($rows)<2)return ''; $parse=static fn(string $r)=>array_map('trim',explode('|',trim($r," |\t")));$head=$parse($rows[0]);$body=array_slice($rows,2);$html='<div class="table-scroll"><table><thead><tr>';foreach($head as $cell)$html.='<th>'.inlineMd($cell).'</th>';$html.='</tr></thead><tbody>';foreach($body as $row){$html.='<tr>';foreach($parse($row) as $cell)$html.='<td>'.inlineMd($cell).'</td>';$html.='</tr>';}$html.='</tbody></table></div>';return $html;}
function renderMd(string $md):string{$lines=preg_split('/\R/',$md)?:[];$html='';$inCode=false;$code=[];$list=null;$table=[];$closeList=function()use(&$html,&$list):void{if($list){$html.="</{$list}>";$list=null;}};$flushTable=function()use(&$html,&$table):void{if($table){$html.=renderTable($table);$table=[];}};foreach($lines as $line){if(str_starts_with($line,'```')){$flushTable();$closeList();if($inCode){$html.='<pre><code>'.htmlspecialchars(implode("\n",$code),ENT_QUOTES,'UTF-8').'</code></pre>';$code=[];$inCode=false;}else{$inCode=true;}continue;}if($inCode){$code[]=$line;continue;}if(str_starts_with(trim($line),'|')){$closeList();$table[]=$line;continue;}$flushTable();if(preg_match('/^(#{1,4})\s+(.+)$/',$line,$m)){$closeList();$n=strlen($m[1]);$id=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$m[2])??'', '-'));$html.="<h{$n} id=\"{$id}\">".inlineMd($m[2])."</h{$n}>";continue;}if(preg_match('/^-\s+(.+)$/',$line,$m)){if($list!=='ul'){$closeList();$html.='<ul>';$list='ul';}$html.='<li>'.inlineMd($m[1]).'</li>';continue;}if(preg_match('/^\d+\.\s+(.+)$/',$line,$m)){if($list!=='ol'){$closeList();$html.='<ol>';$list='ol';}$html.='<li>'.inlineMd($m[1]).'</li>';continue;}$closeList();if(trim($line)==='')continue;if(str_starts_with($line,'> ')){$html.='<blockquote>'.inlineMd(substr($line,2)).'</blockquote>';continue;}$html.='<p>'.inlineMd($line).'</p>';}$flushTable();$closeList();if($inCode&&$code)$html.='<pre><code>'.htmlspecialchars(implode("\n",$code),ENT_QUOTES,'UTF-8').'</code></pre>';return $html;}
$groups=[];foreach($pages as $slug=>$page)$groups[$page[2]][$slug]=$page[0];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?> · Enverif Docs</title><meta name="description" content="Complete Enverif documentation for installation, shared hosting, revenue agents, workflows, email automation, plugins and contributors."><link rel="icon" href="/assets/enverif-mark.svg"><link rel="stylesheet" href="/assets/docs.css"></head><body><div class="layout"><aside><a class="brand" href="/overview"><img src="/assets/enverif-mark.svg" alt=""><span><b>Enverif</b><small>Documentation</small></span></a><div class="search"><input type="search" placeholder="Filter docs…" oninput="document.querySelectorAll('[data-nav]').forEach(a=>a.hidden=!a.textContent.toLowerCase().includes(this.value.toLowerCase()))"></div><nav><?php foreach($groups as $g=>$items):?><div class="nav-group"><strong><?=htmlspecialchars($g)?></strong><?php foreach($items as $slug=>$label):?><a data-nav class="<?=$slug===$key?'active':''?>" href="/<?=htmlspecialchars($slug)?>"><?=htmlspecialchars($label)?></a><?php endforeach;?></div><?php endforeach;?></nav><div class="aside-foot"><a href="https://github.com/ShubhamTuts/enverif">GitHub repository ↗</a><a href="/contributing">Contribute to Enverif</a><span>MIT · Maintained by Codefreex</span></div></aside><main><header><button class="mobile" onclick="document.body.classList.toggle('nav-open')">☰</button><span><?=htmlspecialchars($group)?> <i>/</i> <?=htmlspecialchars($title)?></span><div><a href="/installation">Install</a><a class="github" href="https://github.com/ShubhamTuts/enverif">GitHub ↗</a></div></header><article><?=renderMd($markdown)?><footer><span>Was this useful? Open a GitHub issue for missing documentation.</span><span>Maintained by Codefreex</span></footer></article></main></div></body></html>
