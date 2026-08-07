(() => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const terminal = (status) => ['completed', 'failed', 'cancelled'].includes(String(status || ''));
    const timeLabel = (value) => {
        if (!value) return '';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    };
    const statusLabel = (status) => {
        if (status === 'completed') return 'Completed';
        if (status === 'failed') return 'Failed';
        if (status === 'cancelled') return 'Cancelled';
        if (status === 'awaiting_approval') return 'Approval needed';
        if (status === 'waiting_child') return 'Waiting on agent';
        if (status === 'queued') return 'Queued';
        return 'Working';
    };

    const ensureToast = () => {
        let toast = document.querySelector('[data-runtime-toast]');
        if (toast) return toast;
        toast = document.createElement('div');
        toast.className = 'runtime-toast';
        toast.dataset.runtimeToast = '1';
        toast.hidden = true;
        toast.innerHTML = '<div><strong data-runtime-toast-title>Update</strong><p data-runtime-toast-copy></p></div><button class="icon-btn" type="button" data-runtime-toast-close aria-label="Close">×</button>';
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

    // Dependency-aware destructive confirmations stay global so integrations share one lifecycle UX.
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
        for (const [key, label] of [['connections','configured connection'],['agents','agent'],['workflows','workflow'],['schedules','schedule']]) {
            const count = data?.[key]?.length ?? 0;
            if (count) lines.push(`${count} ${label}${count === 1 ? '' : 's'}`);
        }
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
                } else confirm.disabled = false;
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

    // Action Center remains the workspace-wide queue of decisions.
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
    const chatScroll = shell?.querySelector('[data-chat-scroll]') || null;
    const activityTrigger = document.querySelector('[data-agent-activity-trigger]');
    const activityCount = activityTrigger?.querySelector('[data-agent-activity-count]') || null;
    const activityDot = activityTrigger?.querySelector('[data-agent-activity-dot]') || null;
    const runtimeFeedUrl = document.body.dataset.runtimeFeedUrl || '';
    const currentChatActivityUrl = () => {
        if (!shell) return '';
        const direct = shell.dataset.activityUrl || '';
        if (direct) return direct;
        const status = shell.dataset.statusUrl || '';
        return status ? status.replace(/\/status(?:\?.*)?$/, '/activity') : '';
    };
    const currentChatThreadId = () => String(shell?.dataset.threadId || '');
    const approvalStack = () => shell?.querySelector('[data-runtime-approval-stack]') || null;

    let feedItems = [];
    let feedSummary = {active_count:0, approval_count:0};
    let feedTimer = null;
    let previousFeed = null;
    let selectedItem = null;
    let selectedProjection = null;
    let currentChatProjection = null;
    let detailTimer = null;
    let drawerOpen = false;
    let detailRequest = 0;
    let lastApprovalCount = -1;
    let lastInlineProbeKey = '';

    const backdrop = document.createElement('div');
    backdrop.className = 'runtime-drawer-backdrop';
    const drawer = document.createElement('aside');
    drawer.className = 'runtime-drawer';
    drawer.id = 'agent-activity-drawer';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('aria-labelledby', 'agent-activity-title');
    drawer.innerHTML = '<div class="runtime-drawer-head"><div><h2 id="agent-activity-title">Agent activity</h2><p>Agents, tools, connectors and approvals</p></div><button class="icon-btn" type="button" data-runtime-drawer-close aria-label="Close Agent activity">×</button></div><div class="runtime-drawer-content"><div class="runtime-feed-list" data-runtime-feed-list></div><div class="runtime-drawer-body" data-runtime-drawer-body><div class="runtime-empty">No agent activity yet.</div></div></div>';
    document.body.append(backdrop, drawer);

    const avatarMarkup = (name, url, className = 'runtime-run-avatar') => {
        const initial = String(name || 'A').trim().charAt(0).toUpperCase() || 'A';
        if (!url) return `<span class="${className}"><span>${escapeHtml(initial)}</span></span>`;
        return `<span class="${className}" data-runtime-avatar><img src="${escapeHtml(url)}" alt="${escapeHtml(name || 'Agent')}" loading="lazy" data-runtime-avatar-img><span data-runtime-avatar-fallback hidden>${escapeHtml(initial)}</span></span>`;
    };
    const bindAvatarFallbacks = (root) => {
        root.querySelectorAll('[data-runtime-avatar-img]').forEach((image) => {
            image.addEventListener('error', () => {
                image.hidden = true;
                const fallback = image.parentElement?.querySelector('[data-runtime-avatar-fallback]');
                if (fallback) fallback.hidden = false;
            }, {once:true});
        });
    };
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
    const approvalCards = (approvals, includeReview = false) => approvals.map((approval) => `<article class="runtime-approval" data-approval-id="${escapeHtml(approval.id)}"><div class="runtime-approval-head"><strong>Approval required</strong><span class="runtime-approval-risk">${escapeHtml(approval.risk_level || 'external write')}</span></div><p>${escapeHtml(approval.summary || approval.action || 'External action')}</p><div class="runtime-approval-actions"><button class="btn btn-sm btn-primary" type="button" data-runtime-approval="approved" data-url="${escapeHtml(approval.decide_url)}">Approve</button><button class="btn btn-sm btn-danger" type="button" data-runtime-approval="denied" data-url="${escapeHtml(approval.decide_url)}">Deny</button>${includeReview ? '<button class="btn btn-sm" type="button" data-runtime-open-drawer>Review activity</button>' : ''}</div></article>`).join('');

    const renderInlineApprovals = (data) => {
        const mount = approvalStack();
        if (!mount) return;
        const approvals = Array.isArray(data?.pending_approvals) ? data.pending_approvals : [];
        mount.innerHTML = approvalCards(approvals, true);
        mount.hidden = approvals.length === 0;
        if (lastApprovalCount >= 0 && approvals.length > lastApprovalCount) showToast('Approval required', approvals[0]?.summary || 'An agent is waiting for your decision.');
        lastApprovalCount = approvals.length;
    };

    const renderFeedList = () => {
        const mount = drawer.querySelector('[data-runtime-feed-list]');
        if (!mount) return;
        if (!feedItems.length) {
            mount.innerHTML = '<div class="runtime-empty runtime-feed-empty">No recent agent runs.</div>';
            return;
        }
        mount.innerHTML = feedItems.map((item) => {
            const selected = selectedItem && String(selectedItem.activity_url) === String(item.activity_url);
            const approvals = Number(item.approval_count || 0);
            return `<button type="button" class="runtime-feed-item${selected ? ' is-selected' : ''}" data-runtime-select-activity data-url="${escapeHtml(item.activity_url)}" data-run-id="${escapeHtml(item.run_id)}">${avatarMarkup(item.agent_name, item.agent_avatar_url, 'runtime-feed-avatar')}<span class="runtime-feed-copy"><strong>${escapeHtml(item.agent_name || 'Agent')}</strong><small>${escapeHtml(item.title || 'Agent run')}</small></span><span class="runtime-feed-meta"><b data-status="${escapeHtml(item.status)}">${escapeHtml(statusLabel(item.status))}</b>${approvals > 0 ? `<em>${approvals} approval${approvals === 1 ? '' : 's'}</em>` : ''}<time>${escapeHtml(timeLabel(item.updated_at))}</time></span></button>`;
        }).join('');
        bindAvatarFallbacks(mount);
    };

    const renderDrawerProjection = (data) => {
        const body = drawer.querySelector('[data-runtime-drawer-body]');
        const runs = Array.isArray(data?.runs) ? data.runs : [];
        const events = Array.isArray(data?.events) ? data.events : [];
        const approvals = Array.isArray(data?.pending_approvals) ? data.pending_approvals : [];
        if (!runs.length && !events.length) {
            body.innerHTML = '<div class="runtime-empty">No persisted activity for this run yet.</div>';
            return;
        }
        const depths = depthMap(runs);
        const eventsHtml = events.slice(-50).map((event) => `<div class="runtime-event" data-status="${escapeHtml(event.status)}"><span class="runtime-event-dot"></span><span>${escapeHtml(event.label)}</span>${event.risk ? `<small>${escapeHtml(event.risk)}</small>` : ''}<time>${escapeHtml(timeLabel(event.at))}</time></div>`).join('');
        const runsHtml = runs.map((run) => {
            const depth = depths.get(String(run.id)) || 0;
            const steps = (run.steps || []).map((step) => `<div class="runtime-step" data-status="${escapeHtml(step.status)}"><span class="runtime-step-icon">${step.status === 'completed' ? '✓' : (step.status === 'failed' ? '!' : '·')}</span><span class="runtime-step-copy"><b>${escapeHtml(step.label)}</b>${step.tool ? `<small>${escapeHtml(step.tool)}</small>` : ''}</span><span class="runtime-step-meta">${step.risk_level ? `<em>${escapeHtml(step.risk_level)}</em>` : ''}<b>${escapeHtml(statusLabel(step.status))}</b><time>${escapeHtml(timeLabel(step.finished_at || step.started_at || step.created_at))}</time></span></div>`).join('');
            return `<section class="runtime-run" style="--runtime-depth:${depth}"><div class="runtime-run-head">${avatarMarkup(run.agent_name, run.agent_avatar_url)}<span class="runtime-run-copy"><strong>${escapeHtml(run.agent_name || 'Agent')}</strong><small>${run.parent_run_id ? 'Sub-agent' : 'Primary agent'}</small></span><span class="runtime-run-state" data-status="${escapeHtml(run.status)}">${escapeHtml(statusLabel(run.status))}</span>${run.url ? `<a class="runtime-run-link" href="${escapeHtml(run.url)}">Open run</a>` : ''}</div>${steps ? `<div class="runtime-step-list">${steps}</div>` : '<div class="runtime-run-empty">No tool steps recorded.</div>'}</section>`;
        }).join('');
        body.innerHTML = `${approvals.length ? `<div class="runtime-drawer-approvals">${approvalCards(approvals)}</div>` : ''}${eventsHtml ? `<div class="runtime-events"><div class="runtime-section-title">Timeline</div>${eventsHtml}</div>` : ''}<div class="runtime-runs"><div class="runtime-section-title">Run tree</div>${runsHtml}</div>`;
        bindAvatarFallbacks(body);
    };

    const renderTopbar = () => {
        if (!activityTrigger) return;
        const active = Number(feedSummary.active_count || 0);
        const approvals = Number(feedSummary.approval_count || 0);
        const count = active > 0 ? active : approvals;
        if (activityCount) {
            activityCount.textContent = count > 99 ? '99+' : String(count);
            activityCount.hidden = count < 1;
        }
        activityDot?.classList.toggle('is-live', active > 0);
        activityDot?.classList.toggle('is-waiting', active < 1 && approvals > 0);
        activityTrigger.classList.toggle('has-activity', active > 0 || approvals > 0);
        activityTrigger.title = active || approvals ? `Agent activity · ${active} active · ${approvals} approval${approvals === 1 ? '' : 's'}` : 'Agent activity';
    };

    const renderHistoryStatus = (items) => {
        for (const item of items) {
            const link = [...document.querySelectorAll('[data-chat-history-thread]')]
                .find((node) => String(node.dataset.chatHistoryThread) === String(item.thread_id));
            if (!link) continue;
            link.dataset.runtimeStatus = item.status;
            let status = link.querySelector('[data-chat-history-status]');
            if (!status) {
                status = document.createElement('small');
                status.dataset.chatHistoryStatus = '1';
                status.className = 'chat-history-runtime-status';
                link.appendChild(status);
            }
            status.textContent = statusLabel(item.status);
        }
    };

    const currentFeedItem = () => {
        const threadId = currentChatThreadId();
        const activityUrl = currentChatActivityUrl();
        return feedItems.find((item) => threadId && String(item.thread_id) === threadId)
            || feedItems.find((item) => activityUrl && String(item.activity_url) === activityUrl)
            || null;
    };

    const fetchProjection = async (url) => {
        if (!url) return null;
        const response = await fetch(url, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
        if (!response.ok) return null;
        return response.json();
    };

    const clearDetailTimer = () => {
        if (detailTimer) window.clearTimeout(detailTimer);
        detailTimer = null;
    };
    const scheduleDetail = () => {
        clearDetailTimer();
        if (!drawerOpen || !selectedItem || !(selectedProjection?.runs || []).some((run) => !terminal(run.status))) return;
        detailTimer = window.setTimeout(refreshSelectedProjection, document.hidden ? 8000 : 1200);
    };
    const refreshSelectedProjection = async () => {
        if (!drawerOpen || !selectedItem?.activity_url) return;
        const token = ++detailRequest;
        try {
            const data = await fetchProjection(selectedItem.activity_url);
            if (token !== detailRequest || !data) return;
            selectedProjection = data;
            renderDrawerProjection(data);
            if (String(selectedItem.activity_url) === String(currentChatActivityUrl())) {
                currentChatProjection = data;
                renderInlineApprovals(data);
            }
        } catch (_) {}
        scheduleDetail();
    };

    const selectItem = (item) => {
        if (!item?.activity_url) return;
        selectedItem = item;
        selectedProjection = null;
        renderFeedList();
        const body = drawer.querySelector('[data-runtime-drawer-body]');
        if (body) body.innerHTML = '<div class="runtime-empty">Loading agent activity…</div>';
        refreshSelectedProjection();
    };

    const defaultItem = () => {
        const current = currentFeedItem();
        if (current) return current;
        const direct = currentChatActivityUrl();
        if (direct) return {activity_url:direct, run_id:'', title:'Current chat', agent_name:'Agent', status:'running'};
        return feedItems.find((item) => !terminal(item.status)) || feedItems[0] || null;
    };

    const openDrawer = () => {
        drawerOpen = true;
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        activityTrigger?.setAttribute('aria-expanded', 'true');
        renderFeedList();
        const preferred = defaultItem();
        if (preferred && (!selectedItem || String(selectedItem.activity_url) !== String(preferred.activity_url))) selectItem(preferred);
        else if (selectedItem) refreshSelectedProjection();
    };
    const closeDrawer = () => {
        drawerOpen = false;
        detailRequest += 1;
        clearDetailTimer();
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        activityTrigger?.setAttribute('aria-expanded', 'false');
    };
    backdrop.addEventListener('click', closeDrawer);
    drawer.querySelector('[data-runtime-drawer-close]')?.addEventListener('click', closeDrawer);
    activityTrigger?.addEventListener('click', openDrawer);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawerOpen) closeDrawer(); });

    const maybeProbeCurrentApprovals = async () => {
        if (!shell) return;
        const item = currentFeedItem();
        if (!item || Number(item.approval_count || 0) < 1) {
            lastInlineProbeKey = '';
            currentChatProjection = null;
            renderInlineApprovals({pending_approvals:[]});
            return;
        }
        const key = `${item.run_id}:${item.approval_count}:${item.updated_at || ''}`;
        if (key === lastInlineProbeKey && currentChatProjection) {
            renderInlineApprovals(currentChatProjection);
            return;
        }
        lastInlineProbeKey = key;
        try {
            const data = await fetchProjection(item.activity_url);
            if (!data) return;
            currentChatProjection = data;
            renderInlineApprovals(data);
            if (drawerOpen && selectedItem && String(selectedItem.activity_url) === String(item.activity_url)) {
                selectedProjection = data;
                renderDrawerProjection(data);
            }
        } catch (_) {}
    };

    const stateKey = `enverif-runtime:${document.body.dataset.runtimeWorkspace || runtimeFeedUrl || 'workspace'}`;
    try { previousFeed = JSON.parse(sessionStorage.getItem(stateKey) || 'null'); } catch (_) { previousFeed = null; }
    const applyFeed = (data) => {
        const items = Array.isArray(data?.threads) ? data.threads : [];
        feedItems = items;
        feedSummary = data?.summary && typeof data.summary === 'object' ? data.summary : {
            active_count: items.filter((item) => !terminal(item.status)).length,
            approval_count: items.reduce((sum, item) => sum + Number(item.approval_count || 0), 0),
        };
        renderHistoryStatus(items);
        renderTopbar();
        if (drawerOpen) renderFeedList();

        const current = Object.fromEntries(items.map((item) => [String(item.thread_id), {run_id:item.run_id,status:item.status,title:item.title,agent_name:item.agent_name}]));
        if (previousFeed) {
            for (const [threadId, item] of Object.entries(current)) {
                const before = previousFeed[threadId];
                if (!before || String(before.run_id) !== String(item.run_id) || before.status === item.status) continue;
                if (item.status === 'completed') showToast(`${item.agent_name || 'Agent'} finished`, item.title || 'Your task is ready.');
                if (item.status === 'failed') showToast(`${item.agent_name || 'Agent'} needs attention`, `${item.title || 'Task'} failed. Open the chat to review it.`);
            }
        }
        previousFeed = current;
        try { sessionStorage.setItem(stateKey, JSON.stringify(current)); } catch (_) {}
        maybeProbeCurrentApprovals();
    };

    const pollFeed = async () => {
        if (!runtimeFeedUrl) return;
        try {
            const response = await fetch(runtimeFeedUrl, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
            if (response.ok) applyFeed(await response.json());
        } catch (_) {}
        feedTimer = window.setTimeout(pollFeed, document.hidden ? 15000 : 4000);
    };
    if (runtimeFeedUrl) feedTimer = window.setTimeout(pollFeed, 500);

    const decide = async (button) => {
        const url = button.dataset.url;
        const decision = button.dataset.runtimeApproval;
        if (!url || !['approved','denied'].includes(decision)) return;
        const card = button.closest('[data-approval-id]');
        card?.querySelectorAll('button').forEach((item) => { item.disabled = true; });
        try {
            const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:JSON.stringify({decision})});
            if (!response.ok && response.status !== 409) throw new Error('Unable to record the decision.');
            lastInlineProbeKey = '';
            if (runtimeFeedUrl) {
                const feedResponse = await fetch(runtimeFeedUrl, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
                if (feedResponse.ok) applyFeed(await feedResponse.json());
            }
            if (drawerOpen) await refreshSelectedProjection();
            else await maybeProbeCurrentApprovals();
        } catch (error) {
            card?.querySelectorAll('button').forEach((item) => { item.disabled = false; });
            showToast('Approval failed', error instanceof Error ? error.message : 'Unable to record the decision.');
        }
    };

    const markChatActivityTriggers = () => {
        if (!shell) return;
        shell.querySelectorAll('[data-chat-thread-run], [data-chat-thinking]').forEach((element) => {
            element.setAttribute('role', 'button');
            element.setAttribute('tabindex', '0');
            element.title = 'Open Agent activity';
        });
    };
    markChatActivityTriggers();

    document.addEventListener('click', (event) => {
        const approval = event.target.closest?.('[data-runtime-approval]');
        if (approval) { event.preventDefault(); decide(approval); return; }
        const selector = event.target.closest?.('[data-runtime-select-activity]');
        if (selector) {
            event.preventDefault();
            const item = feedItems.find((candidate) => String(candidate.activity_url) === String(selector.dataset.url));
            if (item) selectItem(item);
            return;
        }
        if (event.target.closest?.('[data-runtime-open-drawer]')) { event.preventDefault(); openDrawer(); return; }
        const trigger = event.target.closest?.('[data-chat-thread-run], [data-chat-thinking]');
        if (trigger && shell?.contains(trigger)) { event.preventDefault(); openDrawer(); }
    }, true);
    document.addEventListener('keydown', (event) => {
        const trigger = event.target.closest?.('[data-chat-thread-run], [data-chat-thinking]');
        if (trigger && shell?.contains(trigger) && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openDrawer(); }
    });

    // app.js replaces chat transcript HTML while polling. Reapply only approval UI and
    // ignore mutations created inside the approval mount so repainting cannot recurse.
    if (chatScroll && typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver((mutations) => {
            const transcriptChanged = mutations.some((mutation) => {
                const target = mutation.target;
                if (!(target instanceof Element)) return false;
                return !target.closest('[data-runtime-approval-stack]');
            });
            if (!transcriptChanged) return;
            markChatActivityTriggers();
            if (currentChatProjection) renderInlineApprovals(currentChatProjection);
        });
        observer.observe(chatScroll, {childList:true, subtree:true});
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            if (feedTimer) window.clearTimeout(feedTimer);
            if (runtimeFeedUrl) pollFeed();
            if (drawerOpen && selectedItem) refreshSelectedProjection();
        }
    });
    window.addEventListener('beforeunload', () => {
        if (feedTimer) window.clearTimeout(feedTimer);
        clearDetailTimer();
    });
})();