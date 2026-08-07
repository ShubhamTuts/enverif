<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" data-user-theme="{{ auth()->user()->theme ?? 'system' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Enverif') · Enverif</title>
@php
    $assetVersion = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev';
    $runtimeAssetMtime = @filemtime(public_path('assets/runtime-ui.js'));
    $runtimeAssetVersion = $runtimeAssetMtime ? $assetVersion.'-'.$runtimeAssetMtime : $assetVersion;
@endphp
<link rel="icon" href="{{ asset('assets/enverif-mark.svg') }}?v={{ rawurlencode($assetVersion) }}">
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ rawurlencode($assetVersion) }}">
<link rel="stylesheet" href="{{ asset('assets/runtime-ui.css') }}?v={{ rawurlencode($assetVersion) }}">
<script>(()=>{const t=localStorage.getItem('enverif-theme')||'{{ auth()->user()->theme ?? 'system' }}';document.documentElement.dataset.theme=t==='system'?(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'):t})()</script>
</head>
<body class="@yield('body-class')" data-runtime-feed-url="{{ route('runtime.feed') }}" data-runtime-workspace="{{ $currentWorkspace->id }}">
@php
$sidebarChats=\App\Models\ChatThread::where('user_id',auth()->id())->whereNull('archived_at')->orderByDesc('last_message_at')->limit(30)->get();
$today=now()->startOfDay();
$sidebarGroups=$sidebarChats->groupBy(function($chat) use ($today){$at=$chat->last_message_at?:$chat->created_at;if($at->gte($today))return 'Today';if($at->gte($today->copy()->subDay()))return 'Yesterday';if($at->gte($today->copy()->subDays(7)))return 'Previous 7 days';return 'Older';});
$currentRole=(string)($currentWorkspace->pivot?->role ?? 'member');
$canAdmin=in_array($currentRole,['owner','admin'],true);
$pendingActionCount=$canAdmin?\App\Models\Approval::where('status','pending')->count():0;
$nav=[
 ['agents.index','agents',__('ui.agents'),'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM4.5 21c.7-5 3.2-7.5 7.5-7.5s6.8 2.5 7.5 7.5'],
 ['schedules.index','schedules',__('ui.schedules'),'M6 2v4m12-4v4M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Zm3 9h3v3H8v-3Z'],
 ['leads.index','leads',__('ui.leads'),'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2.5 21c.6-4.8 2.8-7.2 6.5-7.2 2.1 0 3.7.7 4.8 2M18 11v8m-4-4h8'],
 ['campaigns.index','campaigns',__('ui.campaigns'),'M4 5h9M4 12h6M4 19h9m4-14 3 3-3 3m0 5 3 3-3 3'],
 ['skills.index','skills',__('ui.skills'),'M9 3h6v4H9V3ZM6 9h12v12H6V9Zm4 4h4m-4 4h4'],
 ['connectors.index','connectors',__('ui.plugins'),'M8 12H5a3 3 0 0 0 0 6h3m8-6h3a3 3 0 1 1 0 6h-3M8 15h8M10 12V8a2 2 0 1 1 4 0v4'],
 ['workflows.index','workflows',__('ui.workflows'),'M5 5h5v5H5V5Zm9 9h5v5h-5v-5ZM10 7.5h4a3 3 0 0 1 3 3V14M7.5 10v4a3 3 0 0 0 3 3H14'],
];
@endphp
<div class="mobile-overlay"></div>
<div class="app-shell agentic-shell">
<aside class="sidebar agentic-sidebar">
  <div class="sidebar-top">
    <a class="brand brand-modern" href="{{ route('chat.index') }}" aria-label="Enverif home"><img src="{{ asset('assets/enverif-mark.svg') }}?v={{ rawurlencode($assetVersion) }}" alt=""><span><strong>Enverif</strong></span></a>
    <a class="new-chat" href="{{ route('chat.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg><span>{{ __('ui.new_chat') }}</span><kbd>⌘ K</kbd></a>
  </div>
  <div class="sidebar-scroll">
    <div class="nav-label">{{ __('ui.chats') }}</div>
    <form class="sidebar-chat-search" method="get" action="{{ route('chat.index') }}" role="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg>
      <input name="q" value="{{ request()->routeIs('chat.*') ? request('q') : '' }}" placeholder="Search chats" aria-label="Search chat history" autocomplete="off">
      @if(request()->routeIs('chat.*') && request()->filled('q'))<a href="{{ route('chat.index') }}" aria-label="Clear search">×</a>@endif
    </form>
    @if($sidebarChats->isNotEmpty())
      <nav class="chat-history-nav">@foreach(['Today','Yesterday','Previous 7 days','Older'] as $group)@if(($sidebarGroups[$group]??collect())->isNotEmpty())<div class="chat-history-group">{{ $group }}</div>@foreach($sidebarGroups[$group] as $chat)<a href="{{ route('chat.show',$chat) }}" data-chat-history-thread="{{ $chat->id }}" class="{{ request()->routeIs('chat.show') && request()->route('thread')?->id===$chat->id?'active':'' }}"><span class="chat-dot"></span><span class="truncate">{{ $chat->title }}</span></a>@endforeach @endif @endforeach</nav>
      <div class="chat-history-links"><a href="{{ route('chat.index',['archived'=>1]) }}">Archived</a></div>
    @endif
    <div class="nav-label">{{ __('ui.workspace') }}</div><nav class="nav agentic-nav">
      @foreach($nav as [$route,$section,$label,$path])
        @continue(!$canAdmin && !in_array($section,['leads'],true))
        <a href="{{ route($route) }}" class="{{ request()->routeIs($section.'.*')?'active':'' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="{{ $path }}"/></svg><span>{{ $label }}</span></a>
      @endforeach
      @if($canAdmin)<details class="nav-more" @if(request()->routeIs('dashboard','models.*','mcp.*','approvals.*','action-center.*','audit.*')) open @endif><summary><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg><span>{{ __('ui.more') }}</span><span class="chev">⌄</span></summary><div class="nav-more-menu"><a href="{{ route('dashboard') }}">{{ __('ui.overview') }}</a><a href="{{ route('models.index') }}">{{ __('ui.ai_models') }}</a><a href="{{ route('mcp.index') }}">{{ __('ui.mcp_servers') }}</a><a href="{{ route('action-center.index') }}">Action Center</a><a href="{{ route('approvals.index') }}">{{ __('ui.approvals') }}</a><a href="{{ route('audit.index') }}">{{ __('ui.audit_log') }}</a></div></details>@endif
    </nav>
  </div>
  <div class="sidebar-bottom agentic-bottom">
    <a class="bottom-link" href="https://shubhamtuts.github.io/enverif/" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.5 2.5 0 1 1 3.7 2.2c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>{{ __('ui.help_docs') }}</a>
    <a class="bottom-link {{ request()->routeIs('settings.*')?'active':'' }}" href="{{ route('settings.edit') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.5 1a8 8 0 0 0-1.7-1L14.4 3h-4.8l-.3 3.1a8 8 0 0 0-1.7 1l-2.5-1-2 3.4L5.1 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.5-1a8 8 0 0 0 1.7 1l.3 3.1h4.8l.3-3.1a8 8 0 0 0 1.7-1l2.5 1 2-3.4-2-1.5a7 7 0 0 0 .1-1Z"/></svg>{{ __('ui.settings') }}</a>
    <div class="account-row"><span class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span class="account-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="{{ __('ui.logout') }}" aria-label="{{ __('ui.logout') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10 5H5v14h5m4-4 4-3-4-3m4 3H9"/></svg></button></form></div>
  </div>
</aside>
<main class="main agentic-main">
<header class="topbar agentic-topbar"><div class="top-left"><button class="icon-btn mobile-menu" data-mobile-menu aria-label="{{ __('ui.menu') }}">☰</button><span class="crumb" data-page-crumb>@yield('crumb','Enverif')</span></div><div class="top-actions"><form method="post" action="{{ route('workspace.switch') }}">@csrf<select class="workspace-select" name="workspace_id" data-auto-submit aria-label="{{ __('ui.workspace') }}">@foreach($availableWorkspaces as $workspace)<option value="{{ $workspace->id }}" @selected($currentWorkspace->id===$workspace->id)>{{ $workspace->name }}</option>@endforeach</select></form>@if($canAdmin)<a class="icon-btn approval-shortcut action-center-shortcut" href="{{ route('action-center.index') }}" title="Action Center" data-action-center-url="{{ route('action-center.index') }}" data-pending-count="{{ $pendingActionCount }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/></svg>@if($pendingActionCount>0)<span class="action-center-badge" data-action-center-badge>{{ $pendingActionCount>99?'99+':$pendingActionCount }}</span>@endif</a>@endif<button class="icon-btn" data-theme-toggle title="{{ __('ui.theme') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9Z"/></svg></button></div></header>
<div class="content @yield('content-class')">@if(session('status'))<div class="notice good">✓ <span>{{ session('status') }}</span></div>@endif @if(session('error'))<div class="notice bad">! <span>{{ session('error') }}</span></div>@endif @if($errors->any())<div class="notice bad"><div><strong>{{ __('ui.fix_errors') }}</strong><ul class="error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif @yield('content')</div>
</main></div>
<div class="command-dialog"><div class="command-box"><input class="field command-input" data-command-input placeholder="{{ __('ui.jump_anything') }}"><div class="command-results">@foreach([['chat.index',__('ui.new_chat')],['agents.index',__('ui.agents')],['schedules.index',__('ui.schedules')],['leads.index',__('ui.leads')],['campaigns.index',__('ui.campaigns')],['skills.index',__('ui.skills')],['connectors.index',__('ui.plugins')],['workflows.index',__('ui.workflows')],['settings.edit',__('ui.settings')]] as [$route,$label])@continue(!$canAdmin && !in_array($route,['chat.index','leads.index'],true))<a href="{{ route($route) }}" data-command-item><span>{{ $label }}</span><span class="muted">→</span></a>@endforeach</div></div></div>
<script src="{{ asset('assets/app.js') }}?v={{ rawurlencode($assetVersion) }}" defer></script><script src="{{ asset('assets/runtime-ui.js') }}?v={{ rawurlencode($runtimeAssetVersion) }}" defer></script>@stack('scripts')</body></html>