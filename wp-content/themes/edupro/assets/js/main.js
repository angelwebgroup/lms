/**
 * EduPro LMS Theme — Main JavaScript
 *
 * Comprehensive interactive layer for navigation, live search, AJAX course
 * filtering, pagination, reviews carousel, authentication, checkout gateways
 * (Razorpay, Stripe, PayPal, Bank Transfer), curriculum accordions, course
 * tabs, lesson completion, dashboard rings/tabs, toasts, modals, and real-time
 * form validation.
 *
 * @package EduPro
 * @version 1.0.0
 */

(function (window, document, $) {
    'use strict';

    /* =========================================================================
     * 1. CONFIGURATION & UTILITIES
     * ========================================================================= */

    const CONFIG = {
        ajaxUrl: (window.edupro_vars && window.edupro_vars.ajaxurl) || '/wp-admin/admin-ajax.php',
        nonce: (window.edupro_vars && window.edupro_vars.nonce) || '',
        siteUrl: (window.edupro_vars && window.edupro_vars.site_url) || window.location.origin,
        currency: (window.edupro_vars && window.edupro_vars.currency) || 'INR',
        currencySymbol: (window.edupro_vars && window.edupro_vars.currency_symbol) || '₹',
        scrollOffset: 90,
        announcementKey: 'edupro_announcement_dismissed_v1',
    };

    const EduPro = window.EduPro || {};

    /**
     * Debounce helper.
     */
    function debounce(fn, wait = 300) {
        let timer = null;
        return function (...args) {
            const ctx = this;
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(ctx, args), wait);
        };
    }

    /**
     * Throttle helper for scroll handlers.
     */
    function throttle(fn, limit = 100) {
        let inThrottle = false;
        return function (...args) {
            if (!inThrottle) {
                fn.apply(this, args);
                inThrottle = true;
                setTimeout(() => {
                    inThrottle = false;
                }, limit);
            }
        };
    }

    /**
     * Escape HTML entities to prevent XSS when injecting strings.
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Load external script dynamically once and return a Promise.
     */
    const loadedScripts = {};
    function loadScript(src, globalCheck) {
        if (globalCheck && typeof globalCheck === 'function' && globalCheck()) {
            return Promise.resolve();
        }
        if (loadedScripts[src]) {
            return loadedScripts[src];
        }
        loadedScripts[src] = new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${src}"]`);
            if (existing) {
                if (existing.dataset.loaded === 'true') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)));
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = () => {
                script.dataset.loaded = 'true';
                resolve();
            };
            script.onerror = () => reject(new Error(`Failed to load ${src}`));
            document.head.appendChild(script);
        });
        return loadedScripts[src];
    }

    /**
     * Perform a WordPress AJAX POST request.
     */
    function ajaxPost(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);

        if (!('nonce' in data) && !('_ajax_nonce' in data)) {
            formData.append('nonce', CONFIG.nonce);
        }

        Object.keys(data).forEach((key) => {
            const val = data[key];
            if (val === undefined || val === null) return;
            if (Array.isArray(val)) {
                val.forEach((item) => formData.append(`${key}[]`, item));
            } else {
                formData.append(key, val);
            }
        });

        return fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then((res) => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
        });
    }

    /**
     * Toggle loading spinner state on a button.
     */
    function setButtonLoading(btn, isLoading, loadingText) {
        if (!btn) return;
        if (isLoading) {
            if (!btn.dataset.origHtml) {
                btn.dataset.origHtml = btn.innerHTML;
            }
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            const label = loadingText || btn.dataset.loadingText || 'Processing...';
            btn.innerHTML = `<span class="edupro-spinner" aria-hidden="true"></span> <span>${escapeHtml(label)}</span>`;
        } else {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            if (btn.dataset.origHtml) {
                btn.innerHTML = btn.dataset.origHtml;
            }
        }
    }

    /* =========================================================================
     * 2. TOAST NOTIFICATIONS
     * ========================================================================= */

    const Toast = (() => {
        let container = null;

        function ensureContainer() {
            container = document.getElementById('edupro-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'edupro-toast-container';
                container.className = 'edupro-toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'false');
                document.body.appendChild(container);
            }
            return container;
        }

        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        };

        function show(message, type = 'info', duration = 4500) {
            const wrap = ensureContainer();
            const toast = document.createElement('div');
            const safeType = icons[type] ? type : 'info';

            toast.className = `edupro-toast edupro-toast--${safeType} toast toast-${safeType}`;
            toast.setAttribute('role', safeType === 'error' ? 'alert' : 'status');
            toast.innerHTML = `
                <span class="edupro-toast__icon">${icons[safeType]}</span>
                <div class="edupro-toast__body">${escapeHtml(message)}</div>
                <button type="button" class="edupro-toast__close" aria-label="Close notification">&times;</button>
            `;

            const dismiss = () => {
                if (toast.dataset.dismissed) return;
                toast.dataset.dismissed = '1';
                toast.classList.remove('is-visible');
                toast.classList.add('is-leaving');
                setTimeout(() => toast.remove(), 300);
            };

            toast.querySelector('.edupro-toast__close').addEventListener('click', dismiss);
            wrap.appendChild(toast);

            // Trigger CSS transition on next frame
            requestAnimationFrame(() => toast.classList.add('is-visible'));

            if (duration > 0) {
                let timer = setTimeout(dismiss, duration);
                toast.addEventListener('mouseenter', () => clearTimeout(timer));
                toast.addEventListener('mouseleave', () => {
                    timer = setTimeout(dismiss, 2000);
                });
            }

            return { dismiss };
        }

        return {
            show,
            success: (msg, dur) => show(msg, 'success', dur),
            error: (msg, dur) => show(msg, 'error', dur),
            warning: (msg, dur) => show(msg, 'warning', dur),
            info: (msg, dur) => show(msg, 'info', dur),
        };
    })();

    /* =========================================================================
     * 3. GENERIC MODAL CONTROLLER
     * ========================================================================= */

    const Modal = (() => {
        let activeModal = null;
        let lastFocused = null;

        function getDynamicModal() {
            let modal = document.getElementById('edupro-dynamic-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'edupro-dynamic-modal';
                modal.className = 'edupro-modal modal-overlay';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-hidden', 'true');
                modal.innerHTML = `
                    <div class="edupro-modal__backdrop" data-modal-close></div>
                    <div class="edupro-modal__dialog" role="document">
                        <div class="edupro-modal__header">
                            <h3 class="edupro-modal__title" id="edupro-dynamic-modal-title"></h3>
                            <button type="button" class="edupro-modal__close" data-modal-close aria-label="Close modal">&times;</button>
                        </div>
                        <div class="edupro-modal__body"></div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            return modal;
        }

        function open(target, options = {}) {
            if (activeModal) close();

            let modalEl = null;
            if (typeof target === 'string' && target.startsWith('#')) {
                modalEl = document.querySelector(target);
            } else if (target instanceof HTMLElement) {
                modalEl = target;
            } else {
                modalEl = getDynamicModal();
                const titleEl = modalEl.querySelector('.edupro-modal__title');
                const bodyEl = modalEl.querySelector('.edupro-modal__body');
                if (titleEl) {
                    titleEl.textContent = options.title || '';
                    titleEl.parentElement.style.display = options.title ? '' : 'none';
                }
                if (bodyEl) {
                    bodyEl.innerHTML = typeof target === 'string' ? target : (options.content || '');
                }
            }

            if (!modalEl) return;

            lastFocused = document.activeElement;
            activeModal = modalEl;
            modalEl.classList.add('is-open', 'active');
            modalEl.setAttribute('aria-hidden', 'false');
            document.body.classList.add('edupro-modal-open');

            const focusable = modalEl.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) {
                setTimeout(() => focusable.focus(), 50);
            }

            modalEl.dispatchEvent(new CustomEvent('edupro:modal:open', { bubbles: true }));
        }

        function close() {
            if (!activeModal) return;
            const modalEl = activeModal;

            // Pause any playing video/iframe inside modal
            modalEl.querySelectorAll('video').forEach((v) => v.pause());
            modalEl.querySelectorAll('iframe').forEach((iframe) => {
                if (iframe.dataset.resetOnClose !== 'false') {
                    const src = iframe.src;
                    iframe.src = '';
                    iframe.src = src;
                }
            });

            modalEl.classList.remove('is-open', 'active');
            modalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('edupro-modal-open');
            activeModal = null;

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
            modalEl.dispatchEvent(new CustomEvent('edupro:modal:close', { bubbles: true }));
        }

        function init() {
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-modal-open], [data-modal-target]');
                if (trigger) {
                    e.preventDefault();
                    const selector = trigger.dataset.modalOpen || trigger.dataset.modalTarget;
                    const videoUrl = trigger.dataset.videoUrl;
                    if (videoUrl) {
                        open(buildVideoEmbedHtml(videoUrl), { title: trigger.dataset.modalTitle || 'Course Preview' });
                    } else if (selector) {
                        open(selector.startsWith('#') ? selector : `#${selector}`);
                    }
                    return;
                }

                if (e.target.closest('[data-modal-close]') || (activeModal && e.target === activeModal)) {
                    e.preventDefault();
                    close();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && activeModal) {
                    close();
                }
            });
        }

        return { init, open, close };
    })();

    /* =========================================================================
     * 4. STICKY HEADER & ANNOUNCEMENT BAR
     * ========================================================================= */

    function initStickyHeader() {
        const header = document.querySelector('.site-header, .edupro-header, header[data-sticky]');
        if (!header) return;

        const threshold = parseInt(header.dataset.scrollThreshold, 10) || 40;
        const onScroll = () => {
            if (window.scrollY > threshold) {
                header.classList.add('scrolled', 'is-sticky');
            } else {
                header.classList.remove('scrolled', 'is-sticky');
            }
        };

        onScroll();
        window.addEventListener('scroll', throttle(onScroll, 60), { passive: true });
    }

    function initAnnouncementBar() {
        const bar = document.querySelector('.announcement-bar, .edupro-announcement-bar, #edupro-announcement');
        if (!bar) return;

        const key = bar.dataset.storageKey || CONFIG.announcementKey;
        try {
            if (localStorage.getItem(key) === '1') {
                bar.style.display = 'none';
                bar.classList.add('is-dismissed');
                return;
            }
        } catch (err) {
            // localStorage unavailable (private browsing)
        }

        const closeBtn = bar.querySelector('.announcement-close, .announcement-bar__close, [data-dismiss="announcement"], button');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                bar.style.maxHeight = `${bar.scrollHeight}px`;
                requestAnimationFrame(() => {
                    bar.classList.add('is-dismissing');
                    bar.style.maxHeight = '0px';
                    bar.style.opacity = '0';
                    bar.style.paddingTop = '0px';
                    bar.style.paddingBottom = '0px';
                });
                setTimeout(() => {
                    bar.style.display = 'none';
                    bar.classList.add('is-dismissed');
                }, 320);
                try {
                    localStorage.setItem(key, '1');
                } catch (err) {
                    // ignore
                }
            });
        }
    }

    /* =========================================================================
     * 5. MOBILE HAMBURGER MENU & OFF-CANVAS DRAWER
     * ========================================================================= */

    function initMobileMenu() {
        const toggleBtn = document.querySelector('#edupro-mobile-toggle, .mobile-menu-toggle, .hamburger-btn, .edupro-hamburger, [data-mobile-menu-toggle]');
        const drawer = document.querySelector('#edupro-mobile-drawer, .edupro-mobile-drawer, .mobile-drawer, .mobile-nav-drawer, .mobile-menu-drawer, #mobile-menu');
        if (!toggleBtn || !drawer) return;

        let backdrop = document.querySelector('#edupro-mobile-overlay, .edupro-mobile-overlay, .mobile-menu-backdrop, .edupro-drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'edupro-mobile-overlay mobile-menu-backdrop edupro-drawer-backdrop';
            document.body.appendChild(backdrop);
        }

        const closeBtns = drawer.querySelectorAll('#edupro-mobile-close, .edupro-mobile-drawer__close, .mobile-menu-close, .drawer-close, [data-mobile-menu-close]');

        const openDrawer = () => {
            drawer.classList.add('is-open', 'active');
            backdrop.classList.add('is-open', 'active');
            toggleBtn.classList.add('is-active', 'active');
            toggleBtn.setAttribute('aria-expanded', 'true');
            drawer.setAttribute('aria-hidden', 'false');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.classList.add('mobile-menu-open');
        };

        const closeDrawer = () => {
            drawer.classList.remove('is-open', 'active');
            backdrop.classList.remove('is-open', 'active');
            toggleBtn.classList.remove('is-active', 'active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            drawer.setAttribute('aria-hidden', 'true');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mobile-menu-open');
        };

        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (drawer.classList.contains('is-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });

        backdrop.addEventListener('click', closeDrawer);
        closeBtns.forEach((btn) => btn.addEventListener('click', (e) => {
            e.preventDefault();
            closeDrawer();
        }));

        // Expand/collapse nested submenus inside mobile drawer (starts collapsed)
        drawer.querySelectorAll('.edupro-mobile-nav__list .menu-item-has-children, .menu-item-has-children, .has-submenu').forEach((parent) => {
            if (parent.dataset.mobileSubBound === '1') return;
            const link = parent.querySelector(':scope > a');
            const subMenu = parent.querySelector(':scope > .sub-menu, :scope > ul');
            if (!link || !subMenu) return;

            parent.dataset.mobileSubBound = '1';
            parent.classList.remove('is-open', 'submenu-open');
            subMenu.classList.remove('is-open');

            const subToggle = document.createElement('button');
            subToggle.type = 'button';
            subToggle.className = 'edupro-mobile-submenu-toggle mobile-submenu-toggle';
            subToggle.setAttribute('aria-label', 'Toggle submenu');
            subToggle.setAttribute('aria-expanded', 'false');
            subToggle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            parent.insertBefore(subToggle, subMenu);

            const toggleSubmenu = () => {
                const isOpen = parent.classList.toggle('is-open');
                parent.classList.toggle('submenu-open', isOpen);
                subMenu.classList.toggle('is-open', isOpen);
                subToggle.setAttribute('aria-expanded', String(isOpen));
            };

            subToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSubmenu();
            });

            // Tapping a "#" placeholder parent link toggles the submenu instead of jumping
            link.addEventListener('click', (e) => {
                const href = (link.getAttribute('href') || '').trim();
                if (!href || href === '#' || href.startsWith('javascript:')) {
                    e.preventDefault();
                    toggleSubmenu();
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
                toggleBtn.focus();
            }
        });

        window.addEventListener('resize', debounce(() => {
            if (window.innerWidth > 992 && drawer.classList.contains('is-open')) {
                closeDrawer();
            }
        }, 150));
    }

    /* =========================================================================
     * 6. MEGA MENU WITH KEYBOARD ACCESSIBILITY
     * ========================================================================= */

    function initMegaMenu() {
        const megaSelector = '.has-megamenu, .edupro-megamenu-wrap, .has-mega-menu, .mega-menu-item, [data-mega-menu]';
        const userMenuSelector = '.edupro-user-menu, .user-dropdown, .header-user-menu';
        const megaItems = document.querySelectorAll(megaSelector);

        function closeAll(except) {
            megaItems.forEach((item) => {
                if (item === except) return;
                item.classList.remove('is-open', 'mega-open');
                const trigger = item.querySelector(':scope > a, :scope > button, .edupro-megamenu-trigger, .mega-menu-trigger');
                const panel = item.querySelector('.edupro-megamenu, .mega-menu, .mega-menu-dropdown, .edupro-mega-menu');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                if (panel) panel.setAttribute('aria-hidden', 'true');
            });
        }

        megaItems.forEach((item) => {
            const trigger = item.querySelector(':scope > a, :scope > button, .edupro-megamenu-trigger, .mega-menu-trigger');
            const panel = item.querySelector('.edupro-megamenu, .mega-menu, .mega-menu-dropdown, .edupro-mega-menu');
            if (!trigger || !panel) return;

            trigger.setAttribute('aria-haspopup', 'true');
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'true');

            let closeTimer = null;

            const openMenu = () => {
                clearTimeout(closeTimer);
                closeAll(item);
                item.classList.add('is-open', 'mega-open');
                trigger.setAttribute('aria-expanded', 'true');
                panel.setAttribute('aria-hidden', 'false');
            };

            const closeMenu = (immediate = false) => {
                clearTimeout(closeTimer);
                const doClose = () => {
                    item.classList.remove('is-open', 'mega-open');
                    trigger.setAttribute('aria-expanded', 'false');
                    panel.setAttribute('aria-hidden', 'true');
                };
                if (immediate) {
                    doClose();
                } else {
                    closeTimer = setTimeout(doClose, 160);
                }
            };

            item.addEventListener('mouseenter', openMenu);
            item.addEventListener('mouseleave', () => closeMenu(false));

            trigger.addEventListener('click', (e) => {
                if (trigger.getAttribute('href') === '#' || trigger.tagName === 'BUTTON' || window.innerWidth <= 1024) {
                    e.preventDefault();
                    if (item.classList.contains('is-open')) {
                        closeMenu(true);
                    } else {
                        openMenu();
                    }
                }
            });

            // Keyboard accessibility
            trigger.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    openMenu();
                    const firstLink = panel.querySelector('a, button');
                    if (firstLink) firstLink.focus();
                } else if (e.key === 'Escape') {
                    closeMenu(true);
                    trigger.focus();
                }
            });

            panel.addEventListener('keydown', (e) => {
                const focusables = Array.from(panel.querySelectorAll('a, button, [tabindex="0"]'));
                const idx = focusables.indexOf(document.activeElement);

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeMenu(true);
                    trigger.focus();
                } else if (e.key === 'ArrowDown' && focusables.length) {
                    e.preventDefault();
                    focusables[(idx + 1) % focusables.length].focus();
                } else if (e.key === 'ArrowUp' && focusables.length) {
                    e.preventDefault();
                    focusables[(idx - 1 + focusables.length) % focusables.length].focus();
                }
            });

            item.addEventListener('focusout', (e) => {
                if (!item.contains(e.relatedTarget)) {
                    closeMenu(true);
                }
            });
        });

        // User avatar dropdown in header
        document.querySelectorAll(userMenuSelector).forEach((menu) => {
            const btn = menu.querySelector('.edupro-user-menu__trigger, .user-dropdown-toggle, .user-avatar-btn, button');
            if (!btn) return;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = menu.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', String(isOpen));
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest(megaSelector)) {
                closeAll(null);
            }
            if (!e.target.closest(userMenuSelector)) {
                document.querySelectorAll('.edupro-user-menu.is-open, .user-dropdown.is-open, .header-user-menu.is-open').forEach((m) => {
                    m.classList.remove('is-open');
                    const btn = m.querySelector('.edupro-user-menu__trigger, .user-dropdown-toggle, .user-avatar-btn, button');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            }
        });
    }

    /* =========================================================================
     * 7. SMOOTH SCROLL TO SECTIONS & SCROLL-TO-TOP BUTTON
     * ========================================================================= */

    function initSmoothScroll() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href^="#"]');
            if (!link) return;
            if (link.hasAttribute('data-tab') || link.hasAttribute('data-modal-open')) return;

            const href = link.getAttribute('href');
            if (!href || href === '#' || href.length < 2) return;

            let target = null;
            try {
                target = document.querySelector(href);
            } catch (err) {
                return;
            }
            if (!target) return;

            e.preventDefault();
            const header = document.querySelector('.site-header, .edupro-header');
            const offset = header ? header.offsetHeight + 16 : CONFIG.scrollOffset;
            const top = target.getBoundingClientRect().top + window.pageYOffset - offset;

            window.scrollTo({
                top: Math.max(0, top),
                behavior: 'smooth',
            });

            if (history.pushState) {
                history.pushState(null, '', href);
            }
        });
    }

    function initScrollToTop() {
        let btn = document.querySelector('.scroll-to-top, #edupro-scroll-top, .back-to-top');
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'edupro-scroll-top';
            btn.type = 'button';
            btn.className = 'edupro-scroll-top scroll-to-top';
            btn.setAttribute('aria-label', 'Scroll to top');
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
            document.body.appendChild(btn);
        }

        const onScroll = () => {
            if (window.scrollY > 300) {
                btn.classList.add('is-visible', 'visible');
            } else {
                btn.classList.remove('is-visible', 'visible');
            }
        };

        onScroll();
        window.addEventListener('scroll', throttle(onScroll, 80), { passive: true });

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* =========================================================================
     * 8. INTERSECTION OBSERVERS: ANIMATED COUNTERS & .animate-on-scroll
     * ========================================================================= */

    function initAnimatedCounters() {
        const counters = document.querySelectorAll('.edupro-counter, .counter-number, .stat-number, [data-count], [data-counter]');
        if (!counters.length) return;

        function animateCounter(el) {
            if (el.dataset.counted === 'true') return;
            el.dataset.counted = 'true';

            const rawTarget = el.dataset.count || el.dataset.counter || el.dataset.target || el.textContent;
            const cleaned = String(rawTarget).replace(/,/g, '');
            const match = cleaned.match(/([0-9]+(?:\.[0-9]+)?)/);
            if (!match) return;

            const targetVal = parseFloat(match[1]);
            const decimals = el.dataset.decimals !== undefined
                ? parseInt(el.dataset.decimals, 10) || 0
                : (match[1].split('.')[1] || '').length;
            const prefix = el.dataset.prefix || cleaned.slice(0, match.index);
            const suffix = el.dataset.suffix || cleaned.slice(match.index + match[1].length);
            const duration = parseInt(el.dataset.duration, 10) || 1800;
            const startTime = performance.now();

            function formatNum(val) {
                if (decimals > 0) {
                    return val.toFixed(decimals);
                }
                return Math.round(val).toLocaleString('en-IN');
            }

            function step(now) {
                const progress = Math.min((now - startTime) / duration, 1);
                // Ease-out cubic
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = targetVal * eased;
                el.textContent = `${prefix}${formatNum(current)}${suffix}`;

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = `${prefix}${formatNum(targetVal)}${suffix}`;
                }
            }

            requestAnimationFrame(step);
        }

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            animateCounter(entry.target);
                            observer.unobserve(entry.target);
                        }
                    });
                },
                { threshold: 0.25 }
            );
            counters.forEach((el) => observer.observe(el));
        } else {
            counters.forEach(animateCounter);
        }
    }

    function initAnimateOnScroll() {
        const elements = document.querySelectorAll('.animate-on-scroll');
        if (!elements.length) return;

        const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced || !('IntersectionObserver' in window)) {
            elements.forEach((el) => el.classList.add('fadeInUp', 'is-visible', 'animated'));
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        const delay = el.dataset.delay;
                        if (delay) {
                            el.style.animationDelay = `${delay}ms`;
                            el.style.transitionDelay = `${delay}ms`;
                        }
                        const animClass = el.dataset.animation || 'fadeInUp';
                        el.classList.add(animClass, 'is-visible', 'animated');
                        observer.unobserve(el);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        elements.forEach((el) => observer.observe(el));
    }

    /* =========================================================================
     * 9. LIVE SEARCH (#edupro-search)
     * ========================================================================= */

    function initLiveSearch() {
        const inputs = document.querySelectorAll('#edupro-search, .edupro-live-search-input, [data-live-search]');
        if (!inputs.length) return;

        inputs.forEach((input) => {
            const container = input.closest('.search-bar, .header-search, .hero-search, .edupro-search-wrap, form') || input.parentElement;
            if (!container) return;

            container.style.position = container.style.position || 'relative';

            let dropdown = container.querySelector('.live-search-results, .search-results-dropdown, .edupro-search-dropdown');
            if (!dropdown) {
                dropdown = document.createElement('div');
                dropdown.className = 'live-search-results edupro-search-dropdown';
                dropdown.setAttribute('role', 'listbox');
                dropdown.setAttribute('aria-label', 'Course search results');
                dropdown.hidden = true;
                container.appendChild(dropdown);
            }

            let activeIdx = -1;
            let abortController = null;

            const closeDropdown = () => {
                dropdown.hidden = true;
                dropdown.classList.remove('is-open', 'active');
                activeIdx = -1;
            };

            const renderResults = (items, query) => {
                if (!items || !items.length) {
                    dropdown.innerHTML = `
                        <div class="search-empty-state">
                            <p>No courses found for "<strong>${escapeHtml(query)}</strong>"</p>
                        </div>
                    `;
                } else {
                    const listHtml = items
                        .map((item, idx) => {
                            const thumb = item.thumbnail
                                ? `<img src="${escapeHtml(item.thumbnail)}" alt="${escapeHtml(item.title)}" class="search-result-thumb" loading="lazy">`
                                : '<div class="search-result-thumb search-result-thumb--placeholder"></div>';
                            const price = item.price_html || (item.is_free ? 'Free' : (item.price ? `${CONFIG.currencySymbol}${escapeHtml(item.price)}` : ''));
                            const category = item.category ? `<span class="search-result-cat">${escapeHtml(item.category)}</span>` : '';
                            const rating = item.rating ? `<span class="search-result-rating">★ ${escapeHtml(item.rating)}</span>` : '';
                            return `
                                <a href="${escapeHtml(item.url || item.permalink || '#')}" class="search-result-item" role="option" data-index="${idx}">
                                    ${thumb}
                                    <div class="search-result-info">
                                        <div class="search-result-title">${escapeHtml(item.title)}</div>
                                        <div class="search-result-meta">${category}${rating}</div>
                                    </div>
                                    <div class="search-result-price">${price}</div>
                                </a>
                            `;
                        })
                        .join('');

                    const viewAllUrl = `${CONFIG.siteUrl}/courses/?s=${encodeURIComponent(query)}`;
                    dropdown.innerHTML = `
                        <div class="search-results-list">${listHtml}</div>
                        <a href="${escapeHtml(viewAllUrl)}" class="search-view-all">View all results for "${escapeHtml(query)}" &rarr;</a>
                    `;
                }
                dropdown.hidden = false;
                dropdown.classList.add('is-open', 'active');
                activeIdx = -1;
            };

            const performSearch = debounce((query) => {
                const trimmed = query.trim();
                if (trimmed.length < 2) {
                    closeDropdown();
                    return;
                }

                if (abortController) abortController.abort();
                abortController = new AbortController();

                dropdown.hidden = false;
                dropdown.classList.add('is-open', 'active');
                dropdown.innerHTML = '<div class="search-loading"><span class="edupro-spinner"></span> Searching courses...</div>';

                const formData = new FormData();
                formData.append('action', 'edupro_live_search');
                formData.append('nonce', CONFIG.nonce);
                formData.append('query', trimmed);
                formData.append('search', trimmed);
                formData.append('s', trimmed);

                fetch(CONFIG.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                    signal: abortController.signal,
                })
                    .then((res) => res.json())
                    .then((res) => {
                        if (res && res.success) {
                            const results = Array.isArray(res.data) ? res.data : (res.data && (res.data.courses || res.data.results)) || [];
                            renderResults(results, trimmed);
                        } else {
                            renderResults([], trimmed);
                        }
                    })
                    .catch((err) => {
                        if (err.name !== 'AbortError') {
                            closeDropdown();
                        }
                    });
            }, 300);

            input.addEventListener('input', (e) => performSearch(e.target.value));

            input.addEventListener('focus', () => {
                if (input.value.trim().length >= 2 && dropdown.innerHTML.trim()) {
                    dropdown.hidden = false;
                    dropdown.classList.add('is-open', 'active');
                }
            });

            input.addEventListener('keydown', (e) => {
                if (dropdown.hidden) return;
                const items = dropdown.querySelectorAll('.search-result-item');
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = (activeIdx + 1) % items.length;
                    items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = (activeIdx - 1 + items.length) % items.length;
                    items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
                } else if (e.key === 'Enter' && activeIdx >= 0 && items[activeIdx]) {
                    e.preventDefault();
                    window.location.href = items[activeIdx].getAttribute('href');
                } else if (e.key === 'Escape') {
                    closeDropdown();
                }
            });

            document.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    closeDropdown();
                }
            });
        });
    }

    /* =========================================================================
     * 10. COURSE ARCHIVE FILTER FORM & LOAD MORE COURSES
     * ========================================================================= */

    function initCourseFilters() {
        const filterForm = document.querySelector('#edupro-course-filter-form, .course-filter-form, form[data-course-filter]');
        const gridSelector = '#edupro-courses-grid, .course-grid.edupro-course-grid, .edupro-course-grid, .course-grid, .courses-grid, .course-archive-grid';
        const courseGrid = document.querySelector(gridSelector);
        const sortSelect = document.querySelector('#edupro-sort-select, .edupro-sort-select, #edupro-course-sort, .course-sort-select, select[name="orderby"]');
        const countEl = document.querySelector('.edupro-catalog-count, .courses-count, .archive-result-count, [data-courses-count]');

        if (!filterForm || !courseGrid) return;

        let currentRequest = null;

        function collectParams() {
            const params = new URLSearchParams();
            const fd = new FormData(filterForm);
            for (const [key, val] of fd.entries()) {
                if (val !== '' && val !== null) {
                    params.append(key, val);
                }
            }
            if (sortSelect && sortSelect.value && !params.has(sortSelect.name || 'orderby')) {
                params.set(sortSelect.name || 'orderby', sortSelect.value);
            }
            return params;
        }

        function fetchFilteredCourses(pushHistory = true) {
            const params = collectParams();
            const queryString = params.toString();
            const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
            const targetUrl = queryString ? `${baseUrl}?${queryString}` : baseUrl;

            if (pushHistory && window.history && window.history.pushState) {
                window.history.pushState({ eduproFilter: queryString }, '', targetUrl);
            }

            if (currentRequest) currentRequest.abort();
            currentRequest = new AbortController();

            courseGrid.classList.add('is-loading');
            courseGrid.setAttribute('aria-busy', 'true');

            // First try AJAX endpoint edupro_load_more_courses (page 1), fallback to full archive HTML fetch
            const ajaxData = new FormData();
            ajaxData.append('action', 'edupro_load_more_courses');
            ajaxData.append('nonce', CONFIG.nonce);
            ajaxData.append('page', '1');
            ajaxData.append('paged', '1');
            ajaxData.append('replace', '1');
            for (const [key, val] of params.entries()) {
                ajaxData.append(key, val);
            }

            fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: ajaxData,
                signal: currentRequest.signal,
            })
                .then((res) => res.json())
                .then((res) => {
                    if (res && res.success && res.data && typeof res.data.html === 'string') {
                        courseGrid.innerHTML = res.data.html || '<div class="edupro-empty-state"><p>No courses match your selected filters.</p></div>';
                        if (countEl && res.data.total !== undefined) {
                            countEl.textContent = `${res.data.total} course${res.data.total === 1 ? '' : 's'} found`;
                        }
                        const loadMoreBtn = document.querySelector('#edupro-load-more, .load-more-courses-btn, [data-load-more-courses]');
                        if (loadMoreBtn) {
                            loadMoreBtn.dataset.page = '1';
                            if (res.data.max_pages !== undefined) {
                                loadMoreBtn.dataset.maxPages = String(res.data.max_pages);
                            }
                            const hasMore = res.data.has_more !== undefined ? res.data.has_more : (res.data.max_pages > 1);
                            loadMoreBtn.style.display = hasMore ? '' : 'none';
                        }
                        initAnimateOnScroll();
                        return;
                    }
                    // Fallback: fetch filtered page and swap grid HTML
                    return fetch(targetUrl, { signal: currentRequest.signal })
                        .then((r) => r.text())
                        .then((html) => {
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const newGrid = doc.querySelector(gridSelector);
                            if (newGrid) {
                                courseGrid.innerHTML = newGrid.innerHTML;
                            }
                            const newCount = doc.querySelector('.edupro-catalog-count, .courses-count, .archive-result-count, [data-courses-count]');
                            if (countEl && newCount) {
                                countEl.innerHTML = newCount.innerHTML;
                            }
                        });
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') {
                        Toast.error('Could not load filtered courses. Please try again.');
                    }
                })
                .finally(() => {
                    courseGrid.classList.remove('is-loading');
                    courseGrid.removeAttribute('aria-busy');
                });
        }

        const debouncedFetch = debounce(() => fetchFilteredCourses(true), 250);

        filterForm.addEventListener('change', (e) => {
            if (e.target.matches('input, select')) {
                debouncedFetch();
            }
        });

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchFilteredCourses(true);
        });

        // Reset filters button
        const resetBtn = filterForm.querySelector('.filter-reset-btn, [type="reset"], [data-reset-filters]');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                setTimeout(() => fetchFilteredCourses(true), 20);
            });
        }

        if (sortSelect && !filterForm.contains(sortSelect)) {
            sortSelect.removeAttribute('onchange');
            sortSelect.addEventListener('change', () => {
                const hiddenOrderby = filterForm.querySelector('#edupro-filter-orderby-hidden, input[name="orderby"]');
                if (hiddenOrderby) hiddenOrderby.value = sortSelect.value;
                fetchFilteredCourses(true);
            });
        }

        // Sync on browser back/forward
        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            filterForm.querySelectorAll('input, select').forEach((field) => {
                if (!field.name) return;
                const cleanName = field.name.replace(/\[\]$/, '');
                const vals = urlParams.getAll(field.name).concat(urlParams.getAll(cleanName));
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = vals.includes(field.value);
                } else {
                    field.value = vals[0] || '';
                }
            });
            fetchFilteredCourses(false);
        });
    }

    function initLoadMoreCourses() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#edupro-load-more, .load-more-courses-btn, [data-load-more-courses]');
            if (!btn) return;

            e.preventDefault();
            const grid = document.querySelector(btn.dataset.target || '#edupro-courses-grid, .course-grid.edupro-course-grid, .edupro-course-grid, .course-grid, .courses-grid, .course-archive-grid');
            if (!grid) return;

            const currentPage = parseInt(btn.dataset.page, 10) || 1;
            const nextPage = currentPage + 1;
            const maxPages = parseInt(btn.dataset.maxPages, 10) || 0;

            if (maxPages && nextPage > maxPages) {
                btn.style.display = 'none';
                return;
            }

            setButtonLoading(btn, true, 'Loading courses...');

            const payload = {
                page: nextPage,
                paged: nextPage,
                per_page: btn.dataset.perPage || 8,
                category: btn.dataset.category || '',
                level: btn.dataset.level || '',
                orderby: btn.dataset.orderby || '',
                s: btn.dataset.search || '',
            };

            // Include active URL query filters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach((val, key) => {
                if (!(key in payload) || !payload[key]) {
                    payload[key] = val;
                }
            });

            ajaxPost('edupro_load_more_courses', payload)
                .then((res) => {
                    if (res && res.success && res.data) {
                        const html = res.data.html || '';
                        if (html.trim()) {
                            grid.insertAdjacentHTML('beforeend', html);
                            btn.dataset.page = String(nextPage);
                            initAnimateOnScroll();
                        }
                        const serverMax = parseInt(res.data.max_pages, 10) || maxPages;
                        const hasMore = res.data.has_more !== undefined ? res.data.has_more : (serverMax ? nextPage < serverMax : Boolean(html.trim()));
                        if (!hasMore) {
                            btn.style.display = 'none';
                        }
                    } else {
                        Toast.error((res && res.data && res.data.message) || 'No more courses available.');
                        btn.style.display = 'none';
                    }
                })
                .catch(() => {
                    Toast.error('Unable to load more courses right now.');
                })
                .finally(() => {
                    setButtonLoading(btn, false);
                });
        });
    }

    /* =========================================================================
     * 11. GOOGLE REVIEWS CAROUSEL & REVIEW EXPAND
     * ========================================================================= */

    function initReviewCarousel() {
        const carousels = document.querySelectorAll('.edupro-google-reviews--carousel, .reviews-carousel, .edupro-reviews-carousel, [data-review-carousel]');
        if (!carousels.length) return;

        carousels.forEach((carousel) => {
            const track = carousel.querySelector('.edupro-reviews-track[data-edupro-reviews-slider], .edupro-reviews-track, .reviews-carousel__track, .reviews-track, .carousel-track');
            if (!track) return;

            const slides = Array.from(track.children);
            if (slides.length <= 1) return;

            const prevBtn = carousel.querySelector('.carousel-prev, .reviews-prev, [data-carousel-prev]');
            const nextBtn = carousel.querySelector('.carousel-next, .reviews-next, [data-carousel-next]');
            const dotsWrap = carousel.querySelector('.carousel-dots, .reviews-dots');
            const autoplayMs = parseInt(carousel.dataset.autoplay, 10) || 4500;
            const usesNativeScroll = ['auto', 'scroll'].includes(window.getComputedStyle(track).overflowX);

            let currentIndex = 0;
            let timer = null;
            let touchStartX = 0;

            function getVisibleCount() {
                const w = window.innerWidth;
                if (w < 640) return 1;
                if (w < 1024) return 2;
                return parseInt(carousel.dataset.visible, 10) || 3;
            }

            function getMaxIndex() {
                return Math.max(0, slides.length - getVisibleCount());
            }

            function buildDots() {
                if (!dotsWrap) return;
                const totalSteps = getMaxIndex() + 1;
                dotsWrap.innerHTML = '';
                for (let i = 0; i < totalSteps; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = `carousel-dot${i === currentIndex ? ' is-active' : ''}`;
                    dot.setAttribute('aria-label', `Go to review slide ${i + 1}`);
                    dot.addEventListener('click', () => {
                        goTo(i);
                        restartAutoplay();
                    });
                    dotsWrap.appendChild(dot);
                }
            }

            function goTo(index) {
                const maxIdx = getMaxIndex();
                if (index > maxIdx) {
                    currentIndex = 0;
                } else if (index < 0) {
                    currentIndex = maxIdx;
                } else {
                    currentIndex = index;
                }

                const gap = parseFloat(window.getComputedStyle(track).gap) || 24;
                const slideWidth = slides[0].getBoundingClientRect().width;
                const shift = currentIndex * (slideWidth + gap);
                if (usesNativeScroll) {
                    track.scrollTo({ left: shift, behavior: 'smooth' });
                } else {
                    track.style.transform = `translate3d(-${shift}px, 0, 0)`;
                }

                if (dotsWrap) {
                    dotsWrap.querySelectorAll('.carousel-dot').forEach((d, idx) => {
                        d.classList.toggle('is-active', idx === currentIndex);
                    });
                }
            }

            function startAutoplay() {
                stopAutoplay();
                if (autoplayMs > 0) {
                    timer = setInterval(() => goTo(currentIndex + 1), autoplayMs);
                }
            }

            function stopAutoplay() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            function restartAutoplay() {
                stopAutoplay();
                startAutoplay();
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    goTo(currentIndex - 1);
                    restartAutoplay();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    goTo(currentIndex + 1);
                    restartAutoplay();
                });
            }

            // Pause on hover/focus
            carousel.addEventListener('mouseenter', stopAutoplay);
            carousel.addEventListener('mouseleave', startAutoplay);
            carousel.addEventListener('focusin', stopAutoplay);
            carousel.addEventListener('focusout', startAutoplay);

            // Touch swipe support
            track.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                stopAutoplay();
            }, { passive: true });

            track.addEventListener('touchend', (e) => {
                const diff = touchStartX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 45) {
                    goTo(diff > 0 ? currentIndex + 1 : currentIndex - 1);
                }
                startAutoplay();
            }, { passive: true });

            window.addEventListener('resize', debounce(() => {
                buildDots();
                goTo(Math.min(currentIndex, getMaxIndex()));
            }, 150));

            buildDots();
            goTo(0);
            startAutoplay();
        });

        // Truncated review "Read more" expand toggle
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.review-expand-btn, .edupro-review-toggle');
            if (!btn) return;
            e.preventDefault();
            const card = btn.closest('.review-card, .google-review-card, .edupro-review-card');
            if (!card) return;
            const expanded = card.classList.toggle('is-expanded');
            btn.textContent = expanded ? 'Show less' : 'Read more';
        });
    }

    /* =========================================================================
     * 12. REAL-TIME FORM VALIDATION
     * ========================================================================= */

    const Validator = (() => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        function getFieldWrapper(field) {
            return field.closest('.form-group, .edupro-form-group, .field-wrap, .input-group') || field.parentElement;
        }

        function setFieldState(field, isValid, message = '') {
            const wrap = getFieldWrapper(field);
            if (!wrap) return;

            field.classList.toggle('is-invalid', !isValid);
            field.classList.toggle('is-valid', isValid && field.value.trim() !== '');
            field.setAttribute('aria-invalid', String(!isValid));

            let errEl = wrap.querySelector('.field-error, .edupro-field-error');
            if (!isValid && message) {
                if (!errEl) {
                    errEl = document.createElement('span');
                    errEl.className = 'field-error edupro-field-error';
                    errEl.setAttribute('role', 'alert');
                    wrap.appendChild(errEl);
                }
                errEl.textContent = message;
                errEl.style.display = 'block';
            } else if (errEl) {
                errEl.textContent = '';
                errEl.style.display = 'none';
            }
        }

        function validateField(field) {
            if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                return true;
            }

            const value = (field.value || '').trim();
            const label = field.dataset.label || field.getAttribute('placeholder') || field.name || 'This field';

            if (field.type === 'checkbox' && field.required && !field.checked) {
                setFieldState(field, false, field.dataset.requiredMsg || 'Please accept this requirement.');
                return false;
            }

            if (field.required && value === '') {
                setFieldState(field, false, field.dataset.requiredMsg || `${label} is required.`);
                return false;
            }

            if (value !== '') {
                if (field.type === 'email' && !emailRegex.test(value)) {
                    setFieldState(field, false, 'Please enter a valid email address.');
                    return false;
                }

                const minLen = parseInt(field.getAttribute('minlength'), 10);
                if (minLen && value.length < minLen) {
                    setFieldState(field, false, `Must be at least ${minLen} characters.`);
                    return false;
                }

                if (field.dataset.match || field.name === 'confirm_password' || field.name === 'password_confirm') {
                    const form = field.form;
                    const targetSelector = field.dataset.match || 'input[name="password"], input[name="new_password"]';
                    const targetField = form ? form.querySelector(targetSelector) : null;
                    if (targetField && field.value !== targetField.value) {
                        setFieldState(field, false, 'Passwords do not match.');
                        return false;
                    }
                }
            }

            setFieldState(field, true);
            return true;
        }

        function validateForm(form) {
            let valid = true;
            let firstInvalid = null;
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach((field) => {
                if (!validateField(field)) {
                    valid = false;
                    if (!firstInvalid) firstInvalid = field;
                }
            });
            if (firstInvalid) {
                firstInvalid.focus();
            }
            return valid;
        }

        function init() {
            const forms = document.querySelectorAll(
                'form.edupro-validate, form#edupro-login-form, form#edupro-register-form, form#edupro-profile-form, form#edupro-review-form, form[data-validate]'
            );

            forms.forEach((form) => {
                form.setAttribute('novalidate', 'novalidate');

                form.addEventListener('blur', (e) => {
                    if (e.target.matches('input, textarea, select')) {
                        validateField(e.target);
                    }
                }, true);

                form.addEventListener('input', (e) => {
                    if (e.target.matches('input, textarea, select')) {
                        if (e.target.classList.contains('is-invalid') || e.target.name === 'confirm_password') {
                            validateField(e.target);
                        }
                    }
                });

                form.addEventListener('change', (e) => {
                    if (e.target.matches('input[type="checkbox"], select')) {
                        validateField(e.target);
                    }
                });
            });
        }

        return { init, validateField, validateForm, setFieldState };
    })();

    /* =========================================================================
     * 13. AJAX FORMS: LOGIN, REGISTER, FORGOT PASSWORD, PROFILE, REVIEW, ENROLL
     * ========================================================================= */

    function showFormAlert(form, message, type = 'error') {
        let box = form.querySelector('.form-message, .edupro-form-alert, .auth-message');
        if (!box) {
            box = document.createElement('div');
            box.className = 'edupro-form-alert form-message';
            form.prepend(box);
        }
        box.className = `edupro-form-alert form-message alert alert-${type} is-visible`;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');
        box.innerHTML = escapeHtml(message);
    }

    function isInlineBoundForm(form) {
        if (!form) return false;
        if (form.dataset.eduproInlineBound === '1' || form.hasAttribute('data-edupro-inline-bound')) {
            return true;
        }
        // Page templates (template-login.php, template-register.php, template-dashboard.php)
        // attach dedicated inline AJAX submit handlers to these exact form IDs.
        if (form.id === 'edupro-profile-form' && form.closest('.edupro-dashboard-page-wrap')) {
            return true;
        }
        if (form.id === 'edupro-login-form' && form.closest('.edupro-auth-page, .edupro-auth-card, #edupro-login-panel')) {
            return true;
        }
        if (form.id === 'edupro-register-form' && form.closest('.edupro-auth-page, .edupro-auth-card')) {
            return true;
        }
        return false;
    }

    function initAuthForms() {
        // Password visibility toggle buttons (skip those already handled by inline auth templates)
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('.password-toggle, [data-toggle-password]');
            if (!toggle) return;
            const parentForm = toggle.closest('form');
            if (isInlineBoundForm(parentForm)) return;
            e.preventDefault();
            const wrap = toggle.closest('.password-field, .edupro-password-field, .input-group, .form-group') || toggle.parentElement;
            const input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
            if (!input) return;
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isPwd));
            toggle.classList.toggle('is-visible', isPwd);
        });

        // Login form (shortcodes & non-inline-bound forms)
        document.querySelectorAll('.edupro-ajax-login-form, #edupro-login-form, .edupro-login-form, form[data-ajax-action="edupro_login"]').forEach((form) => {
            if (isInlineBoundForm(form) || form.dataset.eduproMainBound === '1') return;
            form.dataset.eduproMainBound = '1';
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!Validator.validateForm(form)) return;

                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                setButtonLoading(submitBtn, true, 'Signing in...');

                const fd = new FormData(form);
                const data = {
                    nonce: fd.get('nonce') || fd.get('edupro_nonce') || CONFIG.nonce,
                    username: fd.get('username') || fd.get('user_login') || fd.get('email') || '',
                    password: fd.get('password') || fd.get('user_pass') || '',
                    remember: form.querySelector('input[name="remember"], input[name="rememberme"]')?.checked ? 'true' : 'false',
                    redirect_to: fd.get('redirect_to') || '',
                };

                ajaxPost('edupro_login', data)
                    .then((res) => {
                        if (res && res.success) {
                            const msg = (res.data && res.data.message) || 'Login successful! Redirecting...';
                            showFormAlert(form, msg, 'success');
                            Toast.success(msg);
                            const redirect = (res.data && (res.data.redirect_url || res.data.redirect)) || `${CONFIG.siteUrl}/dashboard/`;
                            setTimeout(() => {
                                window.location.href = redirect;
                            }, 600);
                        } else {
                            const errMsg = (res && res.data && res.data.message) || 'Invalid credentials. Please try again.';
                            showFormAlert(form, errMsg, 'error');
                            Toast.error(errMsg);
                            setButtonLoading(submitBtn, false);
                        }
                    })
                    .catch(() => {
                        showFormAlert(form, 'Network error occurred. Please try again.', 'error');
                        Toast.error('Network error occurred.');
                        setButtonLoading(submitBtn, false);
                    });
            });
        });

        // Register form (shortcodes & non-inline-bound forms)
        document.querySelectorAll('.edupro-ajax-register-form, #edupro-register-form, .edupro-register-form, form[data-ajax-action="edupro_register"]').forEach((form) => {
            if (isInlineBoundForm(form) || form.dataset.eduproMainBound === '1') return;
            form.dataset.eduproMainBound = '1';
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!Validator.validateForm(form)) return;

                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                setButtonLoading(submitBtn, true, 'Creating account...');

                const fd = new FormData(form);
                const email = fd.get('email') || fd.get('user_email') || '';
                const firstName = fd.get('first_name') || '';
                const lastName = fd.get('last_name') || '';
                let username = fd.get('username') || fd.get('user_login') || '';
                if (!username && email) {
                    username = email.split('@')[0].replace(/[^a-zA-Z0-9_]/g, '_') + '_' + Math.floor(100 + Math.random() * 900);
                }

                const data = {
                    nonce: fd.get('nonce') || fd.get('edupro_nonce') || CONFIG.nonce,
                    first_name: firstName,
                    last_name: lastName,
                    username: username,
                    email: email,
                    password: fd.get('password') || '',
                    confirm_password: fd.get('confirm_password') || fd.get('password_confirm') || fd.get('password') || '',
                    terms: form.querySelector('input[name="terms"]')?.checked ? '1' : '0',
                };

                ajaxPost('edupro_register', data)
                    .then((res) => {
                        if (res && res.success) {
                            const msg = (res.data && res.data.message) || 'Account created! Redirecting to your dashboard...';
                            showFormAlert(form, msg, 'success');
                            Toast.success(msg);
                            const redirect = (res.data && (res.data.redirect_url || res.data.redirect)) || `${CONFIG.siteUrl}/dashboard/`;
                            setTimeout(() => {
                                window.location.href = redirect;
                            }, 800);
                        } else {
                            const errMsg = (res && res.data && res.data.message) || 'Registration failed. Please check your details.';
                            showFormAlert(form, errMsg, 'error');
                            Toast.error(errMsg);
                            setButtonLoading(submitBtn, false);
                        }
                    })
                    .catch(() => {
                        showFormAlert(form, 'Network error occurred. Please try again.', 'error');
                        Toast.error('Network error occurred.');
                        setButtonLoading(submitBtn, false);
                    });
            });
        });

        // Free course quick enroll buttons (course pages & [edupro_enroll_button] shortcode)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.edupro-free-enroll-btn, .edupro-enroll-btn[data-free="1"], .edupro-free-enroll, [data-action="edupro_enroll"]');
            if (!btn) return;

            e.preventDefault();
            const courseId = btn.dataset.courseId;
            if (!courseId) return;

            setButtonLoading(btn, true, 'Enrolling...');
            ajaxPost('edupro_enroll', {
                course_id: courseId,
                nonce: btn.dataset.nonce || CONFIG.nonce,
            })
                .then((res) => {
                    if (res && res.success) {
                        Toast.success((res.data && res.data.message) || 'Successfully enrolled!');
                        const redirect = res.data && (res.data.redirect || res.data.redirect_url || res.data.course_url);
                        if (redirect) {
                            setTimeout(() => {
                                window.location.href = redirect;
                            }, 600);
                        } else {
                            btn.innerHTML = 'Enrolled &check;';
                            btn.classList.add('is-enrolled');
                        }
                    } else {
                        const msg = (res && res.data && res.data.message) || 'Please log in to enroll.';
                        Toast.error(msg);
                        if (res && res.data && res.data.login_url) {
                            setTimeout(() => {
                                window.location.href = res.data.login_url;
                            }, 900);
                        }
                        setButtonLoading(btn, false);
                    }
                })
                .catch(() => {
                    Toast.error('Enrollment request failed.');
                    setButtonLoading(btn, false);
                });
        });

        // Profile update form (skip if inline-bound on template-dashboard.php)
        document.querySelectorAll('#edupro-profile-form, .edupro-profile-form').forEach((form) => {
            if (isInlineBoundForm(form) || form.dataset.eduproMainBound === '1') return;
            form.dataset.eduproMainBound = '1';
            // Avatar file preview
            const avatarInput = form.querySelector('input[type="file"][name="avatar"], #edupro-avatar-input');
            const avatarPreview = form.querySelector('.profile-avatar-preview img, #edupro-avatar-preview');
            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', () => {
                    const file = avatarInput.files && avatarInput.files[0];
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (ev) => {
                            avatarPreview.src = ev.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!Validator.validateForm(form)) return;

                const submitBtn = form.querySelector('button[type="submit"]');
                setButtonLoading(submitBtn, true, 'Saving...');

                const fd = new FormData(form);
                fd.append('action', 'edupro_update_profile');
                if (!fd.has('nonce')) fd.append('nonce', CONFIG.nonce);

                fetch(CONFIG.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                })
                    .then((r) => r.json())
                    .then((res) => {
                        if (res && res.success) {
                            const msg = (res.data && res.data.message) || 'Profile updated successfully!';
                            showFormAlert(form, msg, 'success');
                            Toast.success(msg);
                        } else {
                            const msg = (res && res.data && res.data.message) || 'Could not update profile.';
                            showFormAlert(form, msg, 'error');
                            Toast.error(msg);
                        }
                    })
                    .catch(() => {
                        Toast.error('Failed to save profile.');
                    })
                    .finally(() => {
                        setButtonLoading(submitBtn, false);
                    });
            });
        });

        // Course review submission form
        document.querySelectorAll('#edupro-review-form, .edupro-review-form').forEach((form) => {
            // Interactive star rating selector
            const starInputs = form.querySelectorAll('.star-picker__btn, [data-star-value]');
            const ratingField = form.querySelector('input[name="rating"]');
            starInputs.forEach((star) => {
                star.addEventListener('click', (e) => {
                    e.preventDefault();
                    const val = parseInt(star.dataset.starValue, 10) || 5;
                    if (ratingField) ratingField.value = String(val);
                    starInputs.forEach((s) => {
                        s.classList.toggle('is-active', parseInt(s.dataset.starValue, 10) <= val);
                    });
                });
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!Validator.validateForm(form)) return;

                const submitBtn = form.querySelector('button[type="submit"]');
                setButtonLoading(submitBtn, true, 'Submitting review...');

                const fd = new FormData(form);
                const data = {};
                fd.forEach((v, k) => {
                    data[k] = v;
                });

                ajaxPost('edupro_submit_review', data)
                    .then((res) => {
                        if (res && res.success) {
                            Toast.success((res.data && res.data.message) || 'Thank you for your review!');
                            form.reset();
                            if (res.data && res.data.html) {
                                let list = document.querySelector('.edupro-course-reviews-list, .course-reviews-list, .reviews-list');
                                if (!list) {
                                    const emptyNotice = form.parentElement ? form.parentElement.querySelector('.edupro-empty-inline') : null;
                                    list = document.createElement('div');
                                    list.className = 'edupro-course-reviews-list';
                                    if (emptyNotice) {
                                        emptyNotice.replaceWith(list);
                                    } else {
                                        form.parentElement.insertBefore(list, form);
                                    }
                                }
                                list.insertAdjacentHTML('afterbegin', res.data.html);
                            }
                        } else {
                            Toast.error((res && res.data && res.data.message) || 'Could not submit review.');
                        }
                    })
                    .catch(() => Toast.error('Error submitting review.'))
                    .finally(() => setButtonLoading(submitBtn, false));
            });
        });
    }

    /* =========================================================================
     * 14. VIDEO PLAYER EMBED HELPER (Self-hosted & YouTube/Vimeo)
     * ========================================================================= */

    function parseVideoSource(url) {
        if (!url) return null;
        const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        if (ytMatch) {
            return { type: 'youtube', id: ytMatch[1], embedUrl: `https://www.youtube.com/embed/${ytMatch[1]}?rel=0&modestbranding=1&autoplay=1` };
        }
        const vimeoMatch = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if (vimeoMatch) {
            return { type: 'vimeo', id: vimeoMatch[1], embedUrl: `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1&title=0&byline=0` };
        }
        return { type: 'html5', src: url };
    }

    function buildVideoEmbedHtml(url) {
        const parsed = parseVideoSource(url);
        if (!parsed) return '';
        if (parsed.type === 'youtube' || parsed.type === 'vimeo') {
            return `
                <div class="edupro-video-responsive">
                    <iframe src="${escapeHtml(parsed.embedUrl)}" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                </div>
            `;
        }
        return `
            <div class="edupro-video-responsive edupro-video-selfhosted">
                <video controls autoplay playsinline preload="metadata">
                    <source src="${escapeHtml(parsed.src)}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        `;
    }

    function initInlineVideos() {
        // Wrap standalone YouTube/Vimeo iframes in responsive 16:9 wrapper
        document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="youtu.be"], iframe[src*="vimeo.com"]').forEach((iframe) => {
            if (iframe.closest('.edupro-video-responsive, .video-wrapper, .lesson-video-wrapper, .edupro-player-stage, .edupro-video-container, .ep-player-stage')) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'edupro-video-responsive';
            iframe.parentNode.insertBefore(wrapper, iframe);
            wrapper.appendChild(iframe);
        });

        // Self-hosted video custom play overlay (outside the dedicated course-player)
        document.querySelectorAll('.edupro-video-card, [data-inline-video]').forEach((card) => {
            if (card.closest('.edupro-lesson-player')) return;
            const video = card.querySelector('video');
            if (!video) return;

            let overlay = card.querySelector('.video-play-overlay');
            if (!overlay) {
                overlay = document.createElement('button');
                overlay.type = 'button';
                overlay.className = 'video-play-overlay';
                overlay.setAttribute('aria-label', 'Play video');
                overlay.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg>';
                card.appendChild(overlay);
            }

            overlay.addEventListener('click', () => {
                video.play().catch(() => {});
            });

            video.addEventListener('play', () => card.classList.add('is-playing'));
            video.addEventListener('pause', () => card.classList.remove('is-playing'));
            video.addEventListener('ended', () => card.classList.remove('is-playing'));
        });
    }

    /* =========================================================================
     * 15. COURSE CURRICULUM ACCORDION & FAQ ACCORDION
     * ========================================================================= */

    function initAccordions() {
        const containerSel = '.edupro-curriculum-accordion, .edupro-faq-list, .edupro-requirements-box, .curriculum-accordion, .edupro-curriculum, .edupro-accordion, .faq-accordion';
        const triggerSel = '.edupro-accordion-trigger, .curriculum-section__header, .accordion-header, .section-header, [data-accordion-toggle]';
        const itemSel = '.edupro-accordion-item, .edupro-curriculum-section, .curriculum-section, .accordion-item, .faq-item';
        const bodySel = '.edupro-accordion-content, .edupro-curriculum-section__body, .curriculum-section__body, .accordion-body, .section-lessons, .faq-answer';

        function bindAccordionTrigger(header, container) {
            if (!header || header.dataset.accordionBound === '1') return;
            const section = header.closest(itemSel);
            const body = section ? section.querySelector(bodySel) : header.nextElementSibling;
            if (!section || !body) return;

            header.dataset.accordionBound = '1';

            const isOpenInitially = section.classList.contains('is-open') || section.classList.contains('active');
            header.setAttribute('aria-expanded', String(isOpenInitially));
            if (!isOpenInitially) {
                body.style.maxHeight = '0px';
            } else {
                body.style.maxHeight = 'none';
            }

            header.addEventListener('click', (e) => {
                if (e.target.closest('a')) return;
                e.preventDefault();

                const singleOpen = container && container.dataset.singleOpen === 'true';
                const isOpen = section.classList.contains('is-open') || section.classList.contains('active');

                if (singleOpen && !isOpen) {
                    container.querySelectorAll('.edupro-accordion-item.is-open, .curriculum-section.is-open, .accordion-item.is-open, .faq-item.is-open').forEach((other) => {
                        if (other === section) return;
                        other.classList.remove('is-open', 'active');
                        const otherBody = other.querySelector(bodySel);
                        const otherHeader = other.querySelector(triggerSel);
                        if (otherBody) otherBody.style.maxHeight = '0px';
                        if (otherHeader) otherHeader.setAttribute('aria-expanded', 'false');
                    });
                }

                if (isOpen) {
                    body.style.maxHeight = `${body.scrollHeight}px`;
                    requestAnimationFrame(() => {
                        body.style.maxHeight = '0px';
                        section.classList.remove('is-open', 'active');
                        header.setAttribute('aria-expanded', 'false');
                    });
                } else {
                    section.classList.add('is-open', 'active');
                    header.setAttribute('aria-expanded', 'true');
                    body.style.maxHeight = `${body.scrollHeight}px`;
                    body.addEventListener(
                        'transitionend',
                        function onEnd() {
                            if (section.classList.contains('is-open')) {
                                body.style.maxHeight = 'none';
                            }
                            body.removeEventListener('transitionend', onEnd);
                        }
                    );
                }
            });
        }

        document.querySelectorAll(containerSel).forEach((accordion) => {
            accordion.querySelectorAll(triggerSel).forEach((header) => bindAccordionTrigger(header, accordion));

            // Optional "Expand All / Collapse All" button
            const expandAllBtn = document.querySelector(`[data-expand-all="${accordion.id}"], .curriculum-expand-all`);
            if (expandAllBtn && !expandAllBtn.dataset.bound) {
                expandAllBtn.dataset.bound = '1';
                expandAllBtn.addEventListener('click', () => {
                    const sections = accordion.querySelectorAll(itemSel);
                    const anyClosed = Array.from(sections).some((s) => !s.classList.contains('is-open'));
                    sections.forEach((sec) => {
                        const body = sec.querySelector(bodySel);
                        const hdr = sec.querySelector(triggerSel);
                        if (!body) return;
                        if (anyClosed) {
                            sec.classList.add('is-open', 'active');
                            if (hdr) hdr.setAttribute('aria-expanded', 'true');
                            body.style.maxHeight = 'none';
                        } else {
                            sec.classList.remove('is-open', 'active');
                            if (hdr) hdr.setAttribute('aria-expanded', 'false');
                            body.style.maxHeight = '0px';
                        }
                    });
                    expandAllBtn.textContent = anyClosed ? 'Collapse all sections' : 'Expand all sections';
                });
            }
        });

        // Catch any standalone .edupro-accordion-item triggers outside named containers
        document.querySelectorAll(triggerSel).forEach((header) => {
            bindAccordionTrigger(header, header.closest(containerSel));
        });

        // Collapsible course description Show More / Show Less toggle (.edupro-expand-toggle)
        document.querySelectorAll('.edupro-expand-toggle').forEach((btn) => {
            if (btn.dataset.expandBound === '1') return;
            btn.dataset.expandBound = '1';
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetSel = btn.dataset.target;
                const target = targetSel ? document.querySelector(targetSel) : btn.previousElementSibling;
                if (!target) return;
                const isCollapsed = target.classList.contains('is-collapsed');
                target.classList.toggle('is-collapsed', !isCollapsed);
                target.classList.toggle('is-expanded', isCollapsed);
                btn.setAttribute('aria-expanded', String(isCollapsed));
                const moreText = btn.dataset.moreText || 'Show More ↓';
                const lessText = btn.dataset.lessText || 'Show Less ↑';
                btn.textContent = isCollapsed ? lessText : moreText;
            });
        });
    }

    /* =========================================================================
     * 16. SINGLE COURSE TAB NAVIGATION
     * ========================================================================= */

    function initCourseTabs() {
        const tabContainers = document.querySelectorAll('.edupro-course-tabs, .course-tabs, .edupro-tabs, [data-tabs]');
        if (!tabContainers.length) return;

        tabContainers.forEach((container) => {
            const buttons = Array.from(container.querySelectorAll('.edupro-course-tab, .course-tab-btn, .tab-btn, [role="tab"], [data-tab]'));
            if (!buttons.length) return;

            const scope = container.closest('.edupro-course-main, .single-course-main, .course-content-col, .edupro-tabs-wrapper') || document;

            function activateTab(btn, updateHash = false) {
                const targetId = btn.dataset.tab || btn.getAttribute('aria-controls') || (btn.getAttribute('href') || '').replace(/^#/, '');
                if (!targetId) return;

                buttons.forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.classList.toggle('active', active);
                    b.setAttribute('aria-selected', String(active));
                    b.setAttribute('tabindex', active ? '0' : '-1');
                });

                const panels = scope.querySelectorAll('.edupro-course-panel, .course-tab-panel, .tab-panel, .tab-pane, [role="tabpanel"]');
                panels.forEach((panel) => {
                    const match = panel.id === targetId
                        || panel.dataset.panel === targetId
                        || panel.dataset.tabPanel === targetId
                        || panel.id === `tab-${targetId}`;
                    panel.classList.toggle('is-active', match);
                    panel.classList.toggle('active', match);
                    panel.hidden = !match;
                });

                if (updateHash && history.replaceState) {
                    history.replaceState(null, '', `#${targetId}`);
                }
            }

            buttons.forEach((btn, idx) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    activateTab(btn, container.dataset.syncHash !== 'false');
                });

                btn.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        const next = buttons[(idx + 1) % buttons.length];
                        next.focus();
                        activateTab(next);
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        const prev = buttons[(idx - 1 + buttons.length) % buttons.length];
                        prev.focus();
                        activateTab(prev);
                    }
                });
            });

            // Check hash on load
            const initialHash = window.location.hash.replace('#', '');
            const matchedBtn = initialHash
                ? buttons.find((b) => (b.dataset.tab || b.getAttribute('aria-controls')) === initialHash)
                : null;
            if (matchedBtn) {
                activateTab(matchedBtn, false);
            }
        });
    }

    /* =========================================================================
     * 17. LESSON MARK COMPLETE (AJAX + PROGRESS BAR + AUTO-REDIRECT)
     * ========================================================================= */

    function markLessonComplete(btn, options = {}) {
        const lessonId = (btn && btn.dataset.lessonId) || options.lessonId;
        const courseId = (btn && btn.dataset.courseId) || options.courseId || '';
        const nextUrl = (btn && btn.dataset.nextUrl) || options.nextUrl || '';
        const autoRedirect = options.autoRedirect !== undefined
            ? options.autoRedirect
            : Boolean(btn && (btn.dataset.autoRedirect === '1' || btn.dataset.autoRedirect === 'true' || document.querySelector('#edupro-auto-next:checked')));

        if (!lessonId) return Promise.reject(new Error('Missing lesson ID'));

        if (btn) {
            if (btn.classList.contains('is-completed') && !options.force) {
                return Promise.resolve({ alreadyCompleted: true });
            }
            setButtonLoading(btn, true, 'Saving...');
        }

        return ajaxPost('edupro_mark_lesson_complete', {
            lesson_id: lessonId,
            course_id: courseId,
            nonce: (btn && btn.dataset.nonce) || CONFIG.nonce,
        })
            .then((res) => {
                if (res && res.success) {
                    const progress = res.data && res.data.progress !== undefined ? parseFloat(res.data.progress) : null;

                    if (btn) {
                        btn.disabled = true;
                        btn.classList.remove('is-loading', 'edupro-btn--primary', 'btn-primary');
                        btn.classList.add('edupro-btn--success', 'is-completed', 'completed');
                        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <span>Lesson Completed</span>';
                    }

                    // Update all progress bars on the lesson page
                    if (progress !== null && !Number.isNaN(progress)) {
                        const clamped = Math.min(100, Math.max(0, progress));
                        document.querySelectorAll('#edupro-player-progress-fill, .edupro-player-progress-bar__fill, .lesson-progress-fill, .course-progress-bar__fill, [data-progress-bar]').forEach((bar) => {
                            bar.style.width = `${clamped}%`;
                            const track = bar.closest('[role="progressbar"]');
                            if (track) track.setAttribute('aria-valuenow', String(Math.round(clamped)));
                        });
                        document.querySelectorAll('#edupro-player-progress-pct, .lesson-progress-percent, .course-progress-percent, [data-progress-text]').forEach((txt) => {
                            txt.textContent = `${Math.round(clamped)}%`;
                        });
                        // Update any SVG circular progress rings on page
                        document.querySelectorAll('[data-progress-ring], .edupro-progress-ring-wrap').forEach((ring) => {
                            ring.dataset.progress = String(clamped);
                            renderCircularProgressRing(ring, clamped);
                        });
                    }

                    // Mark active lesson item as completed in curriculum sidebar
                    document.querySelectorAll(`.edupro-player-lesson-item[data-lesson-id="${lessonId}"], .curriculum-lesson[data-lesson-id="${lessonId}"], .lesson-nav-item[data-lesson-id="${lessonId}"], .lesson-item.is-active`).forEach((item) => {
                        item.classList.add('is-completed', 'completed');
                        const statusEl = item.querySelector('.edupro-player-lesson-status');
                        if (statusEl) statusEl.textContent = '✓';
                    });

                    Toast.success((res.data && res.data.message) || 'Lesson marked as complete!');

                    const redirectTarget = (res.data && res.data.next_lesson_url) || nextUrl;
                    if (autoRedirect && redirectTarget) {
                        Toast.info('Moving to next lesson...', 2500);
                        setTimeout(() => {
                            window.location.href = redirectTarget;
                        }, 1800);
                    }

                    document.dispatchEvent(new CustomEvent('edupro:lesson:completed', {
                        detail: { lessonId, courseId, progress, response: res.data },
                    }));
                    return res.data;
                }

                const errMsg = (res && res.data && res.data.message) || 'Could not update lesson progress.';
                Toast.error(errMsg);
                if (btn) setButtonLoading(btn, false);
                return Promise.reject(new Error(errMsg));
            })
            .catch((err) => {
                Toast.error('Network error while marking lesson complete.');
                if (btn) setButtonLoading(btn, false);
                throw err;
            });
    }

    function initLessonMarkComplete() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#edupro-mark-complete-btn, #edupro-mark-complete, .mark-complete-btn, [data-mark-complete]');
            if (!btn) return;
            e.preventDefault();
            markLessonComplete(btn).catch(() => {});
        });
    }

    /* =========================================================================
     * 18A. MULTI-CURRENCY SWITCHER (Header, Mobile Drawer & Checkout Summary)
     * ========================================================================= */

    function initCurrencySwitcher() {
        const switchers = document.querySelectorAll('[data-currency-switch]');
        if (!switchers.length) return;

        function performCurrencySwitch(code, fallbackHref) {
            const targetCode = String(code || '').trim().toUpperCase();
            if (!targetCode) return;

            ajaxPost('edupro_switch_currency', {
                nonce: CONFIG.nonce,
                currency: targetCode,
            })
                .then((res) => {
                    if (res && res.success && res.data) {
                        CONFIG.currency = res.data.currency || targetCode;
                        CONFIG.currencySymbol = res.data.symbol || CONFIG.currencySymbol;
                        Toast.success(res.data.message || `Currency switched to ${CONFIG.currency}.`);

                        // Reload cleanly without stale ?currency= query param fighting cookie
                        try {
                            const nextUrl = new URL(window.location.href);
                            nextUrl.searchParams.delete('currency');
                            setTimeout(() => {
                                window.location.href = nextUrl.toString();
                            }, 280);
                        } catch (err) {
                            setTimeout(() => window.location.reload(), 280);
                        }
                    } else if (fallbackHref) {
                        window.location.href = fallbackHref;
                    } else {
                        Toast.error((res && res.data && res.data.message) || 'Could not switch currency.');
                    }
                })
                .catch(() => {
                    if (fallbackHref) {
                        window.location.href = fallbackHref;
                    } else {
                        Toast.error('Network error switching currency.');
                    }
                });
        }

        switchers.forEach((el) => {
            if (el.dataset.currencyBound === '1') return;
            el.dataset.currencyBound = '1';

            if (el.tagName === 'SELECT') {
                el.addEventListener('change', () => {
                    performCurrencySwitch(el.value, '');
                });
            } else {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (el.classList.contains('is-active')) return;
                    const code = el.dataset.currencySwitch || '';
                    const href = el.getAttribute('href') || '';
                    el.classList.add('is-loading');
                    performCurrencySwitch(code, href);
                });
            }
        });
    }

    /* =========================================================================
     * 18B. CHECKOUT COUPON CODE (Apply / Remove)
     * ========================================================================= */

    function initCheckoutCoupons() {
        const checkoutWrap = document.querySelector('#edupro-checkout-app, #edupro-checkout, .edupro-checkout');
        const couponForm = document.querySelector('#edupro-coupon-form, .edupro-coupon-form');
        if (!checkoutWrap || !couponForm) return;

        const inputEl = couponForm.querySelector('#edupro-coupon-input, input[name="coupon_code"]');
        const applyBtn = couponForm.querySelector('#edupro-coupon-apply, button[type="submit"]');
        const appliedBox = checkoutWrap.querySelector('#edupro-coupon-applied, .edupro-coupon-applied');
        const appliedLabel = checkoutWrap.querySelector('#edupro-coupon-applied-label');
        const removeBtn = checkoutWrap.querySelector('#edupro-coupon-remove, .edupro-coupon-remove');
        const msgBox = checkoutWrap.querySelector('#edupro-coupon-message, .edupro-coupon-message');

        let activeCouponInput = checkoutWrap.querySelector('#edupro-active-coupon');
        if (!activeCouponInput) {
            activeCouponInput = document.createElement('input');
            activeCouponInput.type = 'hidden';
            activeCouponInput.id = 'edupro-active-coupon';
            activeCouponInput.name = 'active_coupon';
            couponForm.appendChild(activeCouponInput);
        }

        const couponRow = checkoutWrap.querySelector('#edupro-summary-coupon-row, .edupro-summary-discount-row');
        const couponCodeEl = checkoutWrap.querySelector('#edupro-summary-coupon-code');
        const couponAmtEl = checkoutWrap.querySelector('#edupro-summary-coupon-amount');
        const taxAmtEl = checkoutWrap.querySelector('#edupro-summary-tax-amount');
        const totalEl = checkoutWrap.querySelector('#edupro-summary-total, .edupro-total-price');

        // Capture initial formatted values so removing a coupon restores cleanly
        const origTaxText = taxAmtEl ? taxAmtEl.textContent : '';
        const origTotalText = totalEl ? totalEl.textContent : '';
        const origTotalNumeric = checkoutWrap.dataset.total || '';

        function showCouponMsg(text, type) {
            if (!msgBox) return;
            if (!text) {
                msgBox.style.display = 'none';
                msgBox.textContent = '';
                return;
            }
            msgBox.className = `edupro-coupon-message edupro-coupon-message--${type || 'info'}`;
            msgBox.textContent = text;
            msgBox.style.display = 'block';
        }

        function updatePayButtonAmounts(formattedTotal) {
            if (!formattedTotal) return;
            checkoutWrap.querySelectorAll('.edupro-pay-btn-amount').forEach((span) => {
                span.textContent = formattedTotal;
            });
        }

        function dispatchCouponChanged(code, finalTotal) {
            checkoutWrap.dispatchEvent(
                new CustomEvent('edupro:coupon:changed', {
                    bubbles: true,
                    detail: { code, finalTotal },
                })
            );
        }

        couponForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!inputEl) return;

            const code = inputEl.value.trim().toUpperCase();
            if (!code) {
                showCouponMsg('Please enter a coupon code.', 'error');
                inputEl.focus();
                return;
            }

            const courseId = checkoutWrap.dataset.courseId || '';
            const currency = checkoutWrap.dataset.currency || CONFIG.currency;
            const nonce = checkoutWrap.dataset.paymentNonce || checkoutWrap.dataset.authNonce || CONFIG.nonce;

            showCouponMsg('', 'info');
            setButtonLoading(applyBtn, true, 'Applying...');

            ajaxPost('edupro_apply_coupon', {
                nonce,
                coupon_code: code,
                course_id: courseId,
                currency,
            })
                .then((res) => {
                    if (res && res.success && res.data) {
                        const d = res.data;
                        const appliedCode = d.code || code;
                        const sym = d.symbol || checkoutWrap.dataset.currencySymbol || CONFIG.currencySymbol;
                        const discountFmt = d.discount_formatted || `-${sym}${Number(d.discount_amount || 0).toFixed(2)}`;
                        const finalFmt = d.final_total_formatted || `${sym}${Number(d.final_total || 0).toFixed(2)}`;

                        activeCouponInput.value = appliedCode;
                        checkoutWrap.dataset.couponCode = appliedCode;
                        if (d.final_total !== undefined) {
                            checkoutWrap.dataset.total = String(d.final_total);
                        }

                        if (appliedBox) appliedBox.style.display = 'flex';
                        if (appliedLabel) {
                            appliedLabel.textContent = `${appliedCode} (${discountFmt})`;
                        }
                        couponForm.style.display = 'none';

                        if (couponRow) couponRow.style.display = 'flex';
                        if (couponCodeEl) couponCodeEl.textContent = appliedCode;
                        if (couponAmtEl) couponAmtEl.textContent = discountFmt;
                        if (taxAmtEl && d.tax_formatted) taxAmtEl.textContent = d.tax_formatted;
                        if (totalEl) totalEl.textContent = Number(d.final_total) <= 0 ? 'Free' : finalFmt;

                        updatePayButtonAmounts(Number(d.final_total) <= 0 ? 'Free' : finalFmt);

                        const successMsg = d.message || `Coupon ${appliedCode} applied!`;
                        showCouponMsg(successMsg, 'success');
                        Toast.success(successMsg);
                        dispatchCouponChanged(appliedCode, d.final_total);
                    } else {
                        const errMsg = (res && res.data && res.data.message) || 'Invalid or expired coupon code.';
                        showCouponMsg(errMsg, 'error');
                        Toast.error(errMsg);
                    }
                })
                .catch(() => {
                    showCouponMsg('Could not validate coupon. Please try again.', 'error');
                    Toast.error('Could not validate coupon.');
                })
                .finally(() => {
                    setButtonLoading(applyBtn, false);
                });
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                activeCouponInput.value = '';
                delete checkoutWrap.dataset.couponCode;
                checkoutWrap.dataset.total = origTotalNumeric;
                if (inputEl) inputEl.value = '';
                if (appliedBox) appliedBox.style.display = 'none';
                couponForm.style.display = '';
                if (couponRow) couponRow.style.display = 'none';
                if (taxAmtEl) taxAmtEl.textContent = origTaxText;
                if (totalEl) totalEl.textContent = origTotalText;
                updatePayButtonAmounts(origTotalText);
                showCouponMsg('', 'info');
                Toast.info('Coupon removed.');
                dispatchCouponChanged('', parseFloat(origTotalNumeric) || 0);
            });
        }
    }

    /* =========================================================================
     * 18C. MODULAR 5-GATEWAY CHECKOUT CONTROLLER
     *      Cashfree (Primary India), Razorpay (Backup India),
     *      Stripe Payment Element (UK & Global), PayPal Orders v2, Bank Transfer
     * ========================================================================= */

    function initCheckout() {
        const checkoutWrap = document.querySelector('#edupro-checkout-app, #edupro-checkout, .edupro-checkout, .checkout-container, [data-edupro-checkout]');
        if (!checkoutWrap) return;

        const courseId = checkoutWrap.dataset.courseId || checkoutWrap.querySelector('input[name="course_id"]')?.value || '';
        const currency = checkoutWrap.dataset.currency || CONFIG.currency;
        const dashboardUrl = checkoutWrap.dataset.dashboardUrl || `${CONFIG.siteUrl}/dashboard/`;
        const checkoutUrl = checkoutWrap.dataset.checkoutUrl || window.location.href;
        const paymentNonce = checkoutWrap.dataset.paymentNonce
            || checkoutWrap.dataset.nonce
            || checkoutWrap.querySelector('input[name="payment_nonce"], input[name="nonce"]')?.value
            || CONFIG.nonce;

        const alertBox = checkoutWrap.querySelector('#edupro-checkout-alert');
        const verifyBanner = checkoutWrap.querySelector('#edupro-verify-banner, .edupro-verify-banner');
        const verifyTitle = checkoutWrap.querySelector('#edupro-verify-title');
        const verifyMessage = checkoutWrap.querySelector('#edupro-verify-message');

        const gatewayTabs = Array.from(checkoutWrap.querySelectorAll('.edupro-gateway-tab, .gateway-tab, [data-gateway-tab], input[name="payment_gateway"]'));
        const gatewayPanels = Array.from(checkoutWrap.querySelectorAll('.edupro-gateway-panel, .gateway-panel, [data-gateway-panel]'));
        const sharedPayBtn = checkoutWrap.querySelector('#edupro-pay-btn, .checkout-pay-btn, [data-checkout-submit]');

        let activeGateway = '';
        let isProcessing = false;

        // Stripe Payment Element state (re-initialized if coupon changes)
        let stripeInstance = null;
        let stripeElements = null;
        let stripePaymentElement = null;
        let stripeSession = null; // { orderId, clientSecret, returnUrl, paymentIntentId, couponCode }
        let stripeMountPromise = null;

        function getActiveCoupon() {
            const hidden = checkoutWrap.querySelector('#edupro-active-coupon');
            return (hidden && hidden.value.trim()) || checkoutWrap.dataset.couponCode || '';
        }

        function showCheckoutAlert(message, type = 'error') {
            if (!alertBox) {
                if (type === 'error') Toast.error(message);
                else Toast.info(message);
                return;
            }
            if (!message) {
                alertBox.style.display = 'none';
                alertBox.textContent = '';
                return;
            }
            alertBox.className = `edupro-alert edupro-alert-${type}`;
            alertBox.innerHTML = escapeHtml(message);
            alertBox.style.display = 'block';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function updateVerifyBanner(state, title, message, invoiceUrl) {
            if (!verifyBanner) return;
            verifyBanner.className = `edupro-verify-banner edupro-verify-banner--${state}`;
            const spinner = verifyBanner.querySelector('.edupro-verify-spinner');
            if (spinner) spinner.style.display = state === 'loading' ? '' : 'none';
            if (verifyTitle) verifyTitle.textContent = title;
            if (verifyMessage) verifyMessage.textContent = message;

            if (state === 'success') {
                let actions = verifyBanner.querySelector('.edupro-verify-banner__actions');
                if (!actions) {
                    actions = document.createElement('div');
                    actions.className = 'edupro-verify-banner__actions';
                    verifyBanner.appendChild(actions);
                }
                actions.innerHTML = `
                    <a href="${escapeHtml(dashboardUrl)}" class="edupro-btn edupro-btn-primary edupro-btn-sm">Go to Student Dashboard &rarr;</a>
                    ${invoiceUrl ? `<a href="${escapeHtml(invoiceUrl)}" target="_blank" rel="noopener noreferrer" class="edupro-btn edupro-btn-outline edupro-btn-sm">View Invoice</a>` : ''}
                `;
            }
        }

        function isServerVerified(res) {
            if (!res || !res.success || !res.data) return false;
            const d = res.data;
            return d.verified === true || d.status === 'completed' || d.enrolled === true;
        }

        /* ---------------------------------------------------------------------
         * Auto-Verify on Gateway Return (Cashfree / PayPal v2 / Stripe)
         * ------------------------------------------------------------------- */

        function runReturnVerification() {
            // Skip redundant AJAX call when server already verified & rendered success banner
            if (verifyBanner && verifyBanner.classList.contains('edupro-verify-banner--success')) {
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            let returnGateway = checkoutWrap.dataset.returnGateway || '';
            let returnOrderId = checkoutWrap.dataset.returnOrderId || urlParams.get('order_id') || '';
            const returnToken = checkoutWrap.dataset.returnToken || urlParams.get('token') || '';
            const returnPi = checkoutWrap.dataset.returnPi || urlParams.get('payment_intent') || '';

            if (!returnGateway) {
                if (urlParams.get('edupro_cashfree_return') === '1' && returnOrderId) returnGateway = 'cashfree';
                else if (urlParams.get('edupro_paypal_return') === '1' && returnOrderId) returnGateway = 'paypal';
                else if (urlParams.get('edupro_stripe_return') === '1' && returnOrderId) returnGateway = 'stripe';
            }

            if (!returnGateway || !returnOrderId) return;

            let action = '';
            const payload = {
                nonce: paymentNonce,
                order_id: returnOrderId,
                course_id: courseId,
            };

            if (returnGateway === 'cashfree') {
                action = 'edupro_cashfree_verify';
            } else if (returnGateway === 'paypal') {
                action = 'edupro_paypal_capture';
                payload.paypal_order_id = returnToken;
            } else if (returnGateway === 'stripe') {
                action = 'edupro_stripe_confirm';
                payload.payment_intent_id = returnPi;
            } else {
                return;
            }

            ajaxPost(action, payload)
                .then((res) => {
                    if (isServerVerified(res)) {
                        const d = res.data;
                        const msg = d.message || 'Payment verified! Your course is now unlocked.';
                        updateVerifyBanner('success', 'Payment Verified & Enrollment Confirmed!', msg, d.invoice_url || '');
                        Toast.success(msg);
                        const redirect = d.redirect || dashboardUrl;
                        setTimeout(() => {
                            window.location.href = redirect;
                        }, 1400);
                    } else {
                        const d = (res && res.data) || {};
                        const status = d.status || 'pending';
                        if (status === 'pending' || status === 'processing') {
                            const msg = d.message || 'Your payment is still being processed by the gateway. Once confirmed, your course will unlock automatically in your dashboard.';
                            updateVerifyBanner('warning', 'Payment Pending Confirmation', msg);
                            Toast.warning(msg);
                        } else {
                            const msg = d.message || 'Payment could not be verified. No enrollment was granted.';
                            updateVerifyBanner('error', 'Payment Verification Failed', msg);
                            Toast.error(msg);
                        }
                    }
                })
                .catch(() => {
                    updateVerifyBanner(
                        'warning',
                        'Verification Delayed',
                        'We could not reach the verification server right now. Please refresh this page or check Order History in your dashboard.'
                    );
                });
        }

        runReturnVerification();

        /* ---------------------------------------------------------------------
         * Stripe Payment Element v3 Mount / Reset
         * ------------------------------------------------------------------- */

        function resetStripeElement() {
            if (stripePaymentElement && typeof stripePaymentElement.unmount === 'function') {
                try {
                    stripePaymentElement.unmount();
                } catch (err) {
                    // ignore
                }
            }
            stripePaymentElement = null;
            stripeElements = null;
            stripeSession = null;
            stripeMountPromise = null;

            const mountEl = checkoutWrap.querySelector('#edupro-stripe-payment-element');
            if (mountEl) {
                mountEl.innerHTML = '<p class="edupro-stripe-loading-hint">Loading secure Stripe Payment Element…</p>';
            }
        }

        function mountStripePaymentElement() {
            const mountEl = checkoutWrap.querySelector('#edupro-stripe-payment-element');
            if (!mountEl || !courseId) return Promise.resolve(null);

            const currentCoupon = getActiveCoupon();
            if (stripeSession && stripePaymentElement && stripeSession.couponCode === currentCoupon) {
                return Promise.resolve(stripeSession);
            }
            if (stripeMountPromise && (!stripeSession || stripeSession.couponCode === currentCoupon)) {
                return stripeMountPromise;
            }

            if (stripeSession && stripeSession.couponCode !== currentCoupon) {
                resetStripeElement();
            }

            const errBox = checkoutWrap.querySelector('#edupro-stripe-card-errors, #stripe-card-errors, .stripe-errors');
            if (errBox) errBox.textContent = '';

            stripeMountPromise = Promise.all([
                loadScript('https://js.stripe.com/v3/', () => typeof window.Stripe === 'function'),
                ajaxPost('edupro_checkout_initiate', {
                    nonce: paymentNonce,
                    course_id: courseId,
                    gateway: 'stripe',
                    currency,
                    coupon_code: currentCoupon,
                }),
            ])
                .then(([, res]) => {
                    if (!res || !res.success || !res.data) {
                        throw new Error((res && res.data && res.data.message) || 'Could not initialize Stripe checkout.');
                    }

                    const data = res.data;
                    if (data.enrolled) {
                        Toast.success(data.message || 'Enrolled successfully!');
                        window.location.href = data.redirect || dashboardUrl;
                        return null;
                    }

                    const pubKey = data.publishable_key || checkoutWrap.dataset.stripePk || '';
                    if (!pubKey || !data.client_secret) {
                        throw new Error('Stripe publishable key or client secret is missing.');
                    }

                    stripeInstance = window.Stripe(pubKey);
                    stripeElements = stripeInstance.elements({
                        clientSecret: data.client_secret,
                        appearance: {
                            theme: 'stripe',
                            variables: {
                                colorPrimary: '#e31b23',
                                colorBackground: '#ffffff',
                                colorText: '#1a1a2e',
                                colorDanger: '#e31b23',
                                fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, sans-serif',
                                borderRadius: '8px',
                            },
                        },
                    });

                    mountEl.innerHTML = '';
                    stripePaymentElement = stripeElements.create('payment', { layout: 'tabs' });
                    stripePaymentElement.mount(mountEl);

                    stripePaymentElement.on('change', (ev) => {
                        if (errBox && ev.complete) errBox.textContent = '';
                    });

                    stripeSession = {
                        orderId: data.order_id || data.local_order_id || '',
                        clientSecret: data.client_secret,
                        returnUrl: data.return_url || `${checkoutUrl}${checkoutUrl.includes('?') ? '&' : '?'}edupro_stripe_return=1&order_id=${encodeURIComponent(data.order_id || '')}`,
                        paymentIntentId: data.payment_intent_id || '',
                        couponCode: currentCoupon,
                    };

                    return stripeSession;
                })
                .catch((err) => {
                    stripeMountPromise = null;
                    if (errBox) errBox.textContent = err.message || 'Failed to load Stripe Payment Element.';
                    mountEl.innerHTML = `<p class="edupro-field-error">${escapeHtml(err.message || 'Unable to load Stripe.')}</p>`;
                    throw err;
                });

            return stripeMountPromise;
        }

        // Re-initialize Stripe Payment Element when coupon changes (skip auto-remount for 100%-off coupons)
        checkoutWrap.addEventListener('edupro:coupon:changed', (ev) => {
            const finalTotal = ev && ev.detail && ev.detail.finalTotal !== undefined ? Number(ev.detail.finalTotal) : NaN;
            if (stripeSession || stripeMountPromise) {
                resetStripeElement();
            }
            if (!Number.isNaN(finalTotal) && finalTotal <= 0) {
                const mountEl = checkoutWrap.querySelector('#edupro-stripe-payment-element');
                if (mountEl) {
                    mountEl.innerHTML = '<p class="edupro-stripe-loading-hint">100% discount applied — click the button below to complete your free enrollment.</p>';
                }
                return;
            }
            if (activeGateway === 'stripe') {
                mountStripePaymentElement().catch(() => {});
            }
        });

        /* ---------------------------------------------------------------------
         * Gateway Selector Tabs
         * ------------------------------------------------------------------- */

        function selectGateway(gw) {
            if (!gw) return;
            activeGateway = gw;
            showCheckoutAlert('', 'info');

            gatewayTabs.forEach((tab) => {
                const tabGw = tab.dataset.gateway || tab.dataset.gatewayTab || tab.value;
                const isSelected = tabGw === gw;
                if (tab.tagName === 'INPUT') {
                    tab.checked = isSelected;
                    const label = tab.closest('.edupro-gateway-tab, .gateway-tab, label');
                    if (label) label.classList.toggle('is-active', isSelected);
                } else {
                    tab.classList.toggle('is-active', isSelected);
                    tab.classList.toggle('active', isSelected);
                    tab.setAttribute('aria-selected', String(isSelected));
                }
            });

            gatewayPanels.forEach((panel) => {
                const panelGw = panel.dataset.gatewayPanel || panel.dataset.gateway;
                const match = panelGw === gw;
                panel.classList.toggle('is-active', match);
                panel.hidden = !match;
            });

            if (gw === 'stripe') {
                const currentTotal = parseFloat(checkoutWrap.dataset.total);
                if (Number.isNaN(currentTotal) || currentTotal > 0) {
                    mountStripePaymentElement().catch(() => {});
                }
            }
        }

        gatewayTabs.forEach((tab) => {
            const evt = tab.tagName === 'INPUT' ? 'change' : 'click';
            tab.addEventListener(evt, (e) => {
                if (tab.tagName !== 'INPUT') e.preventDefault();
                const gw = tab.dataset.gateway || tab.dataset.gatewayTab || tab.value;
                if (gw) selectGateway(gw);
            });
        });

        const initialTab = gatewayTabs.find((t) => t.classList.contains('is-active') || t.checked) || gatewayTabs[0];
        if (initialTab) {
            selectGateway(initialTab.dataset.gateway || initialTab.dataset.gatewayTab || initialTab.value);
        }

        /* ---------------------------------------------------------------------
         * Individual Gateway Payment Executors
         * ------------------------------------------------------------------- */

        function executeCashfree(data, btn) {
            if (!data.payment_session_id) {
                throw new Error('Cashfree payment session ID was not returned.');
            }
            const cfEnv = data.cf_env || checkoutWrap.dataset.cashfreeEnv || 'sandbox';
            return loadScript('https://sdk.cashfree.com/js/v3/cashfree.js', () => typeof window.Cashfree === 'function')
                .then(() => {
                    const cashfree = window.Cashfree({ mode: cfEnv === 'production' ? 'production' : 'sandbox' });
                    return cashfree.checkout({
                        paymentSessionId: data.payment_session_id,
                        redirectTarget: '_self',
                    });
                })
                .then((result) => {
                    // Only reached if Cashfree resolved inline without redirecting
                    if (result && result.error) {
                        throw new Error(result.error.message || 'Cashfree payment failed.');
                    }
                    if (result && result.paymentDetails) {
                        setButtonLoading(btn, true, 'Verifying with Cashfree...');
                        return ajaxPost('edupro_cashfree_verify', {
                            nonce: paymentNonce,
                            order_id: data.order_id,
                            course_id: courseId,
                        }).then((verifyRes) => {
                            if (isServerVerified(verifyRes)) {
                                Toast.success((verifyRes.data && verifyRes.data.message) || 'Payment verified! Redirecting...');
                                setTimeout(() => {
                                    window.location.href = (verifyRes.data && verifyRes.data.redirect) || dashboardUrl;
                                }, 800);
                            } else {
                                throw new Error((verifyRes && verifyRes.data && verifyRes.data.message) || 'Cashfree payment could not be verified.');
                            }
                        });
                    }
                });
        }

        function executeRazorpay(data, btn) {
            return loadScript('https://checkout.razorpay.com/v1/checkout.js', () => typeof window.Razorpay === 'function')
                .then(() => new Promise((resolve, reject) => {
                    const options = {
                        key: data.key_id || checkoutWrap.dataset.razorpayKey || '',
                        amount: data.amount_paise || data.amount,
                        currency: data.currency || currency,
                        name: data.site_name || checkoutWrap.dataset.siteName || document.title || 'EduPro LMS',
                        description: data.course_title || checkoutWrap.dataset.courseTitle || 'Course Enrollment',
                        order_id: data.razorpay_order_id,
                        prefill: {
                            name: data.user_name || checkoutWrap.dataset.userName || '',
                            email: data.user_email || checkoutWrap.dataset.userEmail || '',
                        },
                        theme: { color: '#e31b23' },
                        handler: function (response) {
                            setButtonLoading(btn, true, 'Verifying payment...');
                            ajaxPost('edupro_razorpay_verify', {
                                nonce: paymentNonce,
                                order_id: data.order_id || data.local_order_id || '',
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                                course_id: data.course_id || courseId,
                            })
                                .then((verifyRes) => {
                                    if (isServerVerified(verifyRes)) {
                                        Toast.success((verifyRes.data && verifyRes.data.message) || 'Payment verified! Redirecting...');
                                        setTimeout(() => {
                                            window.location.href = (verifyRes.data && verifyRes.data.redirect) || dashboardUrl;
                                        }, 800);
                                        resolve();
                                    } else {
                                        reject(new Error((verifyRes && verifyRes.data && verifyRes.data.message) || 'Razorpay payment verification failed.'));
                                    }
                                })
                                .catch(() => reject(new Error('Error verifying Razorpay payment with server.')));
                        },
                        modal: {
                            ondismiss: function () {
                                isProcessing = false;
                                setButtonLoading(btn, false);
                                Toast.warning('Payment window closed.');
                                resolve();
                            },
                        },
                    };

                    const rzp = new window.Razorpay(options);
                    rzp.on('payment.failed', function (resp) {
                        reject(new Error((resp.error && resp.error.description) || 'Razorpay payment failed.'));
                    });
                    rzp.open();
                }));
        }

        function executeStripe(btn) {
            const errBox = checkoutWrap.querySelector('#edupro-stripe-card-errors, #stripe-card-errors, .stripe-errors');
            if (errBox) errBox.textContent = '';

            return mountStripePaymentElement()
                .then((session) => {
                    if (!session) return null; // Free / already redirected
                    if (!stripeInstance || !stripeElements) {
                        throw new Error('Stripe Payment Element is not ready.');
                    }

                    const billingName = checkoutWrap.querySelector('#edupro-stripe-cardholder, input[name="billing_name"]')?.value
                        || checkoutWrap.dataset.userName
                        || '';
                    const billingEmail = checkoutWrap.dataset.userEmail || '';

                    return stripeInstance
                        .confirmPayment({
                            elements: stripeElements,
                            confirmParams: {
                                return_url: session.returnUrl,
                                payment_method_data: {
                                    billing_details: {
                                        name: billingName || undefined,
                                        email: billingEmail || undefined,
                                    },
                                },
                            },
                            redirect: 'if_required',
                        })
                        .then((result) => ({ result, session }));
                })
                .then((payload) => {
                    if (!payload) return;
                    const { result, session } = payload;

                    if (result.error) {
                        if (errBox) errBox.textContent = result.error.message || 'Card payment failed.';
                        throw new Error(result.error.message || 'Payment failed.');
                    }

                    if (result.paymentIntent) {
                        const pi = result.paymentIntent;
                        if (pi.status === 'succeeded') {
                            setButtonLoading(btn, true, 'Confirming payment...');
                            return ajaxPost('edupro_stripe_confirm', {
                                nonce: paymentNonce,
                                order_id: session.orderId,
                                payment_intent_id: pi.id,
                                course_id: courseId,
                            }).then((confirmRes) => {
                                if (isServerVerified(confirmRes)) {
                                    Toast.success((confirmRes.data && confirmRes.data.message) || 'Payment verified! Enrolling...');
                                    setTimeout(() => {
                                        window.location.href = (confirmRes.data && confirmRes.data.redirect) || dashboardUrl;
                                    }, 800);
                                } else {
                                    throw new Error((confirmRes && confirmRes.data && confirmRes.data.message) || 'Server verification failed.');
                                }
                            });
                        }

                        if (pi.status === 'processing') {
                            const msg = 'Your payment is processing with your bank. Your course will unlock automatically once confirmed.';
                            showCheckoutAlert(msg, 'info');
                            Toast.info(msg);
                            isProcessing = false;
                            setButtonLoading(btn, false);
                            return;
                        }

                        throw new Error(`Payment status: ${pi.status}. Please try again.`);
                    }
                });
        }

        function executePayPal(data) {
            const url = data.approve_url || data.redirect_url || data.approval_url || data.url;
            if (!url) {
                throw new Error('PayPal approval URL was not returned.');
            }
            Toast.info('Redirecting to PayPal...', 3000);
            window.location.href = url;
        }

        function executeBankTransfer(data, btn) {
            const orderId = data.order_id || data.local_order_id || '';
            const confirmBox = checkoutWrap.querySelector('#edupro-bank-order-confirmation, .bank-transfer-confirmation, [data-bank-confirmation]');
            const html = `
                <div class="edupro-bank-confirm-box">
                    <h4>Order Received — Pending Manual Verification</h4>
                    <p><strong>Order Reference:</strong> <code>${escapeHtml(orderId)}</code></p>
                    <p>Please include your Order Reference in the bank transfer / UPI remark. Your course enrollment will be activated once our team verifies receipt of funds.</p>
                    ${data.instructions ? `<p>${escapeHtml(data.instructions)}</p>` : ''}
                    <p style="margin-top:10px;"><a href="${escapeHtml(`${dashboardUrl}${dashboardUrl.includes('?') ? '&' : '?'}tab=orders`)}" class="edupro-btn edupro-btn-outline edupro-btn-sm">View Order Status in Dashboard &rarr;</a></p>
                </div>
            `;
            if (confirmBox) {
                confirmBox.innerHTML = html;
                confirmBox.style.display = 'block';
                confirmBox.hidden = false;
                confirmBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                Modal.open(html, { title: 'Bank Transfer Order Created (Pending Verification)' });
            }
            Toast.info('Pending bank transfer order created. Enrollment activates after payment verification.');
            isProcessing = false;
            setButtonLoading(btn, false);
            if (btn) {
                btn.disabled = true;
                btn.textContent = `Order Created (${orderId}) — Pending Verification`;
            }
        }

        /* ---------------------------------------------------------------------
         * Unified Pay Dispatcher (with double-submit lock)
         * ------------------------------------------------------------------- */

        function startGatewayPayment(gateway, triggerBtn) {
            if (isProcessing) return;
            if (!courseId) {
                showCheckoutAlert('Course ID is missing.', 'error');
                return;
            }

            const gw = gateway || activeGateway;
            if (!gw) {
                showCheckoutAlert('Please select a payment method.', 'error');
                return;
            }

            isProcessing = true;
            showCheckoutAlert('', 'info');
            setButtonLoading(triggerBtn, true, gw === 'stripe' ? 'Confirming payment...' : 'Initiating payment...');

            // Stripe Payment Element already created its PaymentIntent when mounted
            if (gw === 'stripe') {
                executeStripe(triggerBtn)
                    .catch((err) => {
                        showCheckoutAlert(err.message || 'Stripe payment failed.', 'error');
                        Toast.error(err.message || 'Stripe payment failed.');
                        isProcessing = false;
                        setButtonLoading(triggerBtn, false);
                    });
                return;
            }

            ajaxPost('edupro_checkout_initiate', {
                nonce: paymentNonce,
                course_id: courseId,
                gateway: gw,
                currency,
                coupon_code: getActiveCoupon(),
            })
                .then((res) => {
                    if (!res || !res.success || !res.data) {
                        throw new Error((res && res.data && res.data.message) || 'Could not initiate checkout.');
                    }

                    const data = res.data;

                    if (data.enrolled) {
                        Toast.success(data.message || 'Enrolled successfully!');
                        window.location.href = data.redirect || dashboardUrl;
                        return;
                    }

                    switch (data.gateway || gw) {
                        case 'cashfree':
                            return executeCashfree(data, triggerBtn);
                        case 'razorpay':
                            return executeRazorpay(data, triggerBtn);
                        case 'paypal':
                            return executePayPal(data);
                        case 'bank_transfer':
                            return executeBankTransfer(data, triggerBtn);
                        default:
                            throw new Error('Unsupported payment gateway.');
                    }
                })
                .catch((err) => {
                    showCheckoutAlert(err.message || 'Payment could not be processed.', 'error');
                    Toast.error(err.message || 'Payment could not be processed.');
                    isProcessing = false;
                    setButtonLoading(triggerBtn, false);
                });
        }

        // Bind per-panel gateway pay buttons (v1.1.0 template-checkout.php)
        checkoutWrap.querySelectorAll('[data-gateway-pay]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                startGatewayPayment(btn.dataset.gatewayPay, btn);
            });
        });

        // Fallback for legacy single checkout form / shared pay button
        const checkoutForm = checkoutWrap.querySelector('form#edupro-checkout-form, form.checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', (e) => {
                e.preventDefault();
                startGatewayPayment(activeGateway, sharedPayBtn);
            });
        } else if (sharedPayBtn) {
            sharedPayBtn.addEventListener('click', (e) => {
                e.preventDefault();
                startGatewayPayment(activeGateway, sharedPayBtn);
            });
        }
    }

    /* =========================================================================
     * 19. STUDENT DASHBOARD TABS & CIRCULAR PROGRESS RINGS (SVG / CANVAS)
     * ========================================================================= */

    function renderCircularProgressRing(el, overridePercent) {
        const percent = Math.min(
            100,
            Math.max(0, parseFloat(overridePercent !== undefined ? overridePercent : (el.dataset.progress || el.dataset.percent || 0)))
        );

        // SVG progress ring
        const progressCircle = el.querySelector('.progress-ring__circle, .ring-progress, circle[data-progress-circle]');
        if (progressCircle) {
            const radius = parseFloat(progressCircle.getAttribute('r')) || 28;
            const circumference = 2 * Math.PI * radius;
            const offset = circumference - (percent / 100) * circumference;
            progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
            progressCircle.style.strokeDashoffset = String(offset);

            const textEl = el.querySelector('.progress-ring__text, .ring-label, text');
            if (textEl) {
                textEl.textContent = `${Math.round(percent)}%`;
            }
            return;
        }

        // Canvas fallback
        const canvas = el.tagName === 'CANVAS' ? el : el.querySelector('canvas');
        if (canvas && canvas.getContext) {
            const ctx = canvas.getContext('2d');
            const size = parseInt(el.dataset.size, 10) || canvas.width || 72;
            const lineWidth = parseInt(el.dataset.stroke, 10) || 6;
            const dpr = window.devicePixelRatio || 1;
            canvas.width = size * dpr;
            canvas.height = size * dpr;
            canvas.style.width = `${size}px`;
            canvas.style.height = `${size}px`;
            ctx.scale(dpr, dpr);

            const cx = size / 2;
            const cy = size / 2;
            const radius = (size - lineWidth) / 2;

            ctx.clearRect(0, 0, size, size);

            // Background track
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.strokeStyle = el.dataset.trackColor || '#e8e8f0';
            ctx.lineWidth = lineWidth;
            ctx.stroke();

            // Active progress arc
            const startAngle = -Math.PI / 2;
            const endAngle = startAngle + (Math.PI * 2 * percent) / 100;
            ctx.beginPath();
            ctx.arc(cx, cy, radius, startAngle, endAngle);
            ctx.strokeStyle = percent >= 100 ? '#2ecc71' : (el.dataset.color || '#e31b23');
            ctx.lineWidth = lineWidth;
            ctx.lineCap = 'round';
            ctx.stroke();

            // Center label
            ctx.fillStyle = '#1a1a2e';
            ctx.font = `700 ${Math.round(size * 0.22)}px Inter, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(`${Math.round(percent)}%`, cx, cy);
        }
    }

    function initDashboard() {
        // Animate all circular progress rings when scrolled into view
        const rings = document.querySelectorAll('.edupro-progress-ring, .circular-progress, [data-progress-ring]');
        if (rings.length) {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                renderCircularProgressRing(entry.target);
                                observer.unobserve(entry.target);
                            }
                        });
                    },
                    { threshold: 0.2 }
                );
                rings.forEach((r) => observer.observe(r));
            } else {
                rings.forEach((r) => renderCircularProgressRing(r));
            }
        }

        const dashboard = document.querySelector('.edupro-dashboard, .student-dashboard, #edupro-dashboard');
        if (!dashboard) return;

        const navLinks = Array.from(dashboard.querySelectorAll('.dashboard-nav-link, .dashboard-bottom-nav__item, [data-dashboard-tab]'));
        const panels = Array.from(dashboard.querySelectorAll('.dashboard-tab-panel, .dashboard-panel, [data-dashboard-panel]'));
        if (!navLinks.length || !panels.length) return;

        function switchDashboardTab(tabName, updateHash = true) {
            if (!tabName) return;

            navLinks.forEach((link) => {
                const target = link.dataset.dashboardTab || (link.getAttribute('href') || '').replace(/^#/, '');
                const active = target === tabName;
                link.classList.toggle('is-active', active);
                link.classList.toggle('active', active);
                link.setAttribute('aria-current', active ? 'page' : 'false');
            });

            panels.forEach((panel) => {
                const panelName = panel.dataset.dashboardPanel || panel.id.replace(/^dashboard-tab-/, '').replace(/^tab-/, '');
                const active = panelName === tabName || panel.id === tabName;
                panel.classList.toggle('is-active', active);
                panel.classList.toggle('active', active);
                panel.hidden = !active;

                if (active) {
                    panel.querySelectorAll('.edupro-progress-ring, .circular-progress, [data-progress-ring]').forEach((r) => {
                        renderCircularProgressRing(r);
                    });
                }
            });

            if (updateHash && history.replaceState) {
                history.replaceState(null, '', `#${tabName}`);
            }
        }

        navLinks.forEach((link) => {
            link.addEventListener('click', (e) => {
                const tabName = link.dataset.dashboardTab;
                if (!tabName) return; // Allow Logout links to navigate normally
                e.preventDefault();
                switchDashboardTab(tabName, true);
                if (window.innerWidth < 992) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });

        // Initial tab from URL hash
        const hash = window.location.hash.replace('#', '');
        if (hash && panels.some((p) => (p.dataset.dashboardPanel === hash || p.id === hash || p.id === `tab-${hash}`))) {
            switchDashboardTab(hash, false);
        }
    }

    /* =========================================================================
     * 20. PUBLIC API & BOOTSTRAP
     * ========================================================================= */

    Object.assign(EduPro, {
        config: CONFIG,
        ajaxPost,
        debounce,
        throttle,
        escapeHtml,
        loadScript,
        setButtonLoading,
        toast: Toast,
        modal: Modal,
        validator: Validator,
        markLessonComplete,
        renderCircularProgressRing,
        parseVideoSource,
        buildVideoEmbedHtml,
    });

    window.EduPro = EduPro;

    function boot() {
        initStickyHeader();
        initAnnouncementBar();
        initMobileMenu();
        initMegaMenu();
        initSmoothScroll();
        initScrollToTop();
        initAnimatedCounters();
        initAnimateOnScroll();
        initLiveSearch();
        initCourseFilters();
        initLoadMoreCourses();
        initReviewCarousel();
        Validator.init();
        initAuthForms();
        initInlineVideos();
        initAccordions();
        initCourseTabs();
        initLessonMarkComplete();
        initCurrencySwitcher();
        initCheckoutCoupons();
        initCheckout();
        initDashboard();
        Modal.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // jQuery compatibility hook if other plugins trigger custom events
    if ($) {
        $(document).on('edupro:refresh', () => {
            initAnimateOnScroll();
            initAccordions();
            initInlineVideos();
        });
    }
})(window, document, window.jQuery);
