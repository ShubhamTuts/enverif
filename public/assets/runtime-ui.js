(() => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const terminal = (status) => ['completed', 'failed', 'cancelled'].includes(String(status || ''));
    const timeLabel = (value) => {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
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

    // Global Action Center counter. The server remains the source of truth.
    const actionCenter = document.querySelector('[data-action-center-url]');
    if (actionCenter) {
        const url = actionCenter.dataset.actionCenterUrl || actionCenter.getAttribute('href');
        let known = Number(actionCenter.dataset.pendingCount || 0);
        let actionTimer = null;
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
        const pollActions = async () => {
            try {
                const response = await fetch(url, {headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin', cache: 'no-store'});
                if (response.ok) {
                    const data = await response.json();
                    const count = Number(data.pending_count || 0);
                    if (count > known && known >= 0) showToast('Approval required', `${count} action${count === 1 ? '' : 's'} waiting in Action Center.`);
                    known = count;
                    renderCount(count);
                }
            } catch (_) {}
            actionTimer = window.setTimeout(pollActions, document.hidden ? 30000 : 12000);
        };
        renderCount(known);
        actionTimer = window.setTimeout(pollActions, 1200);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                if (actionTimer) window.clearTimeout(actionTimer);
                pollActions();
            }
        });
    }

    const shell = document.querySelector('[data-chat-shell]');
    if (!shell) return;

    const activityUrl = shell.dataset.activityUrl || '';
    if (!activityUrl) return;

    const chatScroll = shell.querySelector('[data-chat-scroll]');
    let projection = null;
    let pollTimer = null;
    let lastApprovalCount = -1;

    const approvalStack = document.createElement('div');
    approvalStack.className = 'runtime-approval-stack';
    approvalStack.dataset.runtimeApprovalStack = '1';
    if (chatScroll) chatScroll.appendChild(approvalStack);

    const backdrop = document.createElement('div');
    backdrop.className = 'runtime-drawer-backdrop';
    backdrop.dataset.runtimeDrawerBackdrop = '1';
    const drawer = document.createElement('aside');
    drawer.className = 'runtime-drawer';
    drawer.dataset.runtimeDrawer = '1';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="runtime-drawer-head"><div><h2>Run activity</h2><p>Agents, tools, connectors and approvals</p></div><button class="icon-btn" type="button" data-runtime-drawer-close aria-label="Close activity">×</button></div><div class="runtime-drawer-body" data-runtime-drawer-body><div class="runtime-empty">No run activity yet.</div></div>';
    document.body.append(backdrop, drawer);

    const openDrawer = () => {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.querySelector('[data-runtime-drawer-close]')?.focus({preventScroll: true});
    };
    const closeDrawer = () => {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
    };
    backdrop.addEventListener('click', closeDrawer);
    drawer.querySelector('[data-runtime-drawer-close]')?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer(); });

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
        if (!body) return;
        if (!runs.length && !events.length) {
            body.innerHTML = '<div class="runtime-empty">No persisted run activity yet.</div>';
            return;
        }
        const depths = depthMap(runs);
        const eventsHtml = events.slice(-30).map((event) => `<div class="runtime-event" data-status="${escapeHtml(event.status)}"><span class="runtime-event-dot"></span><span>${escapeHtml(event.label)}</span><time>${escapeHtml(timeLabel(event.at))}</time></div>`).join('');
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

    const decorateRuntimeTrigger = () => {
        const chip = shell.querySelector('[data-chat-thread-run]');
        if (chip && !chip.dataset.runtimeBound) {
            chip.dataset.runtimeBound = '1';
            chip.setAttribute('role', 'button');
            chip.setAttribute('tabindex', '0');
            chip.title = 'Open run activity';
        }
        const thinking = shell.querySelector('[data-chat-thinking]');
        if (thinking && !thinking.dataset.runtimeBound) {
            thinking.dataset.runtimeBound = '1';
            thinking.setAttribute('role', 'button');
            thinking.setAttribute('tabindex', '0');
            thinking.title = 'Open run activity';
        }
    };

    const render = (data) => {
        projection = data;
        renderApprovals(data);
        renderDrawer(data);
        decorateRuntimeTrigger();
    };

    const fetchActivity = async () => {
        try {
            const response = await fetch(activityUrl, {headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin', cache: 'no-store'});
            if (response.ok) render(await response.json());
        } catch (_) {}
        const active = (projection?.runs || []).some((run) => !terminal(run.status));
        pollTimer = window.setTimeout(fetchActivity, active ? 1100 : 6000);
    };

    const decide = async (button) => {
        const url = button.dataset.url;
        const decision = button.dataset.runtimeApproval;
        if (!url || !['approved', 'denied'].includes(decision)) return;
        button.disabled = true;
        const card = button.closest('[data-approval-id]');
        card?.querySelectorAll('button').forEach((item) => { item.disabled = true; });
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
                body: JSON.stringify({decision}),
            });
            if (!response.ok && response.status !== 409) throw new Error('Unable to record the decision.');
            await fetchActivity();
        } catch (error) {
            card?.querySelectorAll('button').forEach((item) => { item.disabled = false; });
            showToast('Approval failed', error instanceof Error ? error.message : 'Unable to record the decision.');
        }
    };

    document.addEventListener('click', (event) => {
        const approval = event.target.closest('[data-runtime-approval]');
        if (approval) { event.preventDefault(); decide(approval); return; }
        if (event.target.closest('[data-runtime-open-drawer]')) { event.preventDefault(); openDrawer(); return; }
        const chip = event.target.closest('[data-chat-thread-run], [data-chat-thinking]');
        if (chip && shell.contains(chip)) { event.preventDefault(); openDrawer(); }
    }, true);
    document.addEventListener('keydown', (event) => {
        const trigger = event.target.closest?.('[data-chat-thread-run], [data-chat-thinking]');
        if (trigger && shell.contains(trigger) && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault(); openDrawer();
        }
    });

    decorateRuntimeTrigger();
    fetchActivity();
    window.addEventListener('beforeunload', () => { if (pollTimer) window.clearTimeout(pollTimer); });
})();
