const hiddenAttribute = 'hidden';

export function createFormState(elements) {
    const {
        form,
        submitButton,
        banner,
        fieldSummary,
        fieldSummaryList,
        fields,
    } = elements;

    const state = {
        submitting: false,
        banner: undefined,
        fieldErrors: {},
    };

    function setSubmitting(value) {
        state.submitting = Boolean(value);

        if (submitButton) {
            submitButton.disabled = state.submitting;
            submitButton.setAttribute('aria-busy', String(state.submitting));
        }

        if (form) {
            form.classList.toggle('is-submitting', state.submitting);
        }
    }

    function setBanner(nextBanner) {
        state.banner = nextBanner ?? undefined;

        if (!banner) {
            return;
        }

        if (!state.banner) {
            banner.textContent = '';
            banner.className = 'banner';
            banner.setAttribute(hiddenAttribute, '');
            return;
        }

        banner.textContent = state.banner.message;
        banner.className = `banner banner--${state.banner.variant}`;
        banner.removeAttribute(hiddenAttribute);
    }

    function setFieldErrors(errorsByField, options = {}) {
        const { focusSummary = true } = options;
        const nextErrors = errorsByField ?? {};

        Object.entries(fields).forEach(([fieldName, field]) => {
            const messages = nextErrors[fieldName] ?? [];

            updateFieldErrorPresentation(field, messages);
        });

        state.fieldErrors = nextErrors;

        updateFieldSummary(nextErrors, { focusSummary });
    }

    function clearFieldError(fieldName) {
        if (!Object.prototype.hasOwnProperty.call(state.fieldErrors, fieldName)) {
            return;
        }

        const nextErrors = { ...state.fieldErrors };
        delete nextErrors[fieldName];
        setFieldErrors(nextErrors, { focusSummary: false });
    }

    function updateFieldErrorPresentation(field, messages) {
        const { input, messageElement } = field;

        if (!input || !messageElement) {
            return;
        }

        if (!messages.length) {
            messageElement.textContent = '';
            messageElement.setAttribute(hiddenAttribute, '');
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
            return;
        }

        const message = messages[0];
        messageElement.textContent = message;
        messageElement.removeAttribute(hiddenAttribute);

        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', messageElement.id);
    }

    function updateFieldSummary(errorsByField, options = {}) {
        const { focusSummary = true } = options;

        if (!fieldSummary || !fieldSummaryList) {
            return;
        }

        const entries = Object.entries(errorsByField ?? {}).filter(([, messages]) => messages.length);

        if (!entries.length) {
            fieldSummaryList.innerHTML = '';
            fieldSummary.setAttribute(hiddenAttribute, '');
            return;
        }

        fieldSummaryList.innerHTML = '';

        entries.forEach(([fieldName, messages]) => {
            const field = fields[fieldName];

            if (!field || !field.input) {
                return;
            }

            const li = document.createElement('li');
            const anchor = document.createElement('a');
            anchor.href = `#${field.input.id}`;
            anchor.textContent = messages[0];
            anchor.addEventListener('click', (event) => {
                event.preventDefault();
                field.input.focus({ preventScroll: false });
            });
            li.appendChild(anchor);
            fieldSummaryList.appendChild(li);
        });

        fieldSummary.removeAttribute(hiddenAttribute);

        if (focusSummary) {
            setTimeout(() => {
                fieldSummary.focus();
            }, 0);
        }
    }

    function focusField(fieldName) {
        const field = fields[fieldName];

        if (field && field.input) {
            field.input.focus();
        }
    }

    function focusFirstError() {
        const first = Object.keys(state.fieldErrors)[0];

        if (first) {
            focusField(first);
        }
    }

    return {
        get state() {
            return { ...state };
        },
        setSubmitting,
        setBanner,
        setFieldErrors,
        clearFieldError,
        focusField,
        focusFirstError,
    };
}

