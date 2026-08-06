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
        modelSelect.innerHTML='<option value="">Use provider default</option>'+models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model ID…</option>';
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
    function populate(preserve=true){const previous=preserve?model.value:'';const models=Array.isArray(catalog?.[provider.value])?catalog[provider.value]:[];model.innerHTML=models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model ID…</option>';if(preserve&&[...model.options].some(option=>option.value===previous))model.value=previous;syncCustom()}
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
    function populate(preserve=true){const option=connection.options[connection.selectedIndex];const provider=option?.dataset.provider||'';const previous=preserve?model.value:'';const models=Array.isArray(catalog?.[provider])?catalog[provider]:[];model.innerHTML='<option value="">Use connection default</option>'+models.map(id=>`<option value="${escapeOption(id)}">${escapeOption(id)}</option>`).join('')+'<option value="__custom__">Custom model ID…</option>';model.disabled=!connection.value;if(preserve&&[...model.options].some(item=>item.value===previous))model.value=previous;syncCustom()}
    connection.addEventListener('change',()=>populate(false));
    model.addEventListener('change',syncCustom);
    populate(true);
});

// Durable visual workflow studio.
(() => {
    const root = document.querySelector('[data-workflow-builder]');
    if (!root) return;

    const hidden = document.querySelector('[data-workflow-definition]');
    const stage = root.querySelector('[data-workflow-stage]');
    const canvas = root.querySelector('[data-workflow-canvas]');
    const svg = root.querySelector('[data-workflow-lines]');
    const empty = root.querySelector('[data-workflow-empty]');
    const inspector = root.querySelector('[data-workflow-inspector]');
    const fieldBox = root.querySelector('[data-node-fields]');
    const labelField = root.querySelector('[data-node-label]');
    const heading = root.querySelector('[data-node-heading]');
    const connectHelp = root.querySelector('[data-connect-help]');
    const connectButton = root.querySelector('[data-node-connect]');

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
    let connecting = null;
    let scale = 1;
    let drag = null;

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

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
    }[character]));

    const makeId = (type) => `${type}_${Math.random().toString(36).slice(2, 9)}`;

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

    function sync() {
        if (hidden) hidden.value = JSON.stringify(definition);
        renderEdges();
    }

    function addNode(type, x = 160 + canvas.scrollLeft / scale, y = 160 + canvas.scrollTop / scale) {
        const node = {
            id: makeId(type),
            type,
            label: labels[type] || type,
            config: defaultConfig(type),
            position: {
                x: Math.max(20, Math.round(x)),
                y: Math.max(20, Math.round(y)),
            },
        };
        definition.nodes.push(node);
        selectedId = node.id;
        renderNodes();
        inspectNode();
        sync();
    }

    function renderNodes() {
        stage.innerHTML = '';
        for (const node of definition.nodes) {
            const element = document.createElement('button');
            element.type = 'button';
            element.className = [
                'workflow-node',
                node.id === selectedId ? 'selected' : '',
                connecting && node.id !== connecting.from ? 'connect-target' : '',
            ].filter(Boolean).join(' ');
            element.dataset.nodeId = node.id;
            element.style.left = `${node.position?.x || 80}px`;
            element.style.top = `${node.position?.y || 80}px`;
            element.innerHTML = `
                <div class="workflow-node-head">
                    <span class="workflow-node-icon">${escapeHtml((labels[node.type] || node.type).slice(0, 1).toUpperCase())}</span>
                    <div>
                        <div class="workflow-node-label">${escapeHtml(node.label || labels[node.type] || node.type)}</div>
                        <div class="workflow-node-type">${escapeHtml(labels[node.type] || node.type)}</div>
                    </div>
                </div>
                <span class="workflow-node-port"></span>`;
            stage.appendChild(element);
        }
        renderEdges();
    }

    function renderEdges() {
        svg.setAttribute('viewBox', '0 0 2200 1400');
        svg.style.transform = `scale(${scale})`;
        stage.style.transform = `scale(${scale})`;

        let output = '<defs><marker id="wf-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor"/></marker></defs>';
        for (const edge of definition.edges) {
            const source = nodeById(edge.from);
            const target = nodeById(edge.to);
            if (!source || !target) continue;

            const x1 = (source.position?.x || 0) + 194;
            const y1 = (source.position?.y || 0) + 38;
            const x2 = target.position?.x || 0;
            const y2 = (target.position?.y || 0) + 38;
            const curve = Math.max(70, Math.abs(x2 - x1) * 0.45);
            const path = `M ${x1} ${y1} C ${x1 + curve} ${y1}, ${x2 - curve} ${y2}, ${x2} ${y2}`;
            const port = edge.port || 'default';
            output += `<path class="workflow-edge ${escapeHtml(port)}" d="${path}" marker-end="url(#wf-arrow)"/>`;
            if (port !== 'default') {
                output += `<text class="workflow-edge-label" x="${(x1 + x2) / 2}" y="${(y1 + y2) / 2 - 7}">${escapeHtml(port)}</text>`;
            }
        }
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
        connectButton.textContent = 'Connect to…';
        connectHelp.textContent = connecting ? `Choose a destination for the ${connecting.port} branch.` : 'Connections are saved with the workflow.';
        bindFields(node);
    }

    function refreshActions(node) {
        const connection = resources.connectors?.find((item) => String(item.id) === String(node.config.connection_id));
        const actions = resources.catalog?.[connection?.driver]?.actions || [];
        const select = fieldBox.querySelector('[data-action-select]');
        if (!select) return;

        select.innerHTML = '<option value="">Choose action</option>' + actions.map((action) => `<option value="${escapeHtml(action.name)}" ${node.config.action === action.name ? 'selected' : ''}>${escapeHtml(action.name)} · ${escapeHtml(action.risk)}</option>`).join('');
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
        renderNodes();
        inspectNode();
    }

    labelField.addEventListener('input', () => {
        const node = selectedNode();
        if (!node) return;
        node.label = labelField.value;
        renderNodes();
        sync();
    });

    root.querySelector('[data-node-delete]').addEventListener('click', () => {
        if (!selectedId) return;
        definition.nodes = definition.nodes.filter((node) => node.id !== selectedId);
        definition.edges = definition.edges.filter((edge) => edge.from !== selectedId && edge.to !== selectedId);
        selectedId = null;
        connecting = null;
        renderNodes();
        inspectNode();
        sync();
    });

    connectButton.addEventListener('click', () => beginConnection('default'));

    stage.addEventListener('pointerdown', (event) => {
        const element = event.target.closest('.workflow-node');
        if (!element) return;
        const id = element.dataset.nodeId;

        if (connecting && id !== connecting.from) {
            definition.edges = definition.edges.filter((edge) => !(edge.from === connecting.from && (edge.port || 'default') === connecting.port));
            definition.edges.push({from: connecting.from, to: id, port: connecting.port});
            connecting = null;
            selectedId = id;
            renderNodes();
            inspectNode();
            sync();
            return;
        }

        selectedId = id;
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

    window.addEventListener('pointermove', (event) => {
        if (!drag) return;
        drag.node.position.x = Math.max(10, Math.round(drag.x + (event.clientX - drag.startX) / scale));
        drag.node.position.y = Math.max(10, Math.round(drag.y + (event.clientY - drag.startY) / scale));
        const element = stage.querySelector(`[data-node-id="${CSS.escape(drag.node.id)}"]`);
        if (element) {
            element.style.left = `${drag.node.position.x}px`;
            element.style.top = `${drag.node.position.y}px`;
        }
        renderEdges();
    });

    window.addEventListener('pointerup', () => {
        if (!drag) return;
        drag = null;
        sync();
    });

    root.querySelectorAll('[data-node-type]').forEach((button) => {
        button.addEventListener('click', () => addNode(button.dataset.nodeType));
        button.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/enverif-node', button.dataset.nodeType));
    });

    canvas.addEventListener('dragover', (event) => event.preventDefault());
    canvas.addEventListener('drop', (event) => {
        event.preventDefault();
        const type = event.dataTransfer?.getData('text/enverif-node');
        if (!type) return;
        const rect = canvas.getBoundingClientRect();
        addNode(type, (event.clientX - rect.left + canvas.scrollLeft) / scale, (event.clientY - rect.top + canvas.scrollTop) / scale);
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
        prompt.style.height = `${Math.min(prompt.scrollHeight, 200)}px`;
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
        document.title = `${title} · Enverif`;
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
            + '<option value="__custom__">Custom model…</option>';
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
        const match = prompt.value.slice(0, prompt.selectionStart).match(/(?:^|\s)@([\w-]*)$/);
        if (match && menu) {
            menu.hidden = false;
            if (search) search.value = match[1] || '';
            filterContext(match[1] || '');
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
