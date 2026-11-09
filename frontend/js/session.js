/* Shared JWT session manager for the subscriber and admin web apps. */
(function (global) {
    'use strict';

    const DEFAULT_API_BASE = 'https://appapi.altfon.com/api';
    const MAX_TIMEOUT = 2147483647;

    class SessionExpiredError extends Error {
        constructor(message = 'Session expired. Please log in again.') {
            super(message);
            this.name = 'SessionExpiredError';
        }
    }

    function decodeJwt(token) {
        try {
            const parts = String(token || '').split('.');
            if (parts.length < 2) return null;

            const normalized = parts[1].replace(/-/g, '+').replace(/_/g, '/');
            const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=');
            const decoded = global.atob(padded);
            const json = decodeURIComponent(
                Array.prototype.map
                    .call(decoded, (char) => '%' + ('00' + char.charCodeAt(0).toString(16)).slice(-2))
                    .join('')
            );

            return JSON.parse(json);
        } catch (error) {
            return null;
        }
    }

    class SessionManager {
        constructor(options = {}) {
            this.apiBase = String(options.apiBase || DEFAULT_API_BASE).replace(/\/+$/, '');
            this.tokenKey = options.tokenKey || 'altfon_token';
            this.userKey = options.userKey || 'altfon_user';
            this.refreshPath = options.refreshPath || '/refresh';
            this.logoutPath = options.logoutPath || '/logout';
            this.loginUrl = options.loginUrl || 'login';
            this.idleMs = options.idleMs === 0 ? 0 : Math.max(1000, options.idleMs || 30 * 60 * 1000);
            this.warningMs = options.warningMs === 0 ? 0 : Math.max(1000, options.warningMs || 28 * 60 * 1000);
            this.refreshLeadMs = Math.max(1000, options.refreshLeadMs || 15 * 60 * 1000);
            this.requestTimeoutMs = Math.max(1000, options.requestTimeoutMs || 20000);
            this.storage = options.storage || global.localStorage;
            this.toast = typeof options.toast === 'function' ? options.toast : null;
            this.onExpire = typeof options.onExpire === 'function' ? options.onExpire : null;

            this._initPromise = null;
            this._refreshPromise = null;
            this._refreshTimer = null;
            this._idleTimer = null;
            this._warned = false;
            this._lastActivity = Date.now();
            this._lastActivityWrite = 0;
            this._storageBound = false;
            this._activityBound = false;
        }

        init() {
            if (this._initPromise) return this._initPromise;

            this._bindStorage();
            this._bindActivity();
            this._initPromise = this._bootstrap();

            return this._initPromise;
        }

        ready() {
            return this.init();
        }

        async _bootstrap() {
            const token = this.getToken();

            if (!token) {
                this._clearTimers();
                return false;
            }

            if (!this.isTokenExpired(token)) {
                this._startTimers();
                return true;
            }

            try {
                await this.refresh({ silent: true });
                this._startTimers();
                return this.isAuthenticated();
            } catch (error) {
                this._clearTimers();
                this._removeKeys();
                return false;
            }
        }

        getToken() {
            return this.storage.getItem(this.tokenKey);
        }

        setToken(token) {
            if (!token) {
                this._removeKeys();
                return;
            }

            this.storage.setItem(this.tokenKey, token);
            this._initPromise = Promise.resolve(true);
            this._warned = false;
            this.touch();
            this._startTimers();
        }

        setUser(user) {
            if (user === null || user === undefined) {
                this.storage.removeItem(this.userKey);
                return;
            }

            this.storage.setItem(this.userKey, JSON.stringify(user));
        }

        getUser() {
            try {
                const raw = this.storage.getItem(this.userKey);
                return raw ? JSON.parse(raw) : null;
            } catch (error) {
                return null;
            }
        }

        setSession(token, user) {
            if (user !== undefined) {
                this.setUser(user);
            }

            this.setToken(token);
            this._dispatch('session:refreshed', { token });
        }

        isAuthenticated() {
            const token = this.getToken();
            return !!token && !this.isTokenExpired(token);
        }

        isTokenExpired(token) {
            const payload = decodeJwt(token);
            if (!payload || typeof payload.exp === 'undefined' || payload.exp === null) {
                return false;
            }

            return Number(payload.exp) * 1000 <= Date.now() + 5000;
        }

        getExpiresAt() {
            const payload = decodeJwt(this.getToken());
            return payload && payload.exp ? Number(payload.exp) * 1000 : null;
        }

        touch() {
            const now = Date.now();
            this._lastActivity = now;
            this._lastActivityWrite = now;
            this._warned = false;
        }

        async refresh({ silent = false } = {}) {
            if (this._refreshPromise) {
                return this._refreshPromise;
            }

            const token = this.getToken();

            if (!token) {
                if (!silent) this.expire('expired');
                throw new SessionExpiredError();
            }

            this._refreshPromise = (async () => {
                const controller = new AbortController();
                const timer = global.setTimeout(() => controller.abort(), this.requestTimeoutMs);
                let response;

                try {
                    response = await global.fetch(this._url(this.refreshPath), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`,
                        },
                        signal: controller.signal,
                    });
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        throw new Error('Session refresh timed out. Please try again.');
                    }
                    throw new Error('Could not refresh the session. Check your connection.');
                } finally {
                    global.clearTimeout(timer);
                }

                let data = {};
                try {
                    const text = await response.text();
                    data = text ? JSON.parse(text) : {};
                } catch (error) {
                    data = {};
                }

                if (!response.ok || data.status !== true || !data.token) {
                    if (!silent) this.expire('expired');
                    throw new SessionExpiredError(data.message || 'Session expired. Please log in again.');
                }

                this.setSession(data.token);
                return data.token;
            })();

            try {
                return await this._refreshPromise;
            } finally {
                this._refreshPromise = null;
            }
        }

        async request(endpoint, options = {}) {
            const {
                retryOnUnauthorized = true,
                auth = true,
                timeoutMs,
                ...fetchOptions
            } = options;

            const token = auth ? this.getToken() : null;

            const doFetch = async (bearer) => {
                const headers = {
                    Accept: 'application/json',
                    ...(fetchOptions.headers || {}),
                };

                if (fetchOptions.body && !(fetchOptions.body instanceof global.FormData) && !headers['Content-Type']) {
                    headers['Content-Type'] = 'application/json';
                }

                if (bearer) {
                    headers.Authorization = `Bearer ${bearer}`;
                }

                const controller = new AbortController();
                const timer = global.setTimeout(() => controller.abort(), timeoutMs || this.requestTimeoutMs);

                try {
                    const response = await global.fetch(this._url(endpoint), {
                        ...fetchOptions,
                        headers,
                        signal: controller.signal,
                    });

                    const text = await response.text();
                    let data = {};

                    if (text) {
                        try {
                            data = JSON.parse(text);
                        } catch (error) {
                            data = { message: text };
                        }
                    }

                    return { response, data };
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        throw new Error('Request timed out. Please try again.');
                    }
                    throw new Error('Could not reach the server. Check your connection and try again.');
                } finally {
                    global.clearTimeout(timer);
                }
            };

            let result = await doFetch(token);

            if (result.response.status === 401 && retryOnUnauthorized && token) {
                // Another tab may have rotated the shared token already.
                const currentToken = this.getToken();

                if (currentToken && currentToken !== token) {
                    result = await doFetch(currentToken);
                }

                if (result.response.status === 401) {
                    try {
                        const newToken = await this.refresh();
                        result = await doFetch(newToken);
                    } catch (error) {
                        // refresh() already expires the session on 401; network
                        // errors keep the token so the user can retry.
                        throw error;
                    }
                }
            }

            const { response, data } = result;

            if (response.status === 429) {
                throw new Error(data.message || 'Too many requests. Please wait a moment and try again.');
            }

            if (response.status === 401) {
                if (retryOnUnauthorized) this.expire('expired');
                throw new SessionExpiredError(data.message || 'Session expired. Please log in again.');
            }

            if (!response.ok) {
                throw new Error(data.message || data.reason || 'Something went wrong');
            }

            return data;
        }

        async logout({ remote = true, redirect = true } = {}) {
            const token = this.getToken();

            if (remote && token) {
                try {
                    const controller = new AbortController();
                    const timer = global.setTimeout(() => controller.abort(), 5000);

                    await global.fetch(this._url(this.logoutPath), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`,
                        },
                        signal: controller.signal,
                    }).finally(() => global.clearTimeout(timer));
                } catch (error) {
                    // Best effort; the local session is cleared regardless.
                }
            }

            this._clearTimers();
            this._removeKeys();
            this._initPromise = Promise.resolve(false);

            if (redirect) {
                global.location.href = this.loginUrl;
            }
        }

        expire(reason = 'expired', { silent = false } = {}) {
            this._clearTimers();
            this._removeKeys();
            this._initPromise = Promise.resolve(false);

            if (silent) return;

            const message = reason === 'idle'
                ? 'You were signed out after a period of inactivity.'
                : 'Your session has expired. Please log in again.';

            this._notify(message, 'error');
            this._dispatch('session:expired', { reason });

            if (this.onExpire) {
                this.onExpire(reason);
            }
        }

        _startTimers() {
            this._scheduleRefresh();
            this._startIdle();
        }

        _scheduleRefresh() {
            global.clearTimeout(this._refreshTimer);

            const payload = decodeJwt(this.getToken());
            if (!payload || !payload.exp) return;

            const refreshAt = Number(payload.exp) * 1000 - this.refreshLeadMs;
            const delay = Math.max(1000, refreshAt - Date.now());

            this._refreshTimer = global.setTimeout(
                () => this._refreshIfNeeded(),
                Math.min(delay, MAX_TIMEOUT)
            );
        }

        _refreshIfNeeded() {
            if (!this.getToken()) return;

            if (this._isIdleExpired()) {
                this.expire('idle');
                return;
            }

            const payload = decodeJwt(this.getToken());
            if (!payload || !payload.exp) return;

            if (Number(payload.exp) * 1000 - Date.now() <= this.refreshLeadMs) {
                this.refresh().catch((error) => {
                    if (error instanceof SessionExpiredError || error.name === 'SessionExpiredError') {
                        this.expire('expired');
                        return;
                    }

                    // Network hiccup: try again shortly without dropping the session.
                    global.setTimeout(() => this._refreshIfNeeded(), 60000);
                });
            } else {
                this._scheduleRefresh();
            }
        }

        _startIdle() {
            global.clearInterval(this._idleTimer);

            if (this.idleMs <= 0) return;

            this._idleTimer = global.setInterval(() => this._checkIdle(), 15000);
            this._checkIdle();
        }

        _checkIdle() {
            if (this.idleMs <= 0 || !this.getToken()) return;

            const idleFor = Date.now() - this._lastActivity;

            if (idleFor >= this.idleMs) {
                this.expire('idle');
                return;
            }

            if (idleFor >= this.warningMs && !this._warned) {
                this._warned = true;
                this._notify('Your session will expire soon. Continue using the app to stay signed in.', 'info');
            }
        }

        _isIdleExpired() {
            return this.idleMs > 0 && Date.now() - this._lastActivity >= this.idleMs;
        }

        _bindActivity() {
            if (this._activityBound) return;
            this._activityBound = true;

            const handler = () => {
                const now = Date.now();

                if (now - this._lastActivityWrite >= 1000) {
                    this._lastActivity = now;
                    this._lastActivityWrite = now;
                    this._warned = false;
                }
            };

            ['mousedown', 'keydown', 'touchstart', 'scroll'].forEach((event) => {
                global.addEventListener(event, handler, { passive: true });
            });

            if (global.document) {
                global.document.addEventListener('visibilitychange', () => {
                    if (!global.document.hidden) {
                        this.touch();
                        this._checkIdle();
                        this._refreshIfNeeded();
                    }
                });
            }
        }

        _bindStorage() {
            if (this._storageBound) return;
            this._storageBound = true;

            global.addEventListener('storage', (event) => {
                if (event.key !== this.tokenKey) return;

                if (!event.newValue) {
                    this._clearTimers();
                    this._initPromise = Promise.resolve(false);
                    this._notify('Your session was signed out in another tab.', 'error');
                    this._dispatch('session:expired', { reason: 'external' });
                    if (this.onExpire) this.onExpire('external');
                    return;
                }

                // Another tab logged in or rotated the token.
                this._initPromise = Promise.resolve(true);
                this._startTimers();
            });
        }

        _clearTimers() {
            global.clearTimeout(this._refreshTimer);
            global.clearInterval(this._idleTimer);
            this._refreshTimer = null;
            this._idleTimer = null;
        }

        _removeKeys() {
            this.storage.removeItem(this.tokenKey);
            this.storage.removeItem(this.userKey);
        }

        _notify(message, type) {
            if (this.toast) {
                this.toast(message, type);
                return;
            }
            if (global.console) {
                global.console.warn(message);
            }
        }

        _dispatch(name, detail) {
            try {
                global.dispatchEvent(new CustomEvent(name, { detail }));
            } catch (error) {
                // CustomEvent is unavailable in very old browsers.
            }
        }

        _url(endpoint) {
            if (/^https?:\/\//i.test(endpoint)) return endpoint;

            const path = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
            return `${this.apiBase}${path}`;
        }
    }

    global.SessionManager = SessionManager;
    global.SessionExpiredError = SessionExpiredError;
})(window);
