import { Controller } from '@hotwired/stimulus';
import { createFormState } from '../auth/form-state.js';
import { generateCsrfHeaders } from './csrf_protection_controller.js';

const minimumInputs = 3;
const maximumInputs = 3;
const maxInputLength = 255;
const submittingLabel = 'AI analizuje Twoje preferencje…';
const defaultSubmitLabel = 'Generuj rekomendacje';
const bannerTimeoutMs = 6000;
const defaultModel = 'openrouter/openai/gpt-4o-mini';

export default class extends Controller {
    static targets = [
        'banner',
        'fieldSummary',
        'fieldSummaryList',
        'form',
        'submit',
        'inputsContainer',
        'inputsGeneralError',
        'inputTemplate',
        'inputRow',
        'exampleSection',
    ];

    connect() {
        this.bannerState = null;
        this.bannerTimeout = null;
        this.formState = null;
        this.formStateFields = {};
        this.lastValidPayload = null;

        this.vm = {
            inputs: Array.from({ length: minimumInputs }, () => ''),
            model: defaultModel,
        };

        this.touched = Array.from({ length: this.vm.inputs.length }, () => false);

        this.renderInputs();
        this.initialiseFormState();
        this.clearBanner();

        if (this.formState) {
            this.formState.setFieldErrors({});
        }
    }

    disconnect() {
        this.clearBannerTimeout();
    }

    initialiseFormState() {
        if (!this.hasFormTarget) {
            return;
        }

        this.formState = createFormState({
            form: this.formTarget,
            submitButton: this.hasSubmitTarget ? this.submitTarget : null,
            banner: null,
            fieldSummary: this.hasFieldSummaryTarget ? this.fieldSummaryTarget : null,
            fieldSummaryList: this.hasFieldSummaryListTarget ? this.fieldSummaryListTarget : null,
            fields: this.formStateFields,
        });
    }

    renderInputs() {
        if (!this.hasInputsContainerTarget || !this.hasInputTemplateTarget) {
            return;
        }

        const template = this.inputTemplateTarget;
        const container = this.inputsContainerTarget;
        const currentErrors = this.formState?.state?.fieldErrors ?? {};

        container.innerHTML = '';

        Object.keys(this.formStateFields).forEach((field) => {
            if (field === 'inputs') {
                return;
            }

            if (field.startsWith('inputs[')) {
                delete this.formStateFields[field];
            }
        });

        const rowElements = [];

        this.vm.inputs.forEach((value, index) => {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('[data-ai-recommendations-form-target="inputRow"]');
            const label = row?.querySelector('label');
            const input = row?.querySelector('input');
            const removeButton = row?.querySelector('button[data-action*="removeInput"]');
            const errorElement = row?.querySelector('.form-field__error');

            if (!(row instanceof HTMLElement) || !(input instanceof HTMLInputElement) || !(label instanceof HTMLLabelElement) || !(removeButton instanceof HTMLButtonElement) || !(errorElement instanceof HTMLElement)) {
                return;
            }

            const inputId = `ai-input-${index}`;
            const errorId = `${inputId}-error`;

            row.dataset.index = String(index);

            label.textContent = `Pozycja ${index + 1}`;
            label.htmlFor = inputId;

            input.id = inputId;
            input.value = value;
            input.dataset.aiRecommendationsFormIndexParam = String(index);
            input.setAttribute('data-ai-recommendations-form-index-param', String(index));

            removeButton.dataset.aiRecommendationsFormIndexParam = String(index);
            removeButton.setAttribute('data-ai-recommendations-form-index-param', String(index));

            errorElement.id = errorId;

            rowElements.push({ row, input, errorElement, removeButton });
            container.appendChild(row);

            this.formStateFields[`inputs[${index}]`] = {
                input,
                messageElement: errorElement,
            };
        });

        const firstRow = rowElements[0];
        this.formStateFields.inputs = {
            input: firstRow?.input ?? null,
            messageElement: this.hasInputsGeneralErrorTarget ? this.inputsGeneralErrorTarget : null,
        };

        this.toggleRemoveButtons();
        this.restoreFieldErrors(currentErrors);
    }

    restoreFieldErrors(errors) {
        if (!this.formState) {
            return;
        }

        const nextErrors = { ...errors };

        Object.keys(nextErrors).forEach((field) => {
            if (!this.formStateFields[field]) {
                delete nextErrors[field];
            }
        });

        this.formState.setFieldErrors(nextErrors);
    }

    toggleRemoveButtons() {
        const allowRemoval = this.vm.inputs.length > minimumInputs;

        this.inputRowTargets.forEach((row) => {
            const removeButton = row.querySelector('button[data-action*="removeInput"]');

            if (!(removeButton instanceof HTMLButtonElement)) {
                return;
            }

            removeButton.hidden = !allowRemoval;
            removeButton.disabled = !allowRemoval;
        });
    }

    addInput() {
        if (this.vm.inputs.length >= maximumInputs) {
            return;
        }

        this.vm.inputs.push('');
        this.touched.push(false);
        this.renderInputs();
        this.focusLastInput();
    }

    removeInput(event) {
        const index = this.resolveEventIndex(event);

        if (index === null || this.vm.inputs.length <= minimumInputs) {
            return;
        }

        this.vm.inputs.splice(index, 1);
        this.touched.splice(index, 1);

        if (this.formState) {
            const currentErrors = { ...(this.formState.state.fieldErrors ?? {}) };
            delete currentErrors[`inputs[${index}]`];

            const normalizedErrors = {};

            Object.entries(currentErrors).forEach(([field, messages]) => {
                const match = field.match(/^inputs\[(\d+)]$/);

                if (!match) {
                    normalizedErrors[field] = messages;
                    return;
                }

                const currentIndex = Number.parseInt(match[1], 10);

                if (Number.isNaN(currentIndex)) {
                    return;
                }

                if (currentIndex > index) {
                    normalizedErrors[`inputs[${currentIndex - 1}]`] = messages;
                    return;
                }

                if (currentIndex < index) {
                    normalizedErrors[`inputs[${currentIndex}]`] = messages;
                }
            });

            this.formState.setFieldErrors(normalizedErrors);
        }

        this.renderInputs();
    }

    onInputChange(event) {
        const index = this.resolveEventIndex(event);

        if (index === null) {
            return;
        }

        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        this.vm.inputs[index] = input.value;

        if (this.touched[index]) {
            const error = this.validateSingleInput(input.value);
            this.setFieldError(`inputs[${index}]`, error);
        } else if (this.formState) {
            this.formState.clearFieldError(`inputs[${index}]`);
        }

        if (this.anyNonEmptyInput() && this.formState) {
            this.formState.clearFieldError('inputs');
        }
    }

    onInputBlur(event) {
        const index = this.resolveEventIndex(event);

        if (index === null) {
            return;
        }

        this.touched[index] = true;

        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const error = this.validateSingleInput(input.value);
        this.setFieldError(`inputs[${index}]`, error);
    }

    async submit(event) {
        event.preventDefault();

        if (this.submitting || !this.formState) {
            return;
        }

        const errors = this.validateForm();

        if (Object.keys(errors).length > 0) {
            this.lastValidPayload = null;
            this.formState.setFieldErrors(errors);
            this.formState.focusFirstError();
            this.showBanner({ type: 'error', text: 'Popraw błędy w formularzu i spróbuj ponownie.', autoHide: false });
            return;
        }

        this.formState.setFieldErrors({});
        this.clearBanner();

        const payload = this.buildPayload();
        await this.performSubmission(payload);
    }

    async performSubmission(payload) {
        this.submitting = true;
        this.lastValidPayload = payload;
        this.setSubmitting(true);

        try {
            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            try {
                Object.assign(headers, generateCsrfHeaders(this.formTarget));
            } catch (csrfError) {
                console.warn('CSRF header generation warning', csrfError);
            }

            const response = await fetch('/api/ai/recommendations/generate', {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
            });

            await this.handleSubmitResponse(response);
        } catch (error) {
            console.error('AI recommendations request failed', error);
            this.showBanner({ type: 'error', text: 'Wystąpił błąd. Spróbuj ponownie.', autoHide: true });
        } finally {
            this.submitting = false;
            this.setSubmitting(false);
        }
    }

    async retryLastSubmission() {
        if (!this.lastValidPayload || this.submitting) {
            return;
        }

        await this.performSubmission(this.lastValidPayload);
    }

    async handleSubmitResponse(response) {
        if (response.status === 201) {
            let payload;

            try {
                payload = await response.json();
            } catch (error) {
                console.warn('Unexpected empty response body', error);
            }

            const eventId = payload?.id ?? payload?.data?.id;

            if (eventId !== undefined && eventId !== null) {
                const eventIdString = String(eventId);
                window.location.assign(`/ai/recommendations/${eventIdString}`);
                return;
            }

            window.location.assign('/ai/recommendations');
            return;
        }

        if (response.status === 401 || response.status === 403) {
            window.location.assign('/auth/login');
            return;
        }

        if (response.status === 422) {
            this.lastValidPayload = null;
            let problem = {};

            try {
                problem = await response.json();
            } catch (error) {
                console.warn('Failed to parse validation error response', error);
            }

            const errors = this.mapProblemErrors(problem);
            this.markFieldsAsTouched(Object.keys(errors));
            this.formState.setFieldErrors(errors);
            this.formState.focusFirstError();
            this.showBanner({ type: 'error', text: 'Popraw błędy w formularzu i spróbuj ponownie.', autoHide: false });
            return;
        }

        if (response.status === 502) {
            this.showBanner({
                type: 'error',
                text: 'Nie udało się wygenerować rekomendacji. Spróbuj ponownie.',
                action: {
                    label: 'Spróbuj ponownie',
                    handler: () => this.retryLastSubmission(),
                },
                autoHide: false,
            });
            return;
        }

        if (response.status === 504) {
            this.showBanner({
                type: 'warning',
                text: 'Generowanie rekomendacji trwa dłużej niż zwykle. Spróbuj ponownie.',
                action: {
                    label: 'Spróbuj ponownie',
                    handler: () => this.retryLastSubmission(),
                },
                autoHide: false,
            });
            return;
        }

        if (!response.ok) {
            this.lastValidPayload = null;
            this.showBanner({ type: 'error', text: `Wystąpił błąd (status ${response.status}). Spróbuj ponownie.`, autoHide: true });
            return;
        }

        window.location.assign('/ai/recommendations');
    }

    mapProblemErrors(problem) {
        const errors = {};

        if (problem && typeof problem === 'object') {
            const problemErrors = problem.errors;

            if (problemErrors && typeof problemErrors === 'object') {
                Object.entries(problemErrors).forEach(([field, messages]) => {
                    if (!Array.isArray(messages) || messages.length === 0) {
                        return;
                    }

                    errors[field] = messages.map((message) => String(message));
                });
            }

            if (Array.isArray(problem.violations)) {
                problem.violations.forEach(({ propertyPath, message }) => {
                    if (!propertyPath || !message) {
                        return;
                    }

                    if (!errors[propertyPath]) {
                        errors[propertyPath] = [];
                    }

                    errors[propertyPath].push(String(message));
                });
            }
        }

        return errors;
    }

    markFieldsAsTouched(fields) {
        fields.forEach((field) => {
            const match = field.match(/^inputs\[(\d+)]$/);

            if (!match) {
                return;
            }

            const index = Number.parseInt(match[1], 10);

            if (!Number.isNaN(index) && index >= 0 && index < this.touched.length) {
                this.touched[index] = true;
            }
        });
    }

    validateForm() {
        const errors = {};
        const trimmedInputs = this.vm.inputs.map((value) => value.trim());
        const nonEmpty = trimmedInputs.filter((value) => value.length > 0);

        if (nonEmpty.length === 0) {
            errors.inputs = ['Podaj co najmniej jeden tytuł lub autora.'];
        }

        trimmedInputs.forEach((value, index) => {
            const error = this.validateSingleInput(value);

            if (error) {
                errors[`inputs[${index}]`] = [error];
            }
        });

        return errors;
    }

    validateSingleInput(value) {
        const normalized = value.trim();

        if (!normalized) {
            return null;
        }

        if (normalized.length < 1 || normalized.length > maxInputLength) {
            return 'Wartość musi mieć od 1 do 255 znaków.';
        }

        return null;
    }

    buildPayload() {
        const inputs = this.vm.inputs
            .map((value) => value.trim())
            .filter((value) => value.length > 0);

        const payload = {
            inputs,
        };

        const modelValue = this.vm.model?.trim();

        if (modelValue && modelValue.length <= 191) {
            payload.model = modelValue;
        }

        return payload;
    }

    setSubmitting(value) {
        if (this.formState) {
            this.formState.setSubmitting(value);
        }

        if (!this.hasSubmitTarget) {
            return;
        }

        if (value) {
            this.submitTarget.textContent = submittingLabel;
        } else {
            this.submitTarget.textContent = defaultSubmitLabel;
        }
    }

    showBanner(banner) {
        if (!this.hasBannerTarget) {
            return;
        }

        if (!banner) {
            this.bannerState = null;
            this.renderBanner();
            return;
        }

        this.bannerState = {
            type: banner.type ?? 'info',
            text: banner.text ?? '',
            action: banner.action ?? null,
            autoHide: banner.autoHide !== false,
        };

        this.renderBanner();
    }

    clearBanner() {
        this.showBanner(null);
    }

    renderBanner() {
        this.clearBannerTimeout();

        const container = this.bannerTarget;
        container.innerHTML = '';

        if (!this.bannerState) {
            container.hidden = true;
            return;
        }

        container.hidden = false;

        const banner = document.createElement('div');
        banner.className = `banner banner--${this.bannerState.type}`;
        banner.setAttribute('role', 'alert');
        banner.tabIndex = -1;

        const message = document.createElement('span');
        message.className = 'banner__message';
        message.textContent = this.bannerState.text;
        banner.appendChild(message);

        if (this.bannerState.action?.label && typeof this.bannerState.action.handler === 'function') {
            const actionButton = document.createElement('button');
            actionButton.type = 'button';
            actionButton.className = 'button button--ghost banner__action';
            actionButton.textContent = this.bannerState.action.label;
            actionButton.addEventListener('click', () => {
                this.bannerState.action.handler();
            });
            banner.appendChild(actionButton);
        }

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'banner__close';
        closeButton.setAttribute('aria-label', 'Zamknij komunikat');
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', () => {
            this.clearBanner();
        });
        banner.appendChild(closeButton);

        container.appendChild(banner);
        banner.focus();

        if (this.bannerState.autoHide) {
            this.bannerTimeout = window.setTimeout(() => {
                this.clearBanner();
            }, bannerTimeoutMs);
        }
    }

    clearBannerTimeout() {
        if (this.bannerTimeout) {
            window.clearTimeout(this.bannerTimeout);
            this.bannerTimeout = null;
        }
    }

    setFieldError(fieldName, message) {
        if (!this.formState) {
            return;
        }

        const current = { ...(this.formState.state.fieldErrors ?? {}) };

        if (message) {
            current[fieldName] = [message];
        } else {
            delete current[fieldName];
        }

        this.formState.setFieldErrors(current);
    }

    anyNonEmptyInput() {
        return this.vm.inputs.some((value) => value.trim().length > 0);
    }

    focusLastInput() {
        if (!this.hasInputsContainerTarget) {
            return;
        }

        const lastInput = this.inputsContainerTarget.querySelector('input[name="inputs[]"]:last-of-type');

        if (lastInput instanceof HTMLElement) {
            lastInput.focus({ preventScroll: false });
        }
    }

    resolveEventIndex(event) {
        const indexParam = event.params?.index;

        if (typeof indexParam === 'number') {
            return indexParam;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return null;
        }

        const datasetValue = target.getAttribute('data-ai-recommendations-form-index-param');

        if (datasetValue === null) {
            return null;
        }

        const parsed = Number.parseInt(datasetValue, 10);

        if (Number.isNaN(parsed)) {
            return null;
        }

        return parsed;
    }
}


