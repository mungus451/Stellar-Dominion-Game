/**
 * STEP 6: JavaScript for AJAX Requests
 * File: public/assets/js/csrf.js
 */
class CSRFManager {
    constructor() {
        this.tokens = new Map();
    }

    async getToken(action = 'default') {
        try {
            const response = await fetch(`/api/csrf-token.php?action=${encodeURIComponent(action)}`);
            const data = await response.json();

            if (data.success) {
                this.tokens.set(action, data.token);
                return data.token;
            }
            throw new Error('Failed to get CSRF token');
        } catch (error) {
            console.error('CSRF Token Error:', error);
            return null;
        }
    }

    async makeSecureRequest(url, options = {}, action = 'default') {
        const token = await this.getToken(action);
        if (!token) {
            throw new Error('Could not obtain CSRF token');
        }

        const defaultOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            }
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        if (mergedOptions.method === 'POST' && mergedOptions.body) {
            let body = JSON.parse(mergedOptions.body);
            body.csrf_token = token;
            body.csrf_action = action;
            mergedOptions.body = JSON.stringify(body);
        }

        return fetch(url, mergedOptions);
    }

    // Helper for form submissions
    async submitSecureForm(formElement, action = 'default') {
        const token = await this.getToken(action);
        if (!token) {
            alert('Security error. Please refresh the page and try again.');
            return false;
        }

        // Add CSRF token to form
        let tokenInput = formElement.querySelector('input[name="csrf_token"]');
        if (!tokenInput) {
            tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'csrf_token';
            formElement.appendChild(tokenInput);
        }
        tokenInput.value = token;

        let actionInput = formElement.querySelector('input[name="csrf_action"]');
        if (!actionInput) {
            actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'csrf_action';
            formElement.appendChild(actionInput);
        }
        actionInput.value = action;

        return true;
    }
}

// Global CSRF manager instance
const csrfManager = new CSRFManager();

// Example usage for AJAX attacks
async function launchAttack(targetId, attackTurns) {
    try {
        const response = await csrfManager.makeSecureRequest('/controllers/AttackController.php', {
            body: JSON.stringify({
                target_id: targetId,
                attack_turns: attackTurns
            })
        }, 'attack');

        const result = await response.json();
        // Handle attack result
        console.log('Attack result:', result);
    } catch (error) {
        console.error('Attack failed:', error);
    }
}

// Auto-protect all forms on page load
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            if (!form.querySelector('input[name="csrf_token"]')) {
                e.preventDefault();

                const action = form.querySelector('input[name="csrf_action"]')?.value || 'default';
                const success = await csrfManager.submitSecureForm(form, action);

                if (success) {
                    form.submit();
                }
            }
        });
    });
});
