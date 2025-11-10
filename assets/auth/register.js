import { createFormState } from './form-state.js';
import { postJson } from './api.js';

const INIT_EVENTS = ['DOMContentLoaded', 'turbo:load'];

INIT_EVENTS.forEach((eventName) => {
    document.addEventListener(eventName, bootstrapRegister);
});

if (document.readyState !== 'loading') {
    bootstrapRegister();
}

function bootstrapRegister() {
    const form = document.querySelector('#register-form');

    if (!form || form.dataset.initialized === 'true') {
        return;
    }

    form.dataset.initialized = 'true';
    initRegisterForm(form);
}

function initRegisterForm(formElement) {
    const viewRoot = document.querySelector('[data-view="auth-register"]');
    const banner = viewRoot?.querySelector('[data-view="banner"]');
    const fieldSummary = viewRoot?.querySelector('[data-view="field-summary"]');
    const fieldSummaryList = viewRoot?.querySelector('[data-view="field-summary-list"]');
    const submitButton = formElement.querySelector('[data-view="submit"]');

    const emailInput = formElement.querySelector('[data-view="input-email"]');
    const passwordInput = formElement.querySelector('[data-view="input-password"]');
    const passwordConfirmInput = formElement.querySelector('[data-view="input-password-confirm"]');

    const emailError = formElement.querySelector('[data-view="error-email"]');
    const passwordError = formElement.querySelector('[data-view="error-password"]');
    const passwordConfirmError = formElement.querySelector('[data-view="error-password-confirm"]');

    const formState = createFormState({
        form: formElement,
        submitButton,
        banner,
        fieldSummary,
        fieldSummaryList,
        fields: {
            email: { input: emailInput, messageElement: emailError },
            password: { input: passwordInput, messageElement: passwordError },
            passwordConfirm: { input: passwordConfirmInput, messageElement: passwordConfirmError },
        },
    });

    attachValidator(emailInput, 'email', validateEmail, formState);
    attachValidator(passwordInput, 'password', validatePassword, formState);
    attachValidator(passwordConfirmInput, 'passwordConfirm', (value) => validatePasswordConfirm(value, () => passwordInput.value), formState);

    formElement.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(formElement);
        const payload = {
            email: String(formData.get('email') ?? '').trim(),
            password: String(formData.get('password') ?? ''),
            passwordConfirm: String(formData.get('passwordConfirm') ?? ''),
        };

        const validationErrors = validateRegister(payload);

        if (Object.keys(validationErrors).length) {
            formState.setBanner(undefined);
            formState.setFieldErrors(validationErrors);
            formState.focusFirstError();
            return;
        }

        formState.setSubmitting(true);
        formState.setBanner(undefined);
        formState.setFieldErrors({});

        try {
            const { response, json } = await postJson('/api/auth/register', payload);

            if (response.ok) {
                window.location.assign('/books');
                return;
            }

            handleFailure(response, json, formState);
        } catch (error) {
            console.error('Register request failed', error);
            formState.setBanner({
                message: 'Wystąpił błąd. Spróbuj ponownie.',
                variant: 'error',
            });
        } finally {
            formState.setSubmitting(false);
        }
    });
}

function attachValidator(input, fieldName, validator, formState) {
    if (!input) {
        return;
    }

    let touched = false;

    const runValidation = () => {
        const error = validator(input.value.trim(), input);

        if (error) {
            formState.setFieldErrors({
                ...formState.state.fieldErrors,
                [fieldName]: [error],
            }, { focusSummary: false });
        } else {
            formState.clearFieldError(fieldName);
        }
    };

    input.addEventListener('input', () => {
        if (!touched) {
            formState.clearFieldError(fieldName);
            return;
        }

        runValidation();
    });

    input.addEventListener('blur', () => {
        touched = true;
        runValidation();
    });
}

function validateRegister(payload) {
    const errors = {};

    const emailError = validateEmail(payload.email);
    if (emailError) {
        errors.email = [emailError];
    }

    const passwordError = validatePassword(payload.password);
    if (passwordError) {
        errors.password = [passwordError];
    }

    const passwordConfirmError = validatePasswordConfirm(payload.passwordConfirm, () => payload.password);
    if (passwordConfirmError) {
        errors.passwordConfirm = [passwordConfirmError];
    }

    return errors;
}

function validateEmail(value) {
    if (!value) {
        return 'Email jest wymagany.';
    }

    if (value.length < 3 || value.length > 255) {
        return 'Email musi mieć od 3 do 255 znaków.';
    }

    if (!/.+@.+\..+/.test(value)) {
        return 'Podaj poprawny adres email.';
    }

    return undefined;
}

function validatePassword(value) {
    if (!value) {
        return 'Hasło jest wymagane.';
    }

    if (value.length < 8) {
        return 'Hasło musi mieć co najmniej 8 znaków.';
    }

    return undefined;
}

function validatePasswordConfirm(value, passwordGetter) {
    const password = passwordGetter();

    if (!value) {
        return 'Potwierdzenie hasła jest wymagane.';
    }

    if (value !== password) {
        return 'Hasła muszą być identyczne.';
    }

    return undefined;
}

function handleFailure(response, json, formState) {
    switch (response.status) {
        case 409:
            formState.setBanner({
                message: 'Ten email jest już zarejestrowany.',
                variant: 'error',
            });
            formState.focusField('email');
            break;
        case 422: {
            const errors = mapViolations(json?.violations ?? []);
            formState.setFieldErrors(errors);
            formState.focusFirstError();
            break;
        }
        case 429:
            formState.setBanner({
                message: 'Zbyt wiele prób. Spróbuj ponownie za chwilę.',
                variant: 'error',
            });
            break;
        default:
            formState.setBanner({
                message: 'Wystąpił błąd. Spróbuj ponownie.',
                variant: 'error',
            });
    }
}

function mapViolations(violations) {
    const fieldErrors = {};

    violations.forEach(({ propertyPath, message }) => {
        if (!propertyPath || !message) {
            return;
        }

        if (!fieldErrors[propertyPath]) {
            fieldErrors[propertyPath] = [];
        }

        fieldErrors[propertyPath].push(message);
    });

    return fieldErrors;
}

