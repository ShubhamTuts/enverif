(() => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const terminal = (status) => ['completed', 'failed', 'cancelled'].includes(String(status || ''));
    const timeLabel = (value) => {
        if (!value) return '';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    };

    const ensureToast = () => {
        let toast = document.querySelector('[data-runtime-toast]');
        if (toast) return toast;
        toast = document.createElement('div');
        toast.className = 'runtime-toast';
        toast.dataset.runtimeToast = '1';
        toast.hidden = true;
        toast.innerHTML = '<div><strong data-runtime-toast-title>Action required</strong><p data-runtime-toast-copy></p></div><button class="icon-btn" type="button" data-runtime-toast-close aria-label="Close">×</button>';
        document.body.appendChild(toast);
        toast.querySelector('[data-runtime-toast-close]')?.addEventListener('click', () => { toast.hidden = true; });
        return toast;
    };
    const showToast = (title, message) => {
        const toast = ensureToast();
        toast.querySelector('[data-runtime-toast-title]').textContent = title;
        toast.querySelector('[data-runtime-toast-copy]').textContent = message;
        toast.hidden = false;
        window.setTimeout(() => { toast.hidden = true; }, 7000);
    };

    let pendingDestructiveForm = null;
    const ensureConfirmDialog = () => {
        let dialog = document.querySelector('[data-destructive-dialog]');
        if (dialog) return dialog;
        dialog = document.createElement('dialog');
        dialog.className = 'card destructive-confirm';
        dialog.dataset.destructiveDialog = '1';
        dialog.innerHTML = '<form method="dialog"><div class="card-head"><div><h2 data-destructive-title>Confirm action</h2><p class="muted" data-destructive-message></p></div><button class="icon-btn" value="cancel" aria-label="Close">×</button></div><div class="destructive-dependencies" data-destructive-dependencies hidden></div><div class="form-actions"><button class="btn" value="cancel">Cancel</button><button class="btn btn-danger" type="button" data-destructive-confirm>Continue</button></div></form>';
        document.body.appendChild(dialog);
        dialog.querySelector('[data-destructive-confirm]')?.addEventListener('click', () => {
            if (!pendingDestructiveForm) return;
            const form = pendingDestructiveForm;
            pendingDestructiveForm = null;
            form.dataset.confirmed = '1';
            dialog.close();
            form.requestSubmit();
        });
        dialog.addEventListener('close', () => { pendingDestructiveForm = null; });
        return dialog;
    };
    const dependencySummary = (data) => {
        const lines = [];
        const agents = data?.agents?.length ?? 0;
        const workflows = data?.workflows?.length ?? 0;
        const schedules = data?.schedules?.length ?? 0;
        const connections = data?.connections?.length ?? 0;
        if (connections) lines.push(`${connections} configured connection${connections === 1 ? '' : 's'}`);
        if (agents) lines.push(`${agents} agent${agents === 1 ? '' : 's'}`);
        if (workflows) lines.push(`${workflows} workflow${workflows === 1 ? '' : 's'}`);
        if (schedules) lines.push(`${schedules} schedule${schedules === 1 ? '' : 's'}`);
        return lines;
    };
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('[data-destructive-form]');
        if (!form || form.dataset.confirmed === '1') return;
        event.preventDefault();
        pendingDestructiveForm = form;
        const dialog = ensureConfirmDialog();
        const title = form.dataset.confirmTitle || 'Confirm destructive action';
        const message = form.dataset.confirmMessage || 'This action may remove access or configuration.';
        dialog.querySelector('[data-destructive-title]').textContent = title;
        dialog.querySelector('[data-destructive-message]').textContent = message;
        const dependencyBox = dialog.querySelector('[data-destructive-dependencies]');
        const confirm = dialog.querySelector('[data-destructive-confirm]');
        dependencyBox.hidden = true;
        dependencyBox.textContent = '';
        confirm.disabled = false;
        confirm.textContent = 'Continue';
        const dependencyUrl = form.dataset.dependenciesUrl || '';
        if (dependencyUrl) {
            dependencyBox.hidden = false;
            dependencyBox.textContent = 'Checking dependencies…';
            confirm.disabled = true;
            try {
                const response = await fetch(dependencyUrl, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
                if (!response.ok) throw new Error('Dependency check failed.');
                const data = await response.json();
                const blocking = Number(data.blocking_count || 0);
                const lines = dependencySummary(data);
                dependencyBox.innerHTML = lines.length ? lines.map((line) => `<div>${escapeHtml(line)}</div>`).join('') : '<div>No live dependencies found.</div>';
                if (blocking > 0) {
                    dependencyBox.insertAdjacentHTML('beforeend', '<div><strong>Removal is blocked until these dependencies are detached.</strong></div>');
                    confirm.disabled = true;
                    confirm.textContent = 'Blocked';
                } else {
                    confirm.disabled = false;
                }
            } catch (error) {
                dependencyBox.textContent = error instanceof Error ? error.message : 'Unable to verify dependencies.';
                confirm.disabled = true;
            }
        }
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else if (window.confirm(`${title}\n\n${message}`)) {
            form.dataset.confirmed = '1';
            form.requestSubmit();
        }
    }, true);

    const actionCenter = document.querySelector('[data-action-center-url]');
    if (actionCenter) {
        const url = actionCenter.dataset.actionCenterUrl || actionCenter.getAttribute('href');
        let known = Number(actionCenter.dataset.pendingCount || 0);
        let timer = null;
        const renderCount = (count) => {
            let badge = actionCenter.querySelector('[data-action-center-badge]');
            if (!badge && count > 0) {
                badge = document.createElement('span');
                badge.className = 'action-center-badge';
                badge.dataset.actionCenterBadge = '1';
                actionCenter.appendChild(badge);
            }
            if (badge) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count < 1;
            }
            actionCenter.setAttribute('aria-label', count > 0 ? `Action Center, ${count} pending` : 'Action Center');
        };
        const poll = async () => {
            try {
                const response = await fetch(url, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
                if (response.ok) {
                    const data = await response.json();
                    const count = Number(data.pending_count || 0);
                    if (count > known) showToast('Approval required', `${count} action${count === 1 ? '' : 's'} waiting in Action Center.`);
                    known = count;
                    renderCount(count);
                }
            } catch (_) {}
            timer = window.setTimeout(poll, document.hidden ? 30000 : 12000);
        };
        renderCount(known);
        timer = window.setTimeout(poll, 1200);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                if (timer) window.clearTimeout(timer);
                poll();
            }
        });
    }

    const shell = document.querySelector('[data-chat-shell]');
    if (!shell) return;
    const chatScroll = shell.querySelector('[data-chat-scroll]');
    let projection = null;
    let pollTimer = null;
    let lastApprovalCount = -1;

    const activityUrl = () => {
        const direct = shell.dataset.activityUrl || '';
        if (direct) return direct;
        const status = shell.dataset.statusUrl || '';
        if (!status) return '';
        return status.replace(/\/status(?:\?.*)?$/, '/activity');
    };

    const approvalStack = document.createElement('div');
    approvalStack.className = 'runtime-approval-stack';
    approvalStack.dataset.runtimeApprovalStack = '1';
    approvalStack.hidden = true;
    if (chatScroll) chatScroll.appendChild(approvalStack);

    const backdrop = document.createElement('div');
    backdrop.className = 'runtime-drawer-backdrop';
    const drawer = document.createElement('aside');
    drawer.className = 'runtime-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="runtime-drawer-head"><div><h2>Run activity</h2><p>Agents, tools, connectors and approvals</p></div><button class="icon-btn" type="button" data-runtime-drawer-close aria-label="Close activity">×</button></div><div class="runtime-drawer-body" data-runtime-drawer-body><div class="runtime-empty">No run activity yet.</div></div>';
    document.body.append(backdrop, drawer);
    const openDrawer = () => { drawer.classList.add('is-open'); backdrop.classList.add('is-open'); drawer.setAttribute('aria-hidden','false'); };
    const closeDrawer = () => { drawer.classList.remove('is-open'); backdrop.classList.remove('is-open'); drawer.setAttribute('aria-hidden','true'); };
    backdrop.addEventListener('click', closeDrawer);
    drawer.querySelector('[data-runtime-drawer-close]')?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDrawer(); });

    const depthMap = (runs) => {
        const map = new Map(runs.map((run) => [String(run.id), run]));
        const depth = new Map();
        const visit = (run) => {
            const id = String(run.id);
            if (depth.has(id)) return depth.get(id);
            const parent = run.parent_run_id ? map.get(String(run.parent_run_id)) : null;
            const value = parent ? Math.min(visit(parent) + 1, 6) : 0;
            depth.set(id, value);
            return value;
        };
        runs.forEach(visit);
        return depth;
    };
    const renderDrawer = (data) => {
        const body = drawer.querySelector('[data-runtime-drawer-body]');
        const runs = Array.isArray(data?.runs) ? data.runs : [];
        const events = Array.isArray(data?.events) ? data.events : [];
        if (!runs.length && !events.length) { body.innerHTML = '<div class="runtime-empty">No persisted run activity yet.</div>'; return; }
        const depths = depthMap(runs);
        const eventsHtml = events.slice(-40).map((event) => `<div class="runtime-event" data-status="${escapeHtml(event.status)}"><span class="runtime-event-dot"></span><span>${escapeHtml(event.label)}</span><time>${escapeHtml(timeLabel(event.at))}</time></div>`).join('');
        const runsHtml = runs.map((run) => {
            const depth = depths.get(String(run.id)) || 0;
            const initial = String(run.agent_name || 'A').trim().charAt(0).toUpperCase();
            const steps = (run.steps || []).map((step) => `<div class="runtime-step"><span class="runtime-step-icon">${step.status === 'completed' ? '✓' : (step.status === 'failed' ? '!' : '·')}</span><span><b>${escapeHtml(step.label)}</b>${step.tool ? `<small>${escapeHtml(step.tool)}</small>` : ''}</span><span class="runtime-risk">${escapeHtml(step.status)}</span></div>`).join('');
            return `<section class="runtime-run" style="margin-left:${depth * 14}px"><div class="runtime-run-head"><span class="runtime-run-avatar">${escapeHtml(initial)}</span><span><strong>${escapeHtml(run.agent_name)}</strong><small>${run.parent_run_id ? 'Sub-agent' : 'Primary agent'}</small></span><span class="runtime-run-state">${escapeHtml(run.status)}</span></div>${steps ? `<div class="runtime-step-list">${steps}</div>` : ''}</section>`;
        }).join('');
        body.innerHTML = `${eventsHtml ? `<div class="runtime-events">${eventsHtml}</div>` : ''}${runsHtml}`;
    };
    const renderApprovals = (data) => {
        const approvals = Array.isArray(data?.pending_approvals) ? data.pending_approvals : [];
        approvalStack.innerHTML = approvals.map((approval) => `<article class="runtime-approval" data-approval-id="${escapeHtml(approval.id)}"><div class="runtime-approval-head"><strong>Approval required</strong><span class="runtime-approval-risk">${escapeHtml(approval.risk_level || 'external write')}</span></div><p>${escapeHtml(approval.summary || approval.action || 'External action')}</p><div class="runtime-approval-actions"><button class="btn btn-sm btn-primary" type="button" data-runtime-approval="approved" data-url="${escapeHtml(approval.decide_url)}">Approve</button><button class="btn btn-sm btn-danger" type="button" data-runtime-approval="denied" data-url="${escapeHtml(approval.decide_url)}">Deny</button><button class="btn btn-sm" type="button" data-runtime-open-drawer>Review activity</button></div></article>`).join('');
        approvalStack.hidden = approvals.length === 0;
        if (lastApprovalCount >= 0 && approvals.length > lastApprovalCount) showToast('Approval required', approvals[0]?.summary || 'An agent is waiting for your decision.');
        lastApprovalCount = approvals.length;
    };
    const decorateTriggers = () => {
        shell.querySelectorAll('[data-chat-thread-run], [data-chat-thinking]').forEach((element) => {
            element.setAttribute('role','button');
            element.setAttribute('tabindex','0');
            element.title = 'Open run activity';
        });
    };
    const fetchActivity = async () => {
        const url = activityUrl();
        if (!url) { pollTimer = window.setTimeout(fetchActivity, 900); return; }
        try {
            const response = await fetch(url, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
            if (response.ok) {
                projection = await response.json();
                renderApprovals(projection);
                renderDrawer(projection);
                decorateTriggers();
            }
        } catch (_) {}
        const active = (projection?.runs || []).some((run) => !terminal(run.status));
        pollTimer = window.setTimeout(fetchActivity, active ? 1100 : 6000);
    };
    const decide = async (button) => {
        const url = button.dataset.url;
        const decision = button.dataset.runtimeApproval;
        if (!url || !['approved','denied'].includes(decision)) return;
        const card = button.closest('[data-approval-id]');
        card?.querySelectorAll('button').forEach((item) => { item.disabled = true; });
        try {
            const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:JSON.stringify({decision})});
            if (!response.ok && response.status !== 409) throw new Error('Unable to record the decision.');
            await fetchActivity();
        } catch (error) {
            card?.querySelectorAll('button').forEach((item) => { item.disabled = false; });
            showToast('Approval failed', error instanceof Error ? error.message : 'Unable to record the decision.');
        }
    };
    document.addEventListener('click', (event) => {
        const approval = event.target.closest?.('[data-runtime-approval]');
        if (approval) { event.preventDefault(); decide(approval); return; }
        if (event.target.closest?.('[data-runtime-open-drawer]')) { event.preventDefault(); openDrawer(); return; }
        const trigger = event.target.closest?.('[data-chat-thread-run], [data-chat-thinking]');
        if (trigger && shell.contains(trigger)) { event.preventDefault(); openDrawer(); }
    }, true);
    document.addEventListener('keydown', (event) => {
        const trigger = event.target.closest?.('[data-chat-thread-run], [data-chat-thinking]');
        if (trigger && shell.contains(trigger) && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openDrawer(); }
    });
    decorateTriggers();
    fetchActivity();
    window.addEventListener('beforeunload', () => { if (pollTimer) window.clearTimeout(pollTimer); });
})();
