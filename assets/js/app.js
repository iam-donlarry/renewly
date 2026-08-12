/**
 * Renewly Enterprise JavaScript Engine
 * Centralized AJAX, Custom Dialogs (Confirm/Prompt/Alert), Toast Alerts & UI Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide Icons
    if (window.lucide) {
        lucide.createIcons();
    }

    // Sidebar Mobile Toggle
    const mobileToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }
});

/**
 * Custom Toast Notification Generator
 */
function showToast(message, type = 'success', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    
    const iconMap = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    const icon = iconMap[type] || 'info';

    toast.innerHTML = `
        <i data-lucide="${icon}" class="w-5 h-5 text-${type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'primary')}"></i>
        <div class="flex-grow-1 text-sm font-medium" style="font-family: 'Outfit', sans-serif;">${message}</div>
        <button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.remove()"></button>
    `;

    container.appendChild(toast);
    if (window.lucide) lucide.createIcons();

    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, duration);
}

/**
 * Custom Brand Confirm Modal Dialog (Replaces native confirm())
 * @param {string} message - Description message
 * @param {string} title - Modal heading title
 * @param {string} confirmText - Confirm button text
 * @param {string} confirmBtnClass - Confirm button color class ('btn-primary' or 'btn-danger')
 * @returns {Promise<boolean>}
 */
function customConfirm(message, title = 'Confirm Action', confirmText = 'Confirm', confirmBtnClass = 'btn-primary') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            padding: 1rem; animation: fadeIn 0.2s ease-out;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 440px; width: 100%; padding: 1.75rem; font-family: 'Outfit', sans-serif;
            animation: scaleUp 0.2s ease-out;
        `;

        modal.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #e0f2f2; color: #12b1b0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="help-circle" style="width: 22px; height: 22px; stroke-width: 2;"></i>
                </div>
                <h5 style="font-weight: 700; font-size: 1.15rem; margin: 0; color: #0f172a;">${title}</h5>
            </div>
            <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5;">${message}</p>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline py-2 px-3" id="confirmCancelBtn" style="border: 1px solid #cbd5e1; color: #475569; font-weight: 600; font-size: 0.875rem;">Cancel</button>
                <button type="button" class="btn ${confirmBtnClass} py-2 px-4" id="confirmOkBtn" style="font-weight: 600; font-size: 0.875rem;">${confirmText}</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        if (window.lucide) lucide.createIcons();

        const close = (result) => {
            overlay.remove();
            resolve(result);
        };

        modal.querySelector('#confirmCancelBtn').addEventListener('click', () => close(false));
        modal.querySelector('#confirmOkBtn').addEventListener('click', () => close(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(false);
        });
    });
}

/**
 * Custom Brand Prompt Modal Dialog (Replaces native prompt())
 * @param {string} message - Prompt label
 * @param {string} title - Prompt title
 * @param {string} placeholder - Input placeholder
 * @param {string} defaultValue - Initial input value
 * @returns {Promise<string|null>}
 */
function customPrompt(message, title = 'Input Required', placeholder = '', defaultValue = '') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            padding: 1rem; animation: fadeIn 0.2s ease-out;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 440px; width: 100%; padding: 1.75rem; font-family: 'Outfit', sans-serif;
            animation: scaleUp 0.2s ease-out;
        `;

        modal.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #e0f2f2; color: #12b1b0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="edit-3" style="width: 22px; height: 22px; stroke-width: 2;"></i>
                </div>
                <h5 style="font-weight: 700; font-size: 1.15rem; margin: 0; color: #0f172a;">${title}</h5>
            </div>
            <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.75rem;">${message}</p>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" id="promptInputField" class="form-control" placeholder="${placeholder}" value="${defaultValue}" style="border-radius: 8px; border: 1px solid #cbd5e1; padding: 0.65rem 1rem; font-size: 0.875rem;" autofocus>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-outline py-2 px-3" id="promptCancelBtn" style="border: 1px solid #cbd5e1; color: #475569; font-weight: 600; font-size: 0.875rem;">Cancel</button>
                <button type="button" class="btn btn-primary py-2 px-4" id="promptSubmitBtn" style="font-weight: 600; font-size: 0.875rem;">Submit</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        if (window.lucide) lucide.createIcons();

        const inputField = modal.querySelector('#promptInputField');
        inputField.focus();

        const close = (val) => {
            overlay.remove();
            resolve(val);
        };

        modal.querySelector('#promptCancelBtn').addEventListener('click', () => close(null));
        modal.querySelector('#promptSubmitBtn').addEventListener('click', () => close(inputField.value));
        inputField.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') close(inputField.value);
            if (e.key === 'Escape') close(null);
        });
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close(null);
        });
    });
}

/**
 * Custom Brand Alert Modal Dialog (Replaces native alert())
 * @param {string} message - Alert message
 * @param {string} title - Alert title
 * @param {string} type - Icon type ('info', 'success', 'error', 'warning')
 * @returns {Promise<void>}
 */
function customAlert(message, title = 'Notice', type = 'info') {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            padding: 1rem; animation: fadeIn 0.2s ease-out;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 440px; width: 100%; padding: 1.75rem; font-family: 'Outfit', sans-serif;
            animation: scaleUp 0.2s ease-out;
        `;

        const iconMap = {
            info: { icon: 'info', bg: '#e0f2f2', color: '#12b1b0' },
            success: { icon: 'check-circle', bg: '#dcfce7', color: '#10b981' },
            error: { icon: 'alert-circle', bg: '#fee2e2', color: '#ef4444' },
            warning: { icon: 'alert-triangle', bg: '#fef3c7', color: '#f59e0b' }
        };
        const cfg = iconMap[type] || iconMap.info;

        modal.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: ${cfg.bg}; color: ${cfg.color}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="${cfg.icon}" style="width: 22px; height: 22px; stroke-width: 2;"></i>
                </div>
                <h5 style="font-weight: 700; font-size: 1.15rem; margin: 0; color: #0f172a;">${title}</h5>
            </div>
            <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5;">${message}</p>
            <div style="display: flex; justify-content: flex-end;">
                <button type="button" class="btn btn-primary py-2 px-4" id="alertOkBtn" style="font-weight: 600; font-size: 0.875rem;">OK</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        if (window.lucide) lucide.createIcons();

        const close = () => {
            overlay.remove();
            resolve();
        };

        modal.querySelector('#alertOkBtn').addEventListener('click', close);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
    });
}

/**
 * Standardized AJAX Request Helper
 */
async function fetchAPI(url, options = {}) {
    const defaultHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json'
    };

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    try {
        const response = await fetch(url, options);
        const json = await response.json();

        if (!response.ok || json.success === false) {
            throw new Error(json.message || 'Server request failed.');
        }
        return json;
    } catch (err) {
        showToast(err.message, 'error');
        throw err;
    }
}
