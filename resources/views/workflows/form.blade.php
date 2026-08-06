@extends('layouts.app')
@section('title', $workflow->exists ? __('ui.edit_workflow') : __('ui.new_workflow'))

@section('content')
@php
    $workflowResourcesJson = json_encode(
        [
            'agents' => $agents,
            'connectors' => $connectors,
            'skills' => $skills,
            'campaigns' => $campaigns,
            'catalog' => $connectorCatalog,
        ],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    );
    $workflowDefinitionJson = json_encode(
        $workflow->definition ?: [],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    $exprInput = '{{input}}';
    $exprPrevious = '{{previous}}';
    $exprNodes = '{{nodes.node_id}}';
    $exprEmail = '{{input.email}}';
    $paletteGroups = [
        'Triggers' => [
            'manual' => __('ui.manual_trigger'),
            'schedule' => __('ui.schedule_trigger'),
            'webhook' => __('ui.webhook_trigger'),
        ],
        'AI & plugins' => [
            'agent' => __('ui.ai_agent'),
            'connector' => __('ui.plugin_action'),
            'skill' => __('ui.skill_context'),
            'approval' => __('ui.human_approval'),
        ],
        'Logic' => [
            'condition' => __('ui.condition'),
            'delay' => __('ui.delay'),
            'output' => __('ui.output'),
        ],
        'Revenue data' => [
            'lead' => __('ui.lead_action'),
            'campaign' => __('ui.campaign_action'),
        ],
    ];
    $paletteIcons = [
        'manual'   => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v4l2 2"/><circle cx="8" cy="8" r="6"/></svg>',
        'schedule' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="11" rx="2"/><path d="M5 1v3M11 1v3M2 7h12"/></svg>',
        'webhook'  => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="3"/><path d="M2 8a6 6 0 1 0 12 0"/></svg>',
        'agent'    => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>',
        'connector'=> '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 8h8M10 5l3 3-3 3"/><path d="M2 5V3h4"/><path d="M2 11v2h4"/></svg>',
        'skill'    => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2l1.8 3.6 4 .6-2.9 2.8.7 4L8 11l-3.6 1.9.7-4L2.1 6.2l4-.6z"/></svg>',
        'condition'=> '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v5"/><path d="M5 10h-2l5 4 5-4h-2V7"/></svg>',
        'delay'    => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 2"/></svg>',
        'lead'     => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="5" r="2.5"/><path d="M3 14c0-2.8 2.2-5 5-5s5 2.2 5 5"/><path d="M11 7l1.5 1.5L15 6"/></svg>',
        'campaign' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 10V6l10-4v12L2 10z"/><path d="M5.5 8.5V13"/></svg>',
        'approval' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="2"/><path d="M5 9l2 2 4-4"/></svg>',
        'output'   => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3v8M5 8l3 3 3-3"/><path d="M3 13h10"/></svg>',
    ];
@endphp

<form
    method="post"
    action="{{ $workflow->exists ? route('workflows.update', $workflow) : route('workflows.store') }}"
    data-workflow-form
>
    @csrf
    @if($workflow->exists)
        @method('PUT')
    @endif

    <div class="workflow-editor-head">
        <div>
            <span class="eyebrow">{{ __('ui.workflow_builder') }}</span>
            <input
                class="workflow-title-input"
                name="name"
                value="{{ old('name', $workflow->name ?: __('ui.untitled_workflow')) }}"
                required
                maxlength="120"
            >
            <input
                class="workflow-desc-input"
                name="description"
                value="{{ old('description', $workflow->description) }}"
                placeholder="{{ __('ui.workflow_achieve') }}"
            >
        </div>
        <div class="inline">
            <select class="select compact" name="status">
                <option value="draft" @selected(old('status', $workflow->status) === 'draft')>{{ __('ui.draft') }}</option>
                <option value="active" @selected(old('status', $workflow->status) === 'active')>{{ __('ui.active') }}</option>
                <option value="paused" @selected(old('status', $workflow->status) === 'paused')>{{ __('ui.paused') }}</option>
            </select>
            <a class="btn" href="{{ $workflow->exists ? route('workflows.show', $workflow) : route('workflows.index') }}">{{ __('ui.cancel') }}</a>
            <button class="btn btn-primary">{{ __('ui.save_workflow') }}</button>
        </div>
    </div>

    @if($errors->any())
        <div class="notice bad">
            <ul class="error-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="workflow-builder" data-workflow-builder data-resources="{{ $workflowResourcesJson }}">
        <aside class="workflow-palette">
            <strong>{{ __('ui.add_node') }}</strong>
            <p>{{ __('ui.add_node_help') }}</p>
            <input class="field workflow-palette-search" type="search" data-palette-search placeholder="Search nodes…" autocomplete="off">
            @foreach($paletteGroups as $group => $items)
                <div class="workflow-palette-group" data-palette-group>
                    <div class="workflow-palette-group-label">{{ $group }}</div>
                    @foreach($items as $type => $label)
                        <button type="button" class="workflow-palette-item" draggable="true" data-node-type="{{ $type }}" data-node-label="{{ $label }}">
                            <span>{!! $paletteIcons[$type] ?? strtoupper(substr($label, 0, 1)) !!}</span>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            @endforeach
        </aside>

        <section class="workflow-canvas-wrap">
            <div class="workflow-toolbar">
                <button type="button" class="btn btn-sm" data-workflow-zoom="out" title="Zoom out">−</button>
                <span data-workflow-zoom-label>100%</span>
                <button type="button" class="btn btn-sm" data-workflow-zoom="in" title="Zoom in">+</button>
                <button type="button" class="btn btn-sm" data-workflow-fit>{{ __('ui.fit') }}</button>
                <button type="button" class="btn btn-sm" data-workflow-auto-layout title="Arrange nodes">Auto-layout</button>
                <span class="workflow-toolbar-divider"></span>
                <span class="muted small" data-workflow-stats>0 nodes · 0 links</span>
                <span class="muted small workflow-toolbar-hint">{{ __('ui.connect_help') }} · Drag from ports · Del deletes · Esc cancels</span>
            </div>
            <div class="workflow-canvas" data-workflow-canvas>
                <svg class="workflow-lines" data-workflow-lines></svg>
                <div class="workflow-stage" data-workflow-stage></div>
                <div class="workflow-canvas-empty" data-canvas-empty hidden>
                    <strong>Build your revenue flow</strong>
                    <p>Drag nodes from the left palette, connect output → input ports, then configure each step.</p>
                </div>
            </div>
        </section>

        <aside class="workflow-inspector">
            <div data-workflow-empty>
                <strong>{{ __('ui.node_settings') }}</strong>
                <p class="muted">{{ __('ui.node_settings_help') }}</p>
                <div class="workflow-expr-help">
                    <span class="eyebrow">Templates</span>
                    <code>{{ $exprInput }}</code>
                    <code>{{ $exprPrevious }}</code>
                    <code>{{ $exprNodes }}</code>
                </div>
            </div>
            <div data-workflow-inspector hidden>
                <div class="between">
                    <strong data-node-heading>{{ __('ui.node') }}</strong>
                    <div class="inline">
                        <button type="button" class="btn btn-sm" data-node-duplicate title="Duplicate">⧉</button>
                        <button type="button" class="icon-btn" data-node-delete aria-label="{{ __('ui.delete_node') }}">×</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('ui.label') }}</label>
                    <input class="field" data-node-label-input>
                </div>
                <div data-node-fields></div>
                <div class="divider"></div>
                <div class="workflow-connect-row">
                    <button type="button" class="btn" data-node-connect>{{ __('ui.connect_to') }}</button>
                    <button type="button" class="btn btn-sm" data-edge-clear hidden>Clear links out</button>
                </div>
                <p class="help" data-connect-help></p>
                <div class="workflow-expr-help">
                    <span class="eyebrow">Insert into fields</span>
                    <button type="button" class="chip" data-expr-chip="{{ $exprInput }}">input</button>
                    <button type="button" class="chip" data-expr-chip="{{ $exprPrevious }}">previous</button>
                    <button type="button" class="chip" data-expr-chip="{{ $exprEmail }}">email</button>
                </div>
            </div>
        </aside>
    </div>

    <input type="hidden" name="definition" data-workflow-definition value="{{ old('definition', $workflowDefinitionJson) }}">

    <div class="card card-pad workflow-safety">
        <div>
            <h3>{{ __('ui.autonomy_safety') }}</h3>
            <p>{{ __('ui.workflow_safety_desc') }}</p>
        </div>
        <div class="stack">
            <label class="switch-row">
                <span>
                    <strong>{{ __('ui.autonomous_external_writes') }}</strong>
                    <small>{{ __('ui.autonomous_external_writes_help') }}</small>
                </span>
                <span class="switch">
                    <input type="checkbox" name="allow_external_writes" value="1" @checked(old('allow_external_writes', data_get($workflow->settings, 'allow_external_writes', false)))>
                    <span></span>
                </span>
            </label>
            <label class="switch-row">
                <span>
                    <strong>{{ __('ui.destructive_actions') }}</strong>
                    <small>{{ __('ui.destructive_nodes_help') }}</small>
                </span>
                <span class="switch">
                    <input type="checkbox" name="allow_destructive" value="1" @checked(old('allow_destructive', data_get($workflow->settings, 'allow_destructive', false)))>
                    <span></span>
                </span>
            </label>
        </div>
    </div>
</form>
@endsection
