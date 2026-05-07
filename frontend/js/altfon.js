// API Configuration
const CONFIG = {
    API_BASE: 'https://appapi.altfon.com/api',
    APP_NAME: 'Altfon Booth'
};

// Authentication Helpers
const Auth = {
    setToken(token) {
        localStorage.setItem('altfon_token', token);
    },
    getToken() {
        return localStorage.getItem('altfon_token');
    },
    setUser(user) {
        localStorage.setItem('altfon_user', JSON.stringify(user));
    },
    getUser() {
        const user = localStorage.getItem('altfon_user');
        return user ? JSON.parse(user) : null;
    },
    logout() {
        localStorage.removeItem('altfon_token');
        localStorage.removeItem('altfon_user');
        window.location.href = 'login';
    },
    isAuthenticated() {
        return !!this.getToken();
    }
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

// Global Request Wrapper
async function apiRequest(endpoint, options = {}) {
    const token = Auth.getToken();

    const defaultHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    if (token) {
        defaultHeaders['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${CONFIG.API_BASE}${endpoint}`, {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers
        }
    });

    const data = await response.json();

    if (!response.ok) {
        if (response.status === 401) {
            Auth.logout();
        }
        throw new Error(data.message || 'Something went wrong');
    }

    return data;
}

// Auto-init navigation on load
document.addEventListener('DOMContentLoaded', () => {
    UI.initNavigation();
});
