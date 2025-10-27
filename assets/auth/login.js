import { createFormState } from './form-state.js';
import { postJson } from './api.js';

const INIT_EVENT = 'DOMContentLoaded';

if (document.readyState === 'loading') {
    document.addEventListener(INIT_EVENT, bootstrapLogin, { once: true });
} else {
    bootstrapLogin();
}

function bootstrapLogin() {
    const form = document.querySelector('#login-form');

    if (form) {
        initLoginForm(form);
    }
}

function initLoginForm(formElement) {
    const banner = document.querySelector('[data-view="auth-login"] [data-view="banner"]');
    const submitButton = formElement.querySelector('[data-view="submit"]');
    const emailInput = formElement.querySelector('[data-view="input-email"]');
    const passwordInput = formElement.querySelector('[data-view="input-password"]');
    const emailError = formElement.querySelector('[data-view="error-email"]');
    const passwordError = formElement.querySelector('[data-view="error-password"]');
    const fieldSummary = document.querySelector('[data-view="auth-login"] [data-view="field-summary"]');
    const fieldSummaryList = document.querySelector('[data-view="auth-login"] [data-view="field-summary-list"]');

    const formState = createFormState({
        form: formElement,
        submitButton,
        banner,
        fieldSummary,
        fieldSummaryList,
        fields: {
            email: {
                input: emailInput,
                messageElement: emailError,
            },
            password: {
                input: passwordInput,
                messageElement: passwordError,
            },
        },
    });

    attachLiveValidation(emailInput, 'email', validateEmail, formState);
    attachLiveValidation(passwordInput, 'password', validatePassword, formState);

    formElement.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(formElement);

        const payload = {
            email: String(formData.get('email') ?? ''),
            password: String(formData.get('password') ?? ''),
        };

        const validationErrors = validateLogin(payload);

        if (Object.keys(validationErrors).length) {
            formState.setFieldErrors(validationErrors);
            formState.setBanner(undefined);
            formState.focusFirstError();
            return;
        }

        formState.setSubmitting(true);
        formState.setBanner(undefined);
        formState.setFieldErrors({});

        try {
            const { response, json } = await postJson('/api/auth/login', payload);

            if (response.ok) {
                window.location.assign('/books');
                return;
            }

            handleFailure(response, json, formState);
        } catch (error) {
            console.error('Login request failed', error);
            formState.setBanner({
                message: 'Wystąpił błąd. Spróbuj ponownie.',
                variant: 'error',
            });
        } finally {
            formState.setSubmitting(false);
        }
    });
}

function attachLiveValidation(input, fieldName, validator, formState) {
    if (!input) {
        return;
    }

    let touched = false;

    const runValidation = () => {
        const value = input.value.trim();
        const error = validator(value);

        if (error) {
            formState.setFieldErrors({
                ...formState.state.fieldErrors,
                [fieldName]: [error],
            });
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

function validateEmail(value) {
    if (!value) {
        return 'Email jest wymagany.';
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

    return undefined;
}

function validateLogin(payload) {
    const errors = {};

    const emailError = validateEmail(payload.email.trim());
    if (emailError) {
        errors.email = [emailError];
    }

    const passwordError = validatePassword(payload.password.trim());
    if (passwordError) {
        errors.password = [passwordError];
    }

    return errors;
}

function handleFailure(response, json, formState) {
    switch (response.status) {
        case 401:
            formState.setBanner({
                message: 'Nieprawidłowy email lub hasło.',
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

