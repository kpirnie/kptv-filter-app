/**
 * DataTables Plain/Tailwind Theme Helper
 * 
 * Provides modal and notification functionality for plain and Tailwind themes
 * that don't have a framework providing these features.
 * 
 * @since   1.1.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

const KPDataTablesPlain = {
    /**
     * Show a modal
     * @param {string} modalId - The modal element ID
     */
    showModal: function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('kp-dt-open');
            modal.classList.add('kp-dt-open-tailwind');
            document.body.style.overflow = 'hidden';
        }
    },

    /**
     * Hide a modal
     * @param {string} modalId - The modal element ID
     */
    hideModal: function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('kp-dt-open');
            modal.classList.remove('kp-dt-open-tailwind');
            document.body.style.overflow = '';
        }
    },

    /**
     * Show a notification
     * @param {string} message - The message to display
     * @param {string} status - The status type (success, danger, warning)
     */
    notification: function (message, status = 'success') {
        const container = document.querySelector('.kp-dt-notification-container') || this.createNotificationContainer();

        const notification = document.createElement('div');
        notification.className = `kp-dt-notification kp-dt-notification-${status} kp-dt-notification-tailwind kp-dt-notification-${status}-tailwind`;
        notification.textContent = message;

        container.appendChild(notification);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    /**
     * Create notification container if not exists
     * @returns {HTMLElement} The notification container
     */
    createNotificationContainer: function () {
        const container = document.createElement('div');
        container.className = 'kp-dt-notification-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1040; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
        return container;
    },

    /**
     * Show a confirmation dialog
     * @param {string} message - The confirmation message
     * @returns {Promise} Resolves if confirmed, rejects if cancelled
     */
    confirm: function (message) {
        return new Promise((resolve, reject) => {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'kp-dt-modal kp-dt-modal-tailwind kp-dt-open kp-dt-open-tailwind';
            overlay.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1050;';

            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = 'kp-dt-modal-dialog kp-dt-modal-dialog-tailwind';
            dialog.style.cssText = 'background: white; padding: 30px; border-radius: 4px; max-width: 400px; text-align: center;';

            dialog.innerHTML = `
                <p style="margin-bottom: 20px;">${message}</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button class="kp-dt-button kp-dt-button-tailwind kp-dt-confirm-cancel" style="padding: 8px 24px;">Cancel</button>
                    <button class="kp-dt-button kp-dt-button-primary kp-dt-button-tailwind kp-dt-button-primary-tailwind kp-dt-confirm-ok" style="padding: 8px 24px;">Confirm</button>
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // Handle clicks
            dialog.querySelector('.kp-dt-confirm-ok').addEventListener('click', () => {
                overlay.remove();
                resolve();
            });

            dialog.querySelector('.kp-dt-confirm-cancel').addEventListener('click', () => {
                overlay.remove();
                reject();
            });

            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    reject();
                }
            });
        });
    }
};

// Also add Bootstrap helper
const KPDataTablesBootstrap = {
    /**
     * Show a notification using Bootstrap toast
     * @param {string} message - The message to display
     * @param {string} status - The status type (success, danger, warning)
     */
    notification: function (message, status = 'success') {
        let container = document.querySelector('.kp-dt-toast-container-bootstrap');
        if (!container) {
            container = document.createElement('div');
            container.className = 'kp-dt-toast-container-bootstrap toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
        }

        const bgClass = status === 'success' ? 'bg-success' : (status === 'danger' ? 'bg-danger' : 'bg-warning');

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white ${bgClass} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    },

    /**
     * Show a confirmation dialog using Bootstrap modal
     * @param {string} message - The confirmation message
     * @returns {Promise} Resolves if confirmed, rejects if cancelled
     */
    confirm: function (message) {
        return new Promise((resolve, reject) => {
            const modalId = 'kp-dt-confirm-modal-' + Date.now();

            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body text-center py-4">
                                <p class="mb-4">${message}</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary kp-dt-confirm-ok">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = document.getElementById(modalId);
            const bsModal = new bootstrap.Modal(modal);

            modal.querySelector('.kp-dt-confirm-ok').addEventListener('click', () => {
                bsModal.hide();
                resolve();
            });

            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                reject();
            });

            bsModal.show();
        });
    }
};

/**
 * Switch tab in plain/tailwind tab UI
 * @param {HTMLElement} btn - The clicked tab button
 * @param {string} targetId - The panel ID to show
 */
KPDataTablesPlain.switchTab = function (btn, targetId) {
    const tabContainer = btn.closest('.kp-dt-tabs, .kp-dt-tabs-tailwind');
    if (!tabContainer) return;

    // Deactivate all buttons
    tabContainer.querySelectorAll('.kp-dt-tab-btn, .kp-dt-tab-btn-tailwind').forEach(b => {
        b.classList.remove('kp-dt-tab-active', 'kp-dt-tab-active-tailwind');
    });
    btn.classList.add('kp-dt-tab-active');
    if (btn.classList.contains('kp-dt-tab-btn-tailwind')) {
        btn.classList.add('kp-dt-tab-active-tailwind');
    }

    // Hide all panels, show target
    tabContainer.querySelectorAll('.kp-dt-tab-panel, .kp-dt-tab-panel-tailwind').forEach(p => {
        p.style.display = 'none';
    });
    const target = document.getElementById(targetId);
    if (target) {
        target.style.display = 'block';
    }
};

// Make available globally
window.KPDataTablesPlain = KPDataTablesPlain;
window.KPDataTablesBootstrap = KPDataTablesBootstrap;

/**
 * Datepicker helper for formatting dates
 */
const KPDataTablesDatepicker = {
    /**
     * Format a YYYY-MM-DD date string using a format pattern
     * Tokens: YYYY, YY, MM, M, DD, D
     */
    format: function (isoDate, formatter) {
        if (!isoDate) return '';
        const parts = isoDate.split('-');
        if (parts.length !== 3) return isoDate;
        const y = parts[0], m = parts[1], d = parts[2];
        return formatter
            .replace('YYYY', y)
            .replace('YY', y.slice(-2))
            .replace('MM', m)
            .replace('M', parseInt(m, 10).toString())
            .replace('DD', d)
            .replace('D', parseInt(d, 10).toString());
    },

    /**
     * Parse a formatted date string back to YYYY-MM-DD
     */
    parseToISO: function (dateStr, formatter) {
        if (!dateStr) return '';
        // Build regex from formatter
        let pattern = formatter
            .replace('YYYY', '(?<y>\\d{4})')
            .replace('YY', '(?<y2>\\d{2})')
            .replace('MM', '(?<m>\\d{1,2})')
            .replace('M', '(?<m>\\d{1,2})')
            .replace('DD', '(?<d>\\d{1,2})')
            .replace('D', '(?<d>\\d{1,2})');
        // Escape separators
        pattern = pattern.replace(/([\/\.\-])/g, '\\$1');
        try {
            const match = new RegExp('^' + pattern + '$').exec(dateStr);
            if (!match || !match.groups) return '';
            const year = match.groups.y || ('20' + (match.groups.y2 || '00'));
            const month = (match.groups.m || '1').padStart(2, '0');
            const day = (match.groups.d || '1').padStart(2, '0');
            return `${year}-${month}-${day}`;
        } catch (e) {
            return '';
        }
    },

    /**
     * Apply date from native picker to display input
     */
    applyDate: function (nativeInput) {
        const targetId = nativeInput.getAttribute('data-target');
        const formatter = nativeInput.getAttribute('data-formatter') || 'YYYY-MM-DD';
        const display = document.getElementById(targetId);
        if (display) {
            display.value = this.format(nativeInput.value, formatter);
        }
    }
};

// make globally available
window.KPDataTablesDatepicker = KPDataTablesDatepicker;