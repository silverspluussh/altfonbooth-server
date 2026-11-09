// API Configuration
const CONFIG = {
    API_BASE: 'https://appapi.altfon.com/api',
    APP_NAME: 'Altfon Booth'
};

// UI Utilities
const UI = {
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerText = message;
        document.body.appendChild(toast);

        // Add styles dynamically if not present
        if (!document.getElementById('toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.innerHTML = `
                .toast {
                    position: fixed;
                    bottom: 2rem;
                    right: 2rem;
                    padding: 1rem 2rem;
                    border-radius: 12px;
                    color: white;
                    font-weight: 600;
                    z-index: 9999;
                    animation: slideUp 0.3s ease-out;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                }
                .toast-success { background: #010a4c; }
                .toast-info { background: #0d6efd; }
                .toast-error { background: #DC3545; }
                @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            `;
            document.head.appendChild(style);
        }

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
    initNavigation() {
        const token = Auth.getToken();
        const accountLinks = document.querySelectorAll('a[href="login"]');

        if (token && accountLinks.length > 0) {
            accountLinks.forEach(link => {
                const parentLi = link.closest('li');
                if (parentLi) {
                    // Update text and link to Dashboard
                    link.href = 'dashboard';
                    link.innerHTML = 'Dashboard';

                    // Add Logout button next to it
                    const logoutLi = document.createElement('li');
                    logoutLi.className = 'color-10';
                    logoutLi.innerHTML = `<a href="javascript:Auth.logout()" class="has-icon" style="color: #DC3545 !important;">Logout</a>`;
                    parentLi.parentNode.insertBefore(logoutLi, parentLi.nextSibling);
                }
            });
        }
    },
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Subscriber session: JWT lifecycle, refresh, idle timeout and cross-tab sync.
const sessionPage = (window.location.pathname.split('/').pop() || '').toLowerCase();
const sessionProtectedPages = [
    'dashboard',
    'dashboard.html',
    'my-account',
    'my-account.html',
    'checkout',
    'checkout.html'
];
const sessionIsProtected = sessionProtectedPages.includes(sessionPage);

const UserSession = new SessionManager({
    apiBase: CONFIG.API_BASE,
    tokenKey: 'altfon_token',
    userKey: 'altfon_user',
    refreshPath: '/refresh',
    logoutPath: '/logout',
    loginUrl: 'login',
    // Idle logout only applies to signed-in app pages, not the public site.
    idleMs: sessionIsProtected ? 30 * 60 * 1000 : 0,
    warningMs: sessionIsProtected ? 28 * 60 * 1000 : 0,
    refreshLeadMs: 15 * 60 * 1000,
    toast: (message, type) => UI.showToast(message, type),
    onExpire: () => {
        if (!sessionIsProtected) return;

        const next = encodeURIComponent(`${sessionPage}${window.location.search}`);
        setTimeout(() => {
            window.location.href = `login?expired=1&next=${next}`;
        }, 1200);
    }
});

UserSession.init();

// Backwards-compatible authentication helpers used across the site.
const Auth = {
    setToken(token) {
        UserSession.setToken(token);
    },
    getToken() {
        return UserSession.getToken();
    },
    setUser(user) {
        UserSession.setUser(user);
    },
    getUser() {
        return UserSession.getUser();
    },
    setSession(token, user) {
        UserSession.setSession(token, user);
    },
    logout() {
        return UserSession.logout({ redirect: true });
    },
    isAuthenticated() {
        return UserSession.isAuthenticated();
    }
};

// Global Request Wrapper
async function apiRequest(endpoint, options = {}) {
    return UserSession.request(endpoint, options);
}

// Auto-init navigation on load
document.addEventListener('DOMContentLoaded', () => {
    UI.initNavigation();
});
