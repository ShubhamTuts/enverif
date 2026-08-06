const root=document.documentElement;
const preferred=localStorage.getItem('enverif-theme')||root.dataset.userTheme||'system';
function resolvedTheme(theme){return theme==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):theme}
function setTheme(theme){localStorage.setItem('enverif-theme',theme);root.dataset.theme=resolvedTheme(theme);root.dataset.userTheme=theme;document.querySelectorAll('[data-theme-label]').forEach(x=>x.textContent=theme)}
setTheme(preferred);
matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if((localStorage.getItem('enverif-theme')||'system')==='system')setTheme('system')});
document.addEventListener('click',e=>{const t=e.target.closest('[data-theme-toggle]');if(t){const current=localStorage.getItem('enverif-theme')||'system';setTheme(current==='system'?'dark':current==='dark'?'light':'system')}const menu=e.target.closest('[data-mobile-menu]');if(menu){document.querySelector('.sidebar')?.classList.toggle('open');document.querySelector('.mobile-overlay')?.classList.toggle('open')}if(e.target.matches('.mobile-overlay')){document.querySelector('.sidebar')?.classList.remove('open');e.target.classList.remove('open')}const tab=e.target.closest('[data-tab]');if(tab){const group=tab.dataset.tabGroup;document.querySelectorAll(`[data-tab-group="${group}"]`).forEach(x=>x.classList.remove('active'));document.querySelectorAll(`[data-tab-panel-group="${group}"]`).forEach(x=>x.classList.remove('active'));tab.classList.add('active');document.querySelector(`[data-tab-panel="${tab.dataset.tab}"]`)?.classList.add('active')}});
const dialog=document.querySelector('.command-dialog');const commandInput=document.querySelector('[data-command-input]');function command(open){if(!dialog)return;dialog.classList.toggle('open',open);if(open)setTimeout(()=>commandInput?.focus(),30)}document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();command(true)}if(e.key==='Escape')command(false)});document.querySelector('[data-command-open]')?.addEventListener('click',()=>command(true));dialog?.addEventListener('click',e=>{if(e.target===dialog)command(false)});commandInput?.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('[data-command-item]').forEach(x=>x.classList.toggle('hidden',!x.textContent.toLowerCase().includes(q))) });
document.querySelectorAll('[data-auto-submit]').forEach(el=>el.addEventListener('change',()=>el.form?.submit()));
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm||'Are you sure?'))e.preventDefault()}));
window.Enverif={setTheme};
// Hosting-aware installer wizard.
(()=>{
    const shell=document.querySelector('[data-installer]');
    if(!shell)return;
    const steps=[...shell.querySelectorAll('[data-install-step]')];
    const progress=[...shell.querySelectorAll('[data-install-jump]')];
    const next=shell.querySelector('[data-install-next]');
    const back=shell.querySelector('[data-install-back]');
    const submit=shell.querySelector('[data-install-submit]');
    const modelStep=shell.querySelector('[data-install-model-catalog]');
    const providerSelect=shell.querySelector('[data-install-provider]');
    const modelSelect=shell.querySelector('[data-install-model]');
    const customModel=shell.querySelector('[data-install-custom-model]');
    const customWrap=shell.querySelector('[data-install-custom-model-wrap]');
    let catalog={};
    try{catalog=JSON.parse(modelStep?.dataset.installModelCatalog||'{}')}catch(_){catalog={}}
    let index=Math.max(0,steps.findIndex(x=>x.querySelector('.field[name="db_host"]')&&document.querySelector('.error-list')));
    const escapeOption=(value)=>String(value??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    function syncRedis(){const selected=shell.querySelector('input[name="runtime_mode"]:checked')?.value;const box=shell.querySelector('[data-redis-fields]');if(box)box.style.opacity=selected==='performance'?'1':'.52'}
    function syncCustomModel(){const custom=modelSelect?.value==='__custom__';if(customWrap)customWrap.hidden=!custom;if(customModel){customModel.required=custom;customModel.disabled=!custom}}
    function syncModels(preserve=true){
        if(!providerSelect||!modelSelect)return;
        const provider=providerSelect.value;
        const previous=preserve?modelSelect.value:'';
        const models=Array.isArray(catalog?.[provider])?catalog[provider]:[];
        modelSelect.innerHTML='<option value="">Use provider default</option>'+models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model IDâ€¦</option>';
        modelSelect.disabled=!provider;
        if(provider&&[...modelSelect.options].some(option=>option.value===previous))modelSelect.value=previous;
        syncCustomModel();
    }
    function show(i){index=Math.max(0,Math.min(steps.length-1,i));steps.forEach((x,n)=>x.classList.toggle('active',n===index));progress.forEach((x,n)=>x.classList.toggle('active',n===index));back.hidden=index===0;next.hidden=index===steps.length-1;submit.hidden=index!==steps.length-1;scrollTo({top:0,behavior:'smooth'});syncRedis()}
    function valid(){const fields=[...steps[index].querySelectorAll('input[required],select[required]')];for(const field of fields){if(!field.checkValidity()){field.reportValidity();return false}}return true}
    next?.addEventListener('click',()=>{if(valid())show(index+1)});
    back?.addEventListener('click',()=>show(index-1));
    progress.forEach(btn=>btn.addEventListener('click',()=>{const target=Number(btn.dataset.installJump);if(target<=index||valid())show(target)}));
    shell.querySelectorAll('input[name="runtime_mode"]').forEach(x=>x.addEventListener('change',syncRedis));
    providerSelect?.addEventListener('change',()=>syncModels(false));
    modelSelect?.addEventListener('change',syncCustomModel);
    syncModels(true);
    show(0);
})();
// Provider-aware AI model selectors.
document.querySelectorAll('[data-model-catalog]').forEach((form)=>{
    let catalog={};
    try{catalog=JSON.parse(form.dataset.modelCatalog||'{}')}catch(_){catalog={}}
    const provider=form.querySelector('[data-model-provider]');
    const model=form.querySelector('[data-model-select]');
    const custom=form.querySelector('[data-model-custom]');
    const customWrap=form.querySelector('[data-model-custom-wrap]');
    if(!provider||!model)return;
    const escapeOption=(value)=>String(value??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    function syncCustom(){const enabled=model.value==='__custom__';if(customWrap)customWrap.hidden=!enabled;if(custom){custom.required=enabled;custom.disabled=!enabled}}
    function populate(preserve=true){const previous=preserve?model.value:'';const models=Array.isArray(catalog?.[provider.value])?catalog[provider.value]:[];model.innerHTML=models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model IDâ€¦</option>';if(preserve&&[...model.options].some(option=>option.value===previous))model.value=previous;syncCustom()}
    provider.addEventListener('change',()=>populate(false));
    model.addEventListener('change',syncCustom);
    populate(true);
});

// Agent model override selector follows the selected model connection provider.
document.querySelectorAll('[data-agent-model-catalog]').forEach((form)=>{
    let catalog={};
    try{catalog=JSON.parse(form.dataset.agentModelCatalog||'{}')}catch(_){catalog={}}
    const connection=form.querySelector('[data-agent-model-connection]');
    const model=form.querySelector('[data-agent-model-select]');
    const custom=form.querySelector('[data-agent-custom-model]');
    const customWrap=form.querySelector('[data-agent-custom-model-wrap]');
    if(!connection||!model)return;
    const escapeOption=(value)=>String(value??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    function syncCustom(){const enabled=model.value==='__custom__';if(customWrap)customWrap.hidden=!enabled;if(custom){custom.required=enabled;custom.disabled=!enabled}}
    function populate(preserve=true){const option=connection.options[connection.selectedIndex];const provider=option?.dataset.provider||'';const previous=preserve?model.value:'';const models=Array.isArray(catalog?.[provider])?catalog[provider]:[];model.innerHTML='<option value="">Use connection default</option>'+models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model IDâ€¦</option>';model.disabled=!connection.value;if(preserve&&[...model.options].some(item=>item.value===previous))model.value=previous;syncCustom()}
    connection.addEventListener('change',()=>populate(false));
    model.addEventListener('change',syncCustom);
    populate(true);
});

// Durable visual workflow studio (n8n-style canvas).
(() => {
    const root = document.querySelector('[data-workflow-builder]');
    if (!root) return;

    const hidden = document.querySelector('[data-workflow-definition]');
    const stage = root.querySelector('[data-workflow-stage]');
    const canvas = root.querySelector('[data-workflow-canvas]');
    const svg = root.querySelector('[data-workflow-lines]');
    const empty = root.querySelector('[data-workflow-empty]');
    const canvasEmpty = root.querySelector('[data-canvas-empty]');
    const inspector = root.querySelector('[data-workflow-inspector]');
    const fieldBox = root.querySelector('[data-node-fields]');
    const labelField = root.querySelector('[data-node-label-input]');
    const heading = root.querySelector('[data-node-heading]');
    const connectHelp = root.querySelector('[data-connect-help]');
    const connectButton = root.querySelector('[data-node-connect]');
    const clearEdgesButton = root.querySelector('[data-edge-clear]');
    const statsLabel = root.querySelector('[data-workflow-stats]');
    const GRID = 22;

    let resources = {};
    try {
        resources = JSON.parse(root.dataset.resources || '{}');
    } catch (_) {
        resources = {};
    }

    let definition = {nodes: [], edges: []};
    try {
        definition = JSON.parse(hidden?.value || '{}');
    } catch (_) {
        definition = {nodes: [], edges: []};
    }
    definition.nodes = Array.isArray(definition.nodes) ? definition.nodes : [];
    definition.edges = Array.isArray(definition.edges) ? definition.edges : [];

    let selectedId = null;
    let selectedEdgeIndex = null;
    let connecting = null;
    let scale = 1;
    let drag = null;
    let pan = null;

    const labels = {
        manual: 'Manual trigger',
        schedule: 'Schedule trigger',
        webhook: 'Webhook trigger',
        agent: 'AI agent',
        connector: 'Plugin action',
        skill: 'Skill context',
        condition: 'Condition',
        delay: 'Delay',
        lead: 'Lead action',
        campaign: 'Campaign action',
        approval: 'Human approval',
        output: 'Output',
    };
    const nodeIcons = {
        manual: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v4l2 2"/><circle cx="8" cy="8" r="6"/></svg>',
        schedule: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="11" rx="2"/><path d="M5 1v3M11 1v3M2 7h12"/></svg>',
        webhook: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 8a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M2 8a6 6 0 1 0 12 0"/><path d="M8 2v2M8 12v2M2 8H0M16 8h-2"/></svg>',
        agent: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>',
        connector: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 8h8M10 5l3 3-3 3"/><path d="M2 5V3h4"/><path d="M2 11v2h4"/></svg>',
        skill: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2l1.8 3.6 4 .6-2.9 2.8.7 4L8 11l-3.6 1.9.7-4L2.1 6.2l4-.6z"/></svg>',
        condition: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v6l4 4M8 8l-4 4"/><circle cx="8" cy="2" r="1.2"/></svg>',
        delay: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 2"/></svg>',
        lead: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="5" r="2.5"/><path d="M3 14c0-2.8 2.2-5 5-5s5 2.2 5 5"/><path d="M11 7l1.5 1.5L15 6"/></svg>',
        campaign: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 10V6l10-4v12L2 10z"/><path d="M2 10l3.5-1.5"/><path d="M5.5 8.5V13"/></svg>',
        approval: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="2"/><path d="M5 9l2 2 4-4"/></svg>',
        output: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3v8M5 8l3 3 3-3"/><path d="M3 13h10"/></svg>',
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
    }[character]));

    const makeId = (type) => `${type}_${Math.random().toString(36).slice(2, 9)}`;
    const snap = (value) => Math.round(value / GRID) * GRID;

    function defaultConfig(type) {
        const agentId = resources.agents?.[0]?.id || '';
        const connectionId = resources.connectors?.[0]?.id || '';
        const skillId = resources.skills?.[0]?.id || '';
        const campaignId = resources.campaigns?.[0]?.id || '';

        return {
            agent: {agent_id: agentId, prompt: '{{input.prompt}}'},
            connector: {connection_id: connectionId, action: '', arguments: {}},
            skill: {skill_id: skillId},
            condition: {path: 'input.score', operator: 'gte', value: 80},
            delay: {seconds: 60},
            lead: {operation: 'upsert', fields: {email: '{{input.email}}', company: '{{input.company}}'}},
            campaign: {campaign_id: campaignId, lead_id: '{{previous.lead_id}}'},
            approval: {summary: 'Approve the next revenue action'},
            output: {value: '{{previous}}'},
        }[type] || {};
    }

    function selectedNode() {
        return definition.nodes.find((node) => node.id === selectedId) || null;
    }

    function nodeById(id) {
        return definition.nodes.find((node) => node.id === id) || null;
    }

    function updateStats() {
        if (statsLabel) {
            statsLabel.textContent = `${definition.nodes.length} nodes Â· ${definition.edges.length} links`;
        }
        if (canvasEmpty) {
            canvasEmpty.hidden = definition.nodes.length > 0;
        }
    }

    function sync() {
        if (hidden) hidden.value = JSON.stringify(definition);
        renderEdges();
        updateStats();
    }

    function addNode(type, x = 160 + canvas.scrollLeft / scale, y = 160 + canvas.scrollTop / scale, autoLink = true) {
        const node = {
            id: makeId(type),
            type,
            label: labels[type] || type,
            config: defaultConfig(type),
            position: {
                x: Math.max(GRID, snap(x)),
                y: Math.max(GRID, snap(y)),
            },
        };
        definition.nodes.push(node);
        if (autoLink && selectedId && selectedId !== node.id) {
            const source = nodeById(selectedId);
            if (source && source.type !== 'condition' && !['manual', 'schedule', 'webhook'].includes(node.type)) {
                const port = 'default';
                if (!definition.edges.some((edge) => edge.from === selectedId && (edge.port || 'default') === port)) {
                    definition.edges.push({from: selectedId, to: node.id, port});
                }
            }
        }
        selectedId = node.id;
        selectedEdgeIndex = null;
        renderNodes();
        inspectNode();
        sync();
    }

    function renderNodes() {
        stage.innerHTML = '';
        for (const node of definition.nodes) {
            const element = document.createElement('div');
            element.className = [
                'workflow-node',
                node.id === selectedId ? 'selected' : '',
                connecting && node.id !== connecting.from ? 'connect-target' : '',
            ].filter(Boolean).join(' ');
            element.dataset.nodeId = node.id;
            element.style.left = `${node.position?.x || 80}px`;
            element.style.top = `${node.position?.y || 80}px`;
            const ports = node.type === 'condition'
                ? `<button type="button" class="workflow-node-port out true" data-port-out="true" title="True"></button><button type="button" class="workflow-node-port out false" data-port-out="false" title="False"></button>`
                : `<button type="button" class="workflow-node-port out" data-port-out="default" title="Connect"></button>`;
            element.innerHTML = `
                <button type="button" class="workflow-node-port in" data-port-in title="Input"></button>
                <div class="workflow-node-head">
                    <span class="workflow-node-icon">${nodeIcons[node.type] || escapeHtml((labels[node.type] || node.type).slice(0, 1).toUpperCase())}</span>
                    <div>
                        <div class="workflow-node-label">${escapeHtml(node.label || labels[node.type] || node.type)}</div>
                        <div class="workflow-node-type">${escapeHtml(labels[node.type] || node.type)}</div>
                    </div>
                </div>
                ${ports}`;
            stage.appendChild(element);
        }
        renderEdges();
        updateStats();
    }

    function renderEdges() {
        svg.setAttribute('viewBox', '0 0 2200 1400');
        svg.style.transform = `scale(${scale})`;
        stage.style.transform = `scale(${scale})`;

        let output = '<defs><marker id="wf-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor"/></marker></defs>';
        definition.edges.forEach((edge, index) => {
            const source = nodeById(edge.from);
            const target = nodeById(edge.to);
            if (!source || !target) return;

            const port = edge.port || 'default';
            const x1 = (source.position?.x || 0) + 194;
            let y1 = (source.position?.y || 0) + 38;
            if (source.type === 'condition') {
                y1 = (source.position?.y || 0) + (port === 'false' ? 56 : 24);
            }
            const x2 = target.position?.x || 0;
            const y2 = (target.position?.y || 0) + 38;
            const curve = Math.max(70, Math.abs(x2 - x1) * 0.45);
            const path = `M ${x1} ${y1} C ${x1 + curve} ${y1}, ${x2 - curve} ${y2}, ${x2} ${y2}`;
            const selected = selectedEdgeIndex === index ? ' selected' : '';
            output += `<path class="workflow-edge ${escapeHtml(port)}${selected}" data-edge-index="${index}" d="${path}" marker-end="url(#wf-arrow)"/>`;
            if (port !== 'default') {
                output += `<text class="workflow-edge-label" x="${(x1 + x2) / 2}" y="${(y1 + y2) / 2 - 7}">${escapeHtml(port)}</text>`;
            }
        });
        svg.innerHTML = output;
    }

    function optionList(items, value, label = 'name') {
        return (items || []).map((item) => `<option value="${escapeHtml(item.id)}" ${String(item.id) === String(value) ? 'selected' : ''}>${escapeHtml(item[label] || item.name)}</option>`).join('');
    }

    function inputField(label, key, value, type = 'text') {
        return `<div class="form-group"><label class="form-label">${escapeHtml(label)}</label><input class="field" type="${type}" data-config="${escapeHtml(key)}" value="${escapeHtml(value)}"></div>`;
    }

    function textArea(label, key, value) {
        const text = typeof value === 'string' ? value : JSON.stringify(value ?? {}, null, 2);
        return `<div class="form-group"><label class="form-label">${escapeHtml(label)}</label><textarea class="textarea" data-config="${escapeHtml(key)}">${escapeHtml(text)}</textarea></div>`;
    }

    function branchButtons(node) {
        if (node.type !== 'condition') return '';
        return `<div class="workflow-connect-buttons"><button type="button" class="btn btn-sm" data-connect-port="true">Connect true</button><button type="button" class="btn btn-sm" data-connect-port="false">Connect false</button></div>`;
    }

    function inspectNode() {
        const node = selectedNode();
        empty.hidden = Boolean(node);
        inspector.hidden = !node;
        if (!node) return;

        heading.textContent = labels[node.type] || node.type;
        labelField.value = node.label || '';
        const config = node.config || (node.config = {});
        let html = '';

        if (node.type === 'agent') {
            html = `<div class="form-group"><label class="form-label">Agent</label><select class="select" data-config="agent_id">${optionList(resources.agents, config.agent_id)}</select></div>${textArea('Prompt', 'prompt', config.prompt || '{{input.prompt}}')}`;
        } else if (node.type === 'connector') {
            html = `<div class="form-group"><label class="form-label">Plugin connection</label><select class="select" data-config="connection_id" data-connector-select>${optionList(resources.connectors, config.connection_id)}</select></div><div class="form-group"><label class="form-label">Action</label><select class="select" data-config="action" data-action-select></select></div>${textArea('Arguments JSON', 'arguments', config.arguments || {})}<label class="switch-row"><span><strong>Always require approval</strong><small>Force an approval even when autonomous external writes are enabled.</small></span><label class="switch"><input type="checkbox" data-config-bool="requires_approval" ${config.requires_approval ? 'checked' : ''}><span></span></label></label>`;
        } else if (node.type === 'skill') {
            html = `<div class="form-group"><label class="form-label">Skill</label><select class="select" data-config="skill_id">${optionList(resources.skills, config.skill_id)}</select></div><div class="form-group"><label class="form-label">Executor agent</label><select class="select" data-config="agent_id"><option value="">First active agent</option>${optionList(resources.agents, config.agent_id)}</select></div>${textArea('Prompt', 'prompt', config.prompt || '{{input.prompt}}')}`;
        } else if (node.type === 'condition') {
            html = `${inputField('Context path', 'path', config.path || 'input.score')}<div class="form-group"><label class="form-label">Operator</label><select class="select" data-config="operator">${['equals', 'not_equals', 'contains', 'gt', 'gte', 'lt', 'lte', 'exists'].map((operator) => `<option value="${operator}" ${config.operator === operator ? 'selected' : ''}>${operator.replace('_', ' ')}</option>`).join('')}</select></div>${inputField('Value', 'value', config.value ?? '')}${branchButtons(node)}`;
        } else if (node.type === 'delay') {
            html = inputField('Delay seconds', 'seconds', config.seconds || 60, 'number');
        } else if (node.type === 'lead') {
            html = `<div class="form-group"><label class="form-label">Operation</label><select class="select" data-config="operation"><option value="upsert" ${config.operation === 'upsert' ? 'selected' : ''}>Upsert lead</option><option value="activity" ${config.operation === 'activity' ? 'selected' : ''}>Add activity</option></select></div>${textArea('Lead fields / activity config JSON', '__config_json', config)}`;
        } else if (node.type === 'campaign') {
            html = `<div class="form-group"><label class="form-label">Campaign</label><select class="select" data-config="campaign_id">${optionList(resources.campaigns, config.campaign_id)}</select></div>${inputField('Lead ID or template', 'lead_id', config.lead_id || '{{previous.lead_id}}')}`;
        } else if (node.type === 'approval') {
            html = textArea('Approval summary', 'summary', config.summary || 'Approve the next revenue action');
        } else if (node.type === 'output') {
            html = textArea('Output value / template', 'value', config.value ?? '{{previous}}');
        } else {
            html = '<p class="help">This trigger starts the workflow and does not require additional configuration.</p>';
        }

        fieldBox.innerHTML = html;
        if (node.type === 'connector') refreshActions(node);
        connectButton.hidden = node.type === 'condition';
        connectButton.textContent = connecting ? 'Connectingâ€¦' : 'Connect toâ€¦';
        if (clearEdgesButton) {
            clearEdgesButton.hidden = !definition.edges.some((edge) => edge.from === node.id);
        }
        connectHelp.textContent = connecting
            ? `Click an input port (or node) for the ${connecting.port} branch. Esc cancels.`
            : 'Drag from the green output port, or use Connect. Click a link to select, Del removes it.';
        bindFields(node);
    }

    function refreshActions(node) {
        const connection = resources.connectors?.find((item) => String(item.id) === String(node.config.connection_id));
        const actions = resources.catalog?.[connection?.driver]?.actions || [];
        const select = fieldBox.querySelector('[data-action-select]');
        if (!select) return;

        select.innerHTML = '<option value="">Choose action</option>' + actions.map((action) => `<option value="${escapeHtml(action.name)}" ${node.config.action === action.name ? 'selected' : ''}>${escapeHtml(action.name)} Â· ${escapeHtml(action.risk)}</option>`).join('');
    }

    function bindFields(node) {
        fieldBox.querySelectorAll('[data-config]').forEach((element) => {
            element.addEventListener('change', () => {
                const key = element.dataset.config;
                if (key === '__config_json') {
                    try {
                        node.config = JSON.parse(element.value);
                        element.setCustomValidity('');
                    } catch (_) {
                        element.setCustomValidity('Enter valid JSON');
                        element.reportValidity();
                        return;
                    }
                } else {
                    let value = element.value;
                    if (element.type === 'number') value = Number(value);
                    if (key === 'arguments') {
                        try {
                            value = JSON.parse(value || '{}');
                            element.setCustomValidity('');
                        } catch (_) {
                            element.setCustomValidity('Enter valid JSON');
                            element.reportValidity();
                            return;
                        }
                    }
                    node.config[key] = value;
                }

                if (key === 'connection_id') {
                    node.config.action = '';
                    inspectNode();
                }
                sync();
            });
        });

        fieldBox.querySelectorAll('[data-config-bool]').forEach((element) => {
            element.addEventListener('change', () => {
                node.config[element.dataset.configBool] = element.checked;
                sync();
            });
        });

        fieldBox.querySelectorAll('[data-connect-port]').forEach((button) => {
            button.addEventListener('click', () => beginConnection(button.dataset.connectPort));
        });
    }

    function beginConnection(port = 'default') {
        if (!selectedId) return;
        connecting = {from: selectedId, port};
        selectedEdgeIndex = null;
        renderNodes();
        inspectNode();
    }

    function finishConnection(toId) {
        if (!connecting || toId === connecting.from) return;
        definition.edges = definition.edges.filter((edge) => !(edge.from === connecting.from && (edge.port || 'default') === connecting.port));
        definition.edges.push({from: connecting.from, to: toId, port: connecting.port});
        connecting = null;
        selectedId = toId;
        selectedEdgeIndex = null;
        renderNodes();
        inspectNode();
        sync();
    }

    function deleteSelected() {
        if (selectedEdgeIndex !== null) {
            definition.edges.splice(selectedEdgeIndex, 1);
            selectedEdgeIndex = null;
            renderNodes();
            inspectNode();
            sync();
            return;
        }
        if (!selectedId) return;
        definition.nodes = definition.nodes.filter((node) => node.id !== selectedId);
        definition.edges = definition.edges.filter((edge) => edge.from !== selectedId && edge.to !== selectedId);
        selectedId = null;
        connecting = null;
        renderNodes();
        inspectNode();
        sync();
    }

    function autoLayout() {
        const triggers = definition.nodes.filter((node) => ['manual', 'schedule', 'webhook'].includes(node.type));
        const rest = definition.nodes.filter((node) => !['manual', 'schedule', 'webhook'].includes(node.type));
        const columns = [];
        const placed = new Set();
        let frontier = triggers.map((node) => node.id);
        if (frontier.length === 0 && definition.nodes[0]) frontier = [definition.nodes[0].id];
        while (frontier.length) {
            columns.push([...frontier]);
            frontier.forEach((id) => placed.add(id));
            const next = [];
            for (const from of columns[columns.length - 1]) {
                for (const edge of definition.edges) {
                    if (edge.from === from && !placed.has(edge.to) && !next.includes(edge.to)) next.push(edge.to);
                }
            }
            frontier = next;
        }
        for (const node of rest) {
            if (!placed.has(node.id)) {
                columns.push([node.id]);
                placed.add(node.id);
            }
        }
        columns.forEach((column, col) => {
            column.forEach((id, row) => {
                const node = nodeById(id);
                if (!node) return;
                node.position = {x: 80 + col * 240, y: 80 + row * 120};
            });
        });
        renderNodes();
        sync();
    }

    labelField.addEventListener('input', () => {
        const node = selectedNode();
        if (!node) return;
        node.label = labelField.value;
        renderNodes();
        sync();
    });

    root.querySelector('[data-node-delete]')?.addEventListener('click', deleteSelected);

    root.querySelector('[data-node-duplicate]')?.addEventListener('click', () => {
        const node = selectedNode();
        if (!node) return;
        const copy = {
            id: makeId(node.type),
            type: node.type,
            label: `${node.label || labels[node.type]} copy`,
            config: JSON.parse(JSON.stringify(node.config || {})),
            position: {
                x: snap((node.position?.x || 80) + 40),
                y: snap((node.position?.y || 80) + 40),
            },
        };
        definition.nodes.push(copy);
        selectedId = copy.id;
        renderNodes();
        inspectNode();
        sync();
    });

    connectButton.addEventListener('click', () => beginConnection('default'));

    clearEdgesButton?.addEventListener('click', () => {
        if (!selectedId) return;
        definition.edges = definition.edges.filter((edge) => edge.from !== selectedId);
        renderNodes();
        inspectNode();
        sync();
    });

    root.querySelectorAll('[data-expr-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const active = fieldBox.querySelector('textarea, input.field:not([type=number])');
            if (!active) return;
            const insert = chip.dataset.exprChip || '';
            const start = active.selectionStart ?? active.value.length;
            const end = active.selectionEnd ?? start;
            active.value = `${active.value.slice(0, start)}${insert}${active.value.slice(end)}`;
            active.dispatchEvent(new Event('change', {bubbles: true}));
            active.focus();
        });
    });

    stage.addEventListener('pointerdown', (event) => {
        const portOut = event.target.closest('[data-port-out]');
        const portIn = event.target.closest('[data-port-in]');
        const element = event.target.closest('.workflow-node');
        if (!element) return;
        const id = element.dataset.nodeId;

        if (portOut) {
            selectedId = id;
            beginConnection(portOut.dataset.portOut || 'default');
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        if (portIn && connecting) {
            finishConnection(id);
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        if (connecting && id !== connecting.from) {
            finishConnection(id);
            return;
        }

        selectedId = id;
        selectedEdgeIndex = null;
        renderNodes();
        inspectNode();
        const node = nodeById(id);
        drag = {
            node,
            startX: event.clientX,
            startY: event.clientY,
            x: node.position?.x || 0,
            y: node.position?.y || 0,
        };
        element.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });

    svg.addEventListener('pointerdown', (event) => {
        const path = event.target.closest('[data-edge-index]');
        if (!path) return;
        selectedEdgeIndex = Number(path.dataset.edgeIndex);
        selectedId = null;
        connecting = null;
        renderNodes();
        empty.hidden = false;
        inspector.hidden = true;
        event.stopPropagation();
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (event.target.closest('.workflow-node') || event.target.closest('[data-edge-index]')) return;
        if (event.button === 1 || event.shiftKey || event.target === canvas || event.target === canvasEmpty) {
            pan = {startX: event.clientX, startY: event.clientY, left: canvas.scrollLeft, top: canvas.scrollTop};
            canvas.setPointerCapture?.(event.pointerId);
            if (event.button === 1) event.preventDefault();
        }
        if (!event.target.closest('.workflow-node') && connecting) {
            connecting = null;
            renderNodes();
            inspectNode();
        }
    });

    window.addEventListener('pointermove', (event) => {
        if (pan) {
            canvas.scrollLeft = pan.left - (event.clientX - pan.startX);
            canvas.scrollTop = pan.top - (event.clientY - pan.startY);
            return;
        }
        if (!drag) return;
        drag.node.position.x = Math.max(GRID, snap(drag.x + (event.clientX - drag.startX) / scale));
        drag.node.position.y = Math.max(GRID, snap(drag.y + (event.clientY - drag.startY) / scale));
        const element = stage.querySelector(`[data-node-id="${CSS.escape(drag.node.id)}"]`);
        if (element) {
            element.style.left = `${drag.node.position.x}px`;
            element.style.top = `${drag.node.position.y}px`;
        }
        renderEdges();
    });

    window.addEventListener('pointerup', () => {
        if (pan) {
            pan = null;
            return;
        }
        if (!drag) return;
        drag = null;
        sync();
    });

    window.addEventListener('keydown', (event) => {
        if (!root.isConnected) return;
        const tag = (event.target?.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) return;
        if (event.key === 'Escape' && connecting) {
            connecting = null;
            renderNodes();
            inspectNode();
        }
        if ((event.key === 'Delete' || event.key === 'Backspace') && (selectedId || selectedEdgeIndex !== null)) {
            event.preventDefault();
            deleteSelected();
        }
    });

    root.querySelectorAll('[data-node-type]').forEach((button) => {
        button.addEventListener('click', () => addNode(button.dataset.nodeType));
        button.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/enverif-node', button.dataset.nodeType));
    });

    root.querySelector('[data-palette-search]')?.addEventListener('input', (event) => {
        const query = String(event.target.value || '').toLowerCase().trim();
        root.querySelectorAll('[data-palette-group]').forEach((group) => {
            let visible = 0;
            group.querySelectorAll('[data-node-type]').forEach((button) => {
                const label = `${button.dataset.nodeType} ${button.dataset.nodeLabel || button.textContent}`.toLowerCase();
                const match = !query || label.includes(query);
                button.hidden = !match;
                if (match) visible += 1;
            });
            group.hidden = visible === 0;
        });
    });

    canvas.addEventListener('dragover', (event) => event.preventDefault());
    canvas.addEventListener('drop', (event) => {
        event.preventDefault();
        const type = event.dataTransfer?.getData('text/enverif-node');
        if (!type) return;
        const rect = canvas.getBoundingClientRect();
        addNode(type, (event.clientX - rect.left + canvas.scrollLeft) / scale, (event.clientY - rect.top + canvas.scrollTop) / scale, false);
    });

    root.querySelectorAll('[data-workflow-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            scale = Math.max(0.5, Math.min(1.5, scale + (button.dataset.workflowZoom === 'in' ? 0.1 : -0.1)));
            root.querySelector('[data-workflow-zoom-label]').textContent = `${Math.round(scale * 100)}%`;
            renderEdges();
        });
    });

    root.querySelector('[data-workflow-fit]')?.addEventListener('click', () => {
        scale = 0.75;
        canvas.scrollTo({left: 0, top: 0, behavior: 'smooth'});
        root.querySelector('[data-workflow-zoom-label]').textContent = '75%';
        renderEdges();
    });

    root.querySelector('[data-workflow-auto-layout]')?.addEventListener('click', autoLayout);

    document.querySelector('[data-workflow-form]')?.addEventListener('submit', sync);
    renderNodes();
    inspectNode();
    sync();
})();
// Workflow run polling keeps durable execution visible without a SPA.
(()=>{const root=document.querySelector('[data-workflow-run]');if(!root)return;const url=root.dataset.statusUrl,current=root.dataset.currentStatus,count=Number(root.dataset.stepCount||0);if(['completed','failed','cancelled'].includes(current))return;const poll=async()=>{try{const r=await fetch(url,{headers:{Accept:'application/json'}});if(!r.ok)return;const data=await r.json();if(data.status!==current||(data.steps?.length||0)!==count||['completed','failed','cancelled'].includes(data.status))location.reload()}catch{}setTimeout(poll,2500)};setTimeout(poll,1800)})();
// ChatGPT-style revenue workspace shell.
(() => {
    const shell = document.querySelector('[data-chat-shell]');
    if (!shell) return;

    const form = shell.querySelector('[data-chat-form]');
    const prompt = shell.querySelector('[data-chat-prompt]');
    const menu = shell.querySelector('[data-context-menu]');
    const toggle = shell.querySelector('[data-context-toggle]');
    const search = shell.querySelector('[data-context-search]');
    const selected = shell.querySelector('[data-selected-context]');
    const scroll = shell.querySelector('[data-chat-scroll]');
    const connectionSelect = shell.querySelector('[data-chat-model-connection]');
    const modelSelect = shell.querySelector('[data-chat-model]');
    const effortSelect = shell.querySelector('[data-chat-effort]');
    const customWrap = shell.querySelector('[data-chat-custom-model-wrap]');
    const customInput = shell.querySelector('[data-chat-custom-model]');
    const agentSelect = shell.querySelector('[data-chat-agent]');
    const attachments = shell.querySelector('[data-chat-attachments]');
    const attachmentPreview = shell.querySelector('[data-attachment-preview]');
    const send = form?.querySelector('.send-button');
    const errorBox = form?.querySelector('[data-chat-error]');

    let modelCatalog = {};
    try { modelCatalog = JSON.parse(shell.dataset.modelCatalog || '{}'); } catch (_) {}

    let statusUrl = shell.dataset.statusUrl || '';
    let pollTimer = null;
    let busy = false;

    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const resize = () => {
        if (!prompt) return;
        prompt.style.height = 'auto';
        prompt.style.height = `${Math.max(36, Math.min(prompt.scrollHeight, 200))}px`;
    };
    const scrollToBottom = () => requestAnimationFrame(() => {
        if (scroll) scroll.scrollTop = scroll.scrollHeight;
    });
    const setBusy = (value) => {
        busy = value;
        form?.classList.toggle('is-sending', value);
        if (send) {
            send.disabled = value || send.dataset.preflightDisabled === '1';
            if (value) send.setAttribute('aria-busy', 'true');
            else send.removeAttribute('aria-busy');
        }
    };
    const showError = (message = '') => {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.hidden = message === '';
    };
    const updateDocumentTitle = (title) => {
        if (!title) return;
        document.title = `${title} Â· Enverif`;
        const crumb = document.querySelector('[data-page-crumb]');
        if (crumb) crumb.textContent = title;
    };
    const renderTranscript = (html) => {
        if (!scroll || typeof html !== 'string' || html.trim() === '') return;
        let live = scroll.querySelector('[data-chat-live-region]');
        if (!live) {
            scroll.innerHTML = '<div data-chat-live-region></div>';
            live = scroll.querySelector('[data-chat-live-region]');
        }
        live.innerHTML = html;
        scrollToBottom();
    };
    const filterContext = (query = '') => {
        const normalized = query.trim().toLowerCase().replace(/^@/, '');
        shell.querySelectorAll('[data-context-item]').forEach((item) => {
            const haystack = `${item.dataset.contextType || ''} ${item.textContent}`.toLowerCase();
            item.hidden = normalized !== '' && !haystack.includes(normalized);
        });
    };
    const refreshPills = () => {
        if (!selected || !form) return;
        const chosen = [...form.querySelectorAll('.context-option input:checked')];
        selected.innerHTML = chosen.slice(0, 8).map((input) => {
            const option = input.closest('.context-option');
            const name = option?.querySelector('b')?.textContent || 'Context';
            const type = option?.dataset.contextType || 'context';
            return `<span class="context-pill">@${escape(type)} ${escape(name)}</span>`;
        }).join('') + (chosen.length > 8 ? `<span class="context-pill">+${chosen.length - 8}</span>` : '');
    };
    const selectedConnection = () => connectionSelect?.selectedOptions?.[0];
    const toggleCustomModel = () => {
        if (!customWrap || !modelSelect) return;
        const custom = modelSelect.value === '__custom__';
        customWrap.hidden = !custom;
        if (customInput) customInput.required = custom;
    };
    const refreshModelOptions = (keepCurrent = true) => {
        if (!modelSelect) return;
        const previous = keepCurrent ? modelSelect.value : '';
        const provider = selectedConnection()?.dataset.provider || '';
        const models = modelCatalog[provider] || [];
        modelSelect.innerHTML = '<option value="">Connection default</option>'
            + models.map((id) => `<option value="${escape(id)}">${escape(id)}</option>`).join('')
            + '<option value="__custom__">Custom modelâ€¦</option>';
        if ([...modelSelect.options].some((option) => option.value === previous)) {
            modelSelect.value = previous;
        } else if (previous) {
            modelSelect.value = '__custom__';
            if (customInput) customInput.value = previous;
        }
        toggleCustomModel();
    };
    const previewAttachments = () => {
        if (!attachmentPreview || !attachments) return;
        const files = [...attachments.files];
        attachmentPreview.innerHTML = files.map((file) => `<span class="attachment-chip"><b>${escape(file.name)}</b><small>${Math.max(0.1, file.size / 1024).toFixed(1)} KB</small></span>`).join('');
    };
    const clearTurnInputs = () => {
        if (prompt) prompt.value = '';
        if (attachments) attachments.value = '';
        form?.querySelectorAll('.context-option input[type="checkbox"]').forEach((input) => { input.checked = false; });
        if (search) search.value = '';
        if (menu) menu.hidden = true;
        previewAttachments();
        refreshPills();
        resize();
    };
    const terminal = (status) => ['completed', 'failed', 'cancelled'].includes(status || '');
    const applyStatus = (data) => {
        if (data?.transcript_html) renderTranscript(data.transcript_html);
        if (data?.title) updateDocumentTitle(data.title);
        const stage = shell.querySelector('[data-chat-stage]');
        if (stage && data?.run?.stage) stage.textContent = data.run.stage;
    };
    const schedulePoll = (delay = 1600) => {
        if (!statusUrl) return;
        if (pollTimer) window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(poll, delay);
    };
    const poll = async () => {
        if (!statusUrl) return;
        try {
            const response = await fetch(statusUrl, {
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (response.ok) {
                const data = await response.json();
                applyStatus(data);
                if (!data.run || terminal(data.run.status)) {
                    setBusy(false);
                    return;
                }
            }
        } catch (_) {
            // Keep polling transient network/server errors. The durable run remains authoritative.
        }
        schedulePoll(1800);
    };

    prompt?.addEventListener('input', () => {
        resize();
        const before = prompt.value.slice(0, prompt.selectionStart);
        const match = before.match(/(?:^|\s)@([\w-]*)$/);
        if (match && menu) {
            menu.hidden = false;
            if (search) search.value = match[1] || '';
            filterContext(match[1] || '');
        } else if (!match && menu && !menu.hidden) {
            menu.hidden = true;
        }
    });
    prompt?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu && !menu.hidden) {
            menu.hidden = true;
            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (!busy && form?.reportValidity()) form.requestSubmit();
        }
    });
    toggle?.addEventListener('click', () => {
        if (!menu) return;
        menu.hidden = !menu.hidden;
        if (!menu.hidden) {
            filterContext(search?.value || '');
            window.setTimeout(() => search?.focus(), 20);
        }
    });
    document.addEventListener('click', (event) => {
        if (menu && !menu.hidden && !menu.contains(event.target) && !toggle?.contains(event.target) && event.target !== prompt) {
            menu.hidden = true;
        }
    });
    shell.querySelectorAll('.context-option input').forEach((input) => input.addEventListener('change', () => {
        const option = input.closest('.context-option');
        if (option?.dataset.contextType === 'agent' && input.checked && agentSelect) {
            agentSelect.value = input.value;
            agentSelect.dispatchEvent(new Event('change'));
        }
        // Remove the @... trigger text from the prompt when an item is picked via context menu
        if (prompt && input.checked) {
            const before = prompt.value.slice(0, prompt.selectionStart);
            const after = prompt.value.slice(prompt.selectionStart);
            const cleaned = before.replace(/(?<=^|\s)@[\w-]*$/, '');
            if (cleaned !== before) {
                prompt.value = cleaned + after;
                const pos = cleaned.length;
                prompt.setSelectionRange(pos, pos);
                resize();
            }
        }
        if (menu) menu.hidden = true;
        refreshPills();
    }));
    agentSelect?.addEventListener('change', () => {
        const radio = form?.querySelector(`[data-agent-context][value="${CSS.escape(agentSelect.value)}"]`);
        if (radio) radio.checked = true;

        const option = agentSelect.selectedOptions?.[0];
        const preferredConnection = option?.dataset.modelConnection || '';
        const preferredModel = option?.dataset.model || '';
        const preferredEffort = option?.dataset.effort || 'standard';
        if (connectionSelect && preferredConnection && [...connectionSelect.options].some((item) => item.value === preferredConnection)) {
            connectionSelect.value = preferredConnection;
        }
        refreshModelOptions(false);
        if (modelSelect && preferredModel) {
            if ([...modelSelect.options].some((item) => item.value === preferredModel)) {
                modelSelect.value = preferredModel;
                if (customInput) customInput.value = '';
            } else {
                modelSelect.value = '__custom__';
                if (customInput) customInput.value = preferredModel;
            }
            toggleCustomModel();
        }
        if (effortSelect && ['fast', 'standard', 'deep'].includes(preferredEffort)) {
            effortSelect.value = preferredEffort;
        }
        refreshPills();
    });
    search?.addEventListener('input', () => filterContext(search.value));
    shell.querySelectorAll('[data-suggest]').forEach((button) => button.addEventListener('click', () => {
        if (!prompt) return;
        prompt.value = button.dataset.suggest || '';
        resize();
        prompt.focus();
    }));
    connectionSelect?.addEventListener('change', () => refreshModelOptions(false));
    modelSelect?.addEventListener('change', toggleCustomModel);
    attachments?.addEventListener('change', previewAttachments);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;

        showError('');
        setBusy(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const contentType = response.headers.get('content-type') || '';
            const data = contentType.includes('application/json') ? await response.json() : null;

            if (!response.ok) {
                const validation = data?.errors ? Object.values(data.errors).flat().join(' ') : '';
                throw new Error(validation || data?.message || `Unable to send message (${response.status}).`);
            }
            if (!data?.thread_url || !data?.status_url || !data?.send_url) {
                throw new Error('The chat response was incomplete. Refresh and try again.');
            }

            history.replaceState({threadId: data.thread_id}, '', data.thread_url);
            shell.dataset.threadId = String(data.thread_id || '');
            shell.dataset.statusUrl = data.status_url;
            statusUrl = data.status_url;
            form.action = data.send_url;
            updateDocumentTitle(data.title);
            if (data.transcript_html) renderTranscript(data.transcript_html);
            clearTurnInputs();
            schedulePoll(500);
        } catch (error) {
            setBusy(false);
            showError(error instanceof Error ? error.message : 'Unable to send the message.');
        }
    });

    refreshPills();
    resize();
    previewAttachments();
    toggleCustomModel();
    scrollToBottom();

    if (statusUrl && shell.querySelector('[data-chat-thinking]')) {
        setBusy(true);
        schedulePoll(800);
    }
})();
// Schedule target switcher.
(() => {
    const form = document.querySelector('[data-schedule-form]');
    if (!form) return;
    const target = form.querySelector('[data-schedule-target]');
    const agent = form.querySelector('[data-agent-target]');
    const workflow = form.querySelector('[data-workflow-target]');
    const sync = () => {
        const isWorkflow = target.value === 'workflow';
        agent.hidden = isWorkflow;
        workflow.hidden = !isWorkflow;
        agent.querySelector('select').required = !isWorkflow;
        workflow.querySelector('select').required = isWorkflow;
    };
    target.addEventListener('change', sync);
    sync();
})();
