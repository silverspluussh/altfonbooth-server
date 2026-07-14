// Admin Authentication (separate from subscriber Auth)
const AdminAuth = {
    TOKEN_KEY: 'altfon_admin_token',
    USER_KEY: 'altfon_admin_user',

    setToken(token) {
        localStorage.setItem(this.TOKEN_KEY, token);
    },
    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },
    setUser(user) {
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
    },
    getUser() {
        const user = localStorage.getItem(this.USER_KEY);
        return user ? JSON.parse(user) : null;
    },
    logout() {
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);
        AdminRouter.navigate('login');
        document.getElementById('app-view').style.display = 'none';
        document.getElementById('login-view').style.display = 'flex';
    },
    isAuthenticated() {
        return !!this.getToken();
    }
};

// Admin API Request
async function adminApiRequest(endpoint, options = {}) {
    const token = AdminAuth.getToken();
    const defaultHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };
    if (token) {
        defaultHeaders['Authorization'] = `Bearer ${token}`;
    }

    const method = (options.method || 'GET').toUpperCase();
    const response = await fetch(`${CONFIG.API_BASE}${endpoint}`, {
        ...options,
        headers: { ...defaultHeaders, ...options.headers }
    });

    const data = await response.json();

    if (response.status === 429) {
        throw new Error(data.message || 'Too many requests. Please wait.');
    }

    if (response.status === 401) {
        AdminAuth.logout();
        throw new Error('Session expired. Please login again.');
    }

    if (!response.ok) {
        throw new Error(data.message || 'Something went wrong');
    }

    return data;
}

// Router
const AdminRouter = {
    currentView: null,
    routes: {
        'login': renderLogin,
        'dashboard': renderDashboard,
        'subscribers': renderSubscribers,
        'auth-users': renderAuthUsers,
        'destinations': renderDestinations,
        'purchase-history': renderPurchaseHistory,
        'admins': renderAdmins,
        'settings': renderSettings,
    },

    init() {
        window.addEventListener('hashchange', () => this.resolve());
        this.resolve();
    },

    navigate(view) {
        window.location.hash = view;
    },

    resolve() {
        const hash = window.location.hash.replace('#', '') || 'dashboard';

        if (!AdminAuth.isAuthenticated()) {
            document.getElementById('app-view').style.display = 'none';
            document.getElementById('login-view').style.display = 'flex';
            return;
        }

        document.getElementById('login-view').style.display = 'none';
        document.getElementById('app-view').style.display = 'flex';

        this.currentView = hash;
        const renderFn = this.routes[hash] || renderDashboard;
        renderFn();
        setActiveNav(hash);
    }
};

// Navigation
function setActiveNav(view) {
    document.querySelectorAll('#sidebar nav a').forEach(a => a.classList.remove('active'));
    const link = document.querySelector(`#sidebar nav a[data-view="${view}"]`);
    if (link) link.classList.add('active');
}

// ---- MODAL HELPERS ----
function showModal(title, contentHtml, onSubmit, submitText = 'Save') {
    const overlay = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    const titleEl = document.getElementById('modal-title');
    const submitBtn = document.getElementById('modal-submit');
    const closeBtn = document.getElementById('modal-close');
    const cancelBtn = document.getElementById('modal-cancel-btn');

    titleEl.textContent = title;
    body.innerHTML = contentHtml;
    submitBtn.textContent = submitText;
    submitBtn.style.display = onSubmit ? 'inline-block' : 'none';
    overlay.style.display = 'flex';

    const newSubmit = submitBtn.cloneNode(true);
    submitBtn.parentNode.replaceChild(newSubmit, submitBtn);
    const newClose = closeBtn.cloneNode(true);
    closeBtn.parentNode.replaceChild(newClose, closeBtn);
    const newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);

    const doHide = () => hideModal();
    newClose.addEventListener('click', doHide);
    newCancel.addEventListener('click', doHide);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) doHide(); });

    if (onSubmit) {
        newSubmit.addEventListener('click', async () => {
            newSubmit.disabled = true;
            newSubmit.textContent = 'Saving...';
            try {
                await onSubmit();
                hideModal();
            } catch (e) {
                UI.showToast(e.message, 'error');
            } finally {
                newSubmit.disabled = false;
                newSubmit.textContent = submitText;
            }
        });
    }
}

function hideModal() {
    document.getElementById('modal-overlay').style.display = 'none';
}

function showConfirm(message, onConfirm) {
    showModal('Confirm', `<p>${message}</p>`, async () => {
        await onConfirm();
    }, 'Confirm');
}

// ---- TABLE HELPER ----
function createTable(data, columns, actions = null) {
    if (!data || data.length === 0) {
        return '<p class="empty-state">No records found.</p>';
    }
    let html = '<div class="table-wrap"><table><thead><tr>';
    columns.forEach(col => { html += `<th>${col.label}</th>`; });
    if (actions) html += '<th>Actions</th>';
    html += '</tr></thead><tbody>';

    data.forEach(row => {
        html += '<tr>';
        columns.forEach(col => {
            let val = col.render ? col.render(row) : (row[col.key] ?? '');
            html += `<td>${val}</td>`;
        });
        if (actions) {
            html += '<td class="action-cell">';
            actions.forEach(action => {
                if (action.show && !action.show(row)) return;
                html += `<button class="btn btn-sm btn-${action.style || 'outline'}" onclick="(${action.handler.toString()})(${JSON.stringify(row).replace(/"/g, '&quot;')})">${action.label}</button> `;
            });
            html += '</td>';
        }
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    return html;
}

function createStatCards(cards) {
    return cards.map(c => `
        <div class="stat-card">
            <div class="stat-icon" style="background:${c.color}15; color:${c.color}">${c.icon}</div>
            <div class="stat-info">
                <p>${c.label}</p>
                <h3>${c.value}</h3>
            </div>
        </div>
    `).join('');
}

function showLoading(container) {
    document.getElementById(container).innerHTML = '<div class="loader-wrapper"><div class="spinner"></div><p>Loading...</p></div>';
}

// ============ VIEW RENDERERS ============

// ---- LOGIN ----
function renderLogin() {
    document.getElementById('login-view').style.display = 'flex';
    document.getElementById('app-view').style.display = 'none';
}

// ---- DASHBOARD ----
async function renderDashboard() {
    const container = document.getElementById('app-content');
    container.innerHTML = '<h2>Dashboard</h2><div id="dashboard-content"></div>';
    showLoading('dashboard-content');

    try {
        const res = await adminApiRequest('/admin/dashboard');
        const d = res.data;

        document.getElementById('dashboard-content').innerHTML = `
            <div class="stat-grid">
                ${createStatCards([
                    { label: 'Subscribers', value: d.total_subscribers, icon: '&#128101;', color: '#010a4c' },
                    { label: 'SIP Accounts', value: d.total_auth_users, icon: '&#128279;', color: '#2563eb' },
                    { label: 'Destinations', value: d.total_destinations, icon: '&#128205;', color: '#059669' },
                    { label: 'Total Credits (GH₵)', value: Number(d.total_credits_amount).toFixed(2), icon: '&#128176;', color: '#d97706' },
                    { label: 'Transactions', value: d.total_transactions, icon: '&#128203;', color: '#7c3aed' },
                ])}
            </div>
            <div class="dashboard-grid">
                <div class="card">
                    <h3>Recent Subscribers</h3>
                    <div id="recent-subs">${renderRecentSubs(d.recent_subscribers)}</div>
                </div>
                <div class="card">
                    <h3>Recent Transactions</h3>
                    <div id="recent-txns">${renderRecentTxns(d.recent_transactions)}</div>
                </div>
            </div>
        `;
    } catch (e) {
        document.getElementById('dashboard-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

function renderRecentSubs(list) {
    if (!list || !list.length) return '<p class="empty-state">No recent subscribers</p>';
    return `<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Date</th></tr></thead><tbody>
        ${list.map(s => `<tr><td>${UI.esc(s.fullname)}</td><td>${UI.esc(s.emailaddress)}</td><td>${UI.esc(s.username)}</td><td>${s.regdatetime ? new Date(s.regdatetime).toLocaleDateString() : ''}</td></tr>`).join('')}
    </tbody></table></div>`;
}

function renderRecentTxns(list) {
    if (!list || !list.length) return '<p class="empty-state">No recent transactions</p>';
    return `<div class="table-wrap"><table><thead><tr><th>SIP</th><th>Amount</th><th>Date</th></tr></thead><tbody>
        ${list.map(t => `<tr><td>${UI.esc(t.authusername)}</td><td>GH₵${Number(t.amount).toFixed(2)}</td><td>${t.created_at ? new Date(t.created_at).toLocaleDateString() : ''}</td></tr>`).join('')}
    </tbody></table></div>`;
}

// ---- SUBSCRIBERS ----
async function renderSubscribers() {
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <div class="page-header">
            <h2>Subscribers</h2>
            <button class="btn btn-primary" onclick="showSubscriberModal()">+ Add Subscriber</button>
        </div>
        <div class="search-bar">
            <input type="text" id="subs-search" placeholder="Search by name, email, or username..." oninput="filterSubscribers()">
        </div>
        <div id="subs-content"></div>
    `;
    showLoading('subs-content');
    await loadSubscribers();
}

let _allSubscribers = [];

async function loadSubscribers() {
    try {
        const res = await adminApiRequest('/admin/subscribers');
        _allSubscribers = res.data || [];
        renderSubscribersTable(_allSubscribers);
    } catch (e) {
        document.getElementById('subs-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

function filterSubscribers() {
    const q = document.getElementById('subs-search').value.toLowerCase();
    const filtered = _allSubscribers.filter(s =>
        (s.fullname || '').toLowerCase().includes(q) ||
        (s.emailaddress || '').toLowerCase().includes(q) ||
        (s.username || '').toLowerCase().includes(q) ||
        (s.subscriberid || '').toLowerCase().includes(q)
    );
    renderSubscribersTable(filtered);
}

function renderSubscribersTable(list) {
    document.getElementById('subs-content').innerHTML = createTable(list, [
        { label: 'ID', render: s => `<code>${UI.esc(s.subscriberid)}</code>` },
        { label: 'Name', key: 'fullname' },
        { label: 'Email', key: 'emailaddress' },
        { label: 'Username', key: 'username' },
        { label: 'Phone', key: 'phonenumber' },
        { label: 'Country', key: 'country' },
    ], [
        { label: 'Edit', style: 'primary', handler: (row) => showSubscriberModal(row) },
        { label: 'Delete', style: 'danger', handler: (row) => confirmDeleteSubscriber(row) },
    ]);
}

function showSubscriberModal(sub = null) {
    const isEdit = !!sub;
    const title = isEdit ? 'Edit Subscriber' : 'Add Subscriber';
    const html = `
        <form id="sub-form">
            <label>Full Name *</label>
            <input name="fullname" value="${UI.esc(sub?.fullname || '')}" required>
            <label>Username *</label>
            <input name="username" value="${UI.esc(sub?.username || '')}" required>
            <label>Email *</label>
            <input name="emailaddress" type="email" value="${UI.esc(sub?.emailaddress || '')}" required>
            <label>Phone</label>
            <input name="phonenumber" value="${UI.esc(sub?.phonenumber || '')}">
            <label>Country</label>
            <input name="country" value="${UI.esc(sub?.country || '')}">
            ${isEdit ? '' : '<label>Password *</label><input name="password" type="password" required>'}
        </form>
    `;

    showModal(title, html, async () => {
        const form = document.getElementById('sub-form');
        const data = Object.fromEntries(new FormData(form));
        if (isEdit) {
            await adminApiRequest(`/admin/subscribers/${sub.subscriberid}`, {
                method: 'PUT', body: JSON.stringify(data)
            });
            UI.showToast('Subscriber updated');
        } else {
            await adminApiRequest('/admin/subscribers', {
                method: 'POST', body: JSON.stringify(data)
            });
            UI.showToast('Subscriber created');
        }
        await loadSubscribers();
    }, isEdit ? 'Update' : 'Create');
}

function confirmDeleteSubscriber(sub) {
    showConfirm(
        `Delete subscriber <strong>${UI.esc(sub.fullname)}</strong> (${UI.esc(sub.subscriberid)})? This will remove all their SIP accounts, destinations, and credits.`,
        async () => {
            await adminApiRequest(`/admin/subscribers/${sub.subscriberid}`, { method: 'DELETE' });
            UI.showToast('Subscriber deleted');
            await loadSubscribers();
        }
    );
}

// ---- SIP ACCOUNTS (Auth Users) ----
async function renderAuthUsers() {
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <div class="page-header">
            <h2>SIP Accounts</h2>
            <button class="btn btn-primary" onclick="showAuthUserModal()">+ Add SIP Account</button>
        </div>
        <div id="auth-users-content"></div>
    `;
    showLoading('auth-users-content');
    await loadAuthUsers();
}

let _allAuthUsers = [];

async function loadAuthUsers() {
    try {
        const res = await adminApiRequest('/admin/auth-users');
        _allAuthUsers = res.data || [];
        renderAuthUsersTable(_allAuthUsers);
    } catch (e) {
        document.getElementById('auth-users-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

function renderAuthUsersTable(list) {
    document.getElementById('auth-users-content').innerHTML = createTable(list, [
        { label: 'ID', key: 'id' },
        { label: 'Subscriber ID', key: 'subscriberid' },
        { label: 'SIP Number', key: 'authusername' },
        { label: 'Balance (GH₵)', render: a => Number(a.balance || 0).toFixed(2) },
        { label: 'Status', render: a => `<span class="badge badge-${a.status === 'active' ? 'success' : 'danger'}">${UI.esc(a.status)}</span>` },
        { label: 'Created', render: a => a.created_at ? new Date(a.created_at).toLocaleDateString() : '' },
    ], [
        {
            label: 'Activate',
            style: 'success',
            handler: (row) => toggleAuthUserStatus(row, 'active'),
            show: (row) => row.status !== 'active'
        },
        {
            label: 'Deactivate',
            style: 'warning',
            handler: (row) => toggleAuthUserStatus(row, 'inactive'),
            show: (row) => row.status === 'active'
        },
        { label: 'Edit', style: 'primary', handler: (row) => showAuthUserModal(row) },
        { label: 'Delete', style: 'danger', handler: (row) => confirmDeleteAuthUser(row) },
    ]);
}

async function toggleAuthUserStatus(auth, newStatus) {
    const label = newStatus === 'active' ? 'Activate' : 'Deactivate';
    showConfirm(
        `${label} SIP account <strong>${UI.esc(auth.authusername)}</strong>?`,
        async () => {
            await adminApiRequest(`/admin/auth-users/${auth.id}`, {
                method: 'PUT', body: JSON.stringify({ status: newStatus })
            });
            UI.showToast(`SIP account ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
            await loadAuthUsers();
        }
    );
}

async function showAuthUserModal(auth = null) {
    const isEdit = !!auth;
    const title = isEdit ? 'Edit SIP Account' : 'Add SIP Account';

    // Fetch subscribers for dropdown
    let subOptions = '';
    try {
        const res = await adminApiRequest('/admin/subscribers');
        (res.data || []).forEach(s => {
            const sel = auth && s.subscriberid === auth.subscriberid ? 'selected' : '';
            subOptions += `<option value="${UI.esc(s.subscriberid)}" ${sel}>${UI.esc(s.fullname)} (${UI.esc(s.subscriberid)})</option>`;
        });
    } catch (_) {}

    const html = `
        <form id="auth-form">
            <label>Subscriber ${isEdit ? '' : '*'} ${isEdit ? `<code>${UI.esc(auth.subscriberid)}</code>` : ''}</label>
            ${isEdit ? '' : `<select name="subscriberid" required><option value="">Select subscriber...</option>${subOptions}</select>`}
            <label>SIP Number *</label>
            <input name="authusername" value="${UI.esc(auth?.authusername || '')}" ${isEdit ? '' : 'required'} ${isEdit ? 'readonly style="background:#f1f5f9"' : ''}>
            <label>Password</label>
            <input name="authpassword" value="${UI.esc(auth?.authpassword || '')}">
            <label>Status</label>
            <select name="status">
                <option value="active" ${auth?.status === 'active' ? 'selected' : ''}>Active</option>
                <option value="inactive" ${auth?.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                <option value="suspended" ${auth?.status === 'suspended' ? 'selected' : ''}>Suspended</option>
            </select>
        </form>
    `;

    showModal(title, html, async () => {
        const form = document.getElementById('auth-form');
        const data = Object.fromEntries(new FormData(form));
        // Remove empty password
        if (!data.authpassword) delete data.authpassword;

        if (isEdit) {
            await adminApiRequest(`/admin/auth-users/${auth.id}`, {
                method: 'PUT', body: JSON.stringify(data)
            });
            UI.showToast('SIP account updated');
        } else {
            await adminApiRequest('/admin/auth-users', {
                method: 'POST', body: JSON.stringify(data)
            });
            UI.showToast('SIP account created');
        }
        await loadAuthUsers();
    }, isEdit ? 'Update' : 'Create');
}

function confirmDeleteAuthUser(auth) {
    showConfirm(
        `Delete SIP account <strong>${UI.esc(auth.authusername)}</strong>? This will also remove all destinations and credits for this account.`,
        async () => {
            await adminApiRequest(`/admin/auth-users/${auth.id}`, { method: 'DELETE' });
            UI.showToast('SIP account deleted');
            await loadAuthUsers();
        }
    );
}

// ---- DESTINATIONS ----
async function renderDestinations() {
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <div class="page-header">
            <h2>Destinations</h2>
            <button class="btn btn-primary" onclick="showDestinationModal()">+ Add Destination</button>
        </div>
        <div id="dests-content"></div>
    `;
    showLoading('dests-content');
    await loadDestinations();
}

let _allDestinations = [];

async function loadDestinations() {
    try {
        const res = await adminApiRequest('/admin/destinations');
        _allDestinations = res.data || [];
        renderDestinationsTable(_allDestinations);
    } catch (e) {
        document.getElementById('dests-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

function renderDestinationsTable(list) {
    document.getElementById('dests-content').innerHTML = createTable(list, [
        { label: 'ID', key: 'id' },
        { label: 'Subscriber', key: 'subscriberid' },
        { label: 'SIP', key: 'authusername' },
        { label: 'Destination', key: 'destination' },
        { label: 'Status', render: d => `<span class="badge badge-${d.status === 'active' ? 'success' : 'secondary'}">${UI.esc(d.status)}</span>` },
        { label: 'Created', render: d => d.created_at ? new Date(d.created_at).toLocaleDateString() : '' },
    ], [
        { label: 'Edit', style: 'primary', handler: (row) => showDestinationModal(row) },
        { label: 'Delete', style: 'danger', handler: (row) => confirmDeleteDestination(row) },
    ]);
}

async function showDestinationModal(dest = null) {
    const isEdit = !!dest;
    const title = isEdit ? 'Edit Destination' : 'Add Destination';

    const html = `
        <form id="dest-form">
            ${isEdit ? '' : `
                <label>Subscriber ID *</label>
                <input name="subscriberid" value="${UI.esc(dest?.subscriberid || '')}" required>
                <label>SIP Number *</label>
                <input name="authusername" value="${UI.esc(dest?.authusername || '')}" required>
            `}
            ${isEdit ? `<p>SIP: <strong>${UI.esc(dest.authusername)}</strong></p>` : ''}
            <label>Destination *</label>
            <input name="destination" value="${UI.esc(dest?.destination || '')}" required>
            <label>Status</label>
            <select name="status">
                <option value="active" ${dest?.status === 'active' ? 'selected' : ''}>Active</option>
                <option value="inactive" ${dest?.status === 'inactive' ? 'selected' : ''}>Inactive</option>
            </select>
        </form>
    `;

    showModal(title, html, async () => {
        const form = document.getElementById('dest-form');
        const data = Object.fromEntries(new FormData(form));
        if (isEdit) {
            await adminApiRequest(`/admin/destinations/${dest.id}`, {
                method: 'PUT', body: JSON.stringify(data)
            });
            UI.showToast('Destination updated');
        } else {
            await adminApiRequest('/admin/destinations', {
                method: 'POST', body: JSON.stringify(data)
            });
            UI.showToast('Destination created');
        }
        await loadDestinations();
    }, isEdit ? 'Update' : 'Create');
}

function confirmDeleteDestination(dest) {
    showConfirm(
        `Delete destination <strong>${UI.esc(dest.destination)}</strong> for SIP ${UI.esc(dest.authusername)}?`,
        async () => {
            await adminApiRequest(`/admin/destinations/${dest.id}`, { method: 'DELETE' });
            UI.showToast('Destination deleted');
            await loadDestinations();
        }
    );
}

// ---- PURCHASE HISTORY & CREDITS ----
async function renderPurchaseHistory() {
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <div class="page-header">
            <h2>Credits & Purchase History</h2>
            <button class="btn btn-primary" onclick="showAddCreditsModal()">+ Add Credits</button>
        </div>
        <div id="purchase-content"></div>
    `;
    showLoading('purchase-content');
    await loadPurchaseHistory();
}

async function loadPurchaseHistory() {
    try {
        const res = await adminApiRequest('/admin/purchase-history');
        const data = res.data || [];
        document.getElementById('purchase-content').innerHTML = createTable(data, [
            { label: 'ID', key: 'id' },
            { label: 'SIP', key: 'authusername' },
            { label: 'Amount (GH₵)', render: t => Number(t.amount).toFixed(4) },
            { label: 'Transaction ID', key: 'transaction_id' },
            { label: 'Status', render: t => `<span class="badge badge-${t.status === 'completed' ? 'success' : 'warning'}">${UI.esc(t.status)}</span>` },
            { label: 'Date', render: t => t.created_at ? new Date(t.created_at).toLocaleString() : '' },
        ]);
    } catch (e) {
        document.getElementById('purchase-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

async function showAddCreditsModal() {
    const html = `
        <form id="credits-form">
            <label>SIP Number *</label>
            <input name="authusername" placeholder="e.g. 694622" required>
            <label>Amount (GH₵) *</label>
            <input name="amount" type="number" step="0.01" min="0.01" required>
        </form>
    `;

    showModal('Add Credits', html, async () => {
        const form = document.getElementById('credits-form');
        const data = Object.fromEntries(new FormData(form));
        data.amount = parseFloat(data.amount);
        const res = await adminApiRequest('/admin/credits', {
            method: 'POST', body: JSON.stringify(data)
        });
        UI.showToast(`Credits added. New balance: GH₵${res.balance}`);
        await loadPurchaseHistory();
    }, 'Add Credits');
}

// ---- ADMIN MANAGEMENT (super_admin only) ----
async function renderAdmins() {
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <div class="page-header">
            <h2>Admin Management</h2>
            <button class="btn btn-primary" onclick="showAdminModal()">+ Add Admin</button>
        </div>
        <div id="admins-content"></div>
    `;
    showLoading('admins-content');
    await loadAdmins();
}

async function loadAdmins() {
    try {
        const res = await adminApiRequest('/admin/admins');
        const data = res.data || res || [];
        document.getElementById('admins-content').innerHTML = createTable(data, [
            { label: 'ID', key: 'id' },
            { label: 'Name', key: 'name' },
            { label: 'Username', key: 'username' },
            { label: 'Email', key: 'email' },
            { label: 'Role', render: a => `<span class="badge badge-${a.role === 'super_admin' ? 'warning' : 'primary'}">${UI.esc(a.role)}</span>` },
            { label: 'Created', render: a => a.created_at ? new Date(a.created_at).toLocaleDateString() : '' },
        ], [
            { label: 'Delete', style: 'danger', handler: (row) => confirmDeleteAdmin(row) },
        ]);
    } catch (e) {
        document.getElementById('admins-content').innerHTML = `<p class="error-state">${e.message}</p>`;
    }
}

function showAdminModal() {
    const html = `
        <form id="admin-form">
            <label>Name *</label>
            <input name="name" required>
            <label>Username *</label>
            <input name="username" required>
            <label>Email *</label>
            <input name="email" type="email" required>
            <label>Password *</label>
            <input name="password" type="password" minlength="6" required>
            <label>Role *</label>
            <select name="role">
                <option value="manager">Manager</option>
                <option value="super_admin">Super Admin</option>
            </select>
        </form>
    `;

    showModal('Add Admin', html, async () => {
        const form = document.getElementById('admin-form');
        const data = Object.fromEntries(new FormData(form));
        await adminApiRequest('/admin/admins', {
            method: 'POST', body: JSON.stringify(data)
        });
        UI.showToast('Admin created');
        await loadAdmins();
    }, 'Create');
}

function confirmDeleteAdmin(admin) {
    showConfirm(
        `Delete admin <strong>${UI.esc(admin.name)}</strong> (${UI.esc(admin.username)})?`,
        async () => {
            await adminApiRequest(`/admin/admins/${admin.id}`, { method: 'DELETE' });
            UI.showToast('Admin deleted');
            await loadAdmins();
        }
    );
}

// ---- SETTINGS ----
async function renderSettings() {
    const user = AdminAuth.getUser();
    const container = document.getElementById('app-content');
    container.innerHTML = `
        <h2>Settings</h2>
        <div class="card" style="max-width: 500px;">
            <form id="settings-form">
                <label>Name</label>
                <input name="name" value="${UI.esc(user?.name || '')}">
                <label>Email</label>
                <input name="email" type="email" value="${UI.esc(user?.email || '')}">
                <label>New Password (leave blank to keep current)</label>
                <input name="password" type="password">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    `;

    document.getElementById('settings-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        if (!data.password) delete data.password;
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
            const res = await adminApiRequest('/admin/profile', {
                method: 'PUT', body: JSON.stringify(data)
            });
            if (res.data) AdminAuth.setUser(res.data);
            UI.showToast('Profile updated');
        } catch (err) {
            UI.showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        }
    });
}

// ---- LOGIN FORM HANDLER ----
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Logging in...';

            try {
                const res = await adminApiRequest('/admin/login', {
                    method: 'POST',
                    body: JSON.stringify({ username, password })
                });
                AdminAuth.setToken(res.token);
                AdminAuth.setUser(res.admin);
                AdminRouter.navigate('dashboard');
                document.getElementById('login-view').style.display = 'none';
                document.getElementById('app-view').style.display = 'flex';
            } catch (err) {
                UI.showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Login';
            }
        });
    }

    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            AdminAuth.logout();
        });
    }

    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('sidebar-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
        });
    }

    // Init router
    if (AdminAuth.isAuthenticated()) {
        AdminRouter.init();
    } else {
        renderLogin();
    }
});
