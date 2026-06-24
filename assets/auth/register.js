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
    const libraryNameInput = formElement.querySelector('[data-view="input-library-name"]');
    const libraryPasswordInput = formElement.querySelector('[data-view="input-library-password"]');

    const emailError = formElement.querySelector('[data-view="error-email"]');
    const passwordError = formElement.querySelector('[data-view="error-password"]');
    const passwordConfirmError = formElement.querySelector('[data-view="error-password-confirm"]');
    const libraryNameError = formElement.querySelector('[data-view="error-libraryName"]');
    const libraryPasswordError = formElement.querySelector('[data-view="error-libraryPassword"]');

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
            libraryName: { input: libraryNameInput, messageElement: libraryNameError },
            libraryPassword: { input: libraryPasswordInput, messageElement: libraryPasswordError },
        },
    });

    attachValidator(emailInput, 'email', validateEmail, formState);
    attachValidator(passwordInput, 'password', validatePassword, formState);
    attachValidator(passwordConfirmInput, 'passwordConfirm', (value) => validatePasswordConfirm(value, () => passwordInput.value), formState);
    attachValidator(libraryNameInput, 'libraryName', validateLibraryName, formState);
    attachValidator(libraryPasswordInput, 'libraryPassword', validateLibraryPassword, formState);

    formElement.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(formElement);
        const payload = {
            email: String(formData.get('email') ?? '').trim(),
            password: String(formData.get('password') ?? ''),
            passwordConfirm: String(formData.get('passwordConfirm') ?? ''),
            libraryName: String(formData.get('libraryName') ?? '').trim(),
            libraryPassword: String(formData.get('libraryPassword') ?? ''),
            libraryMode: String(formData.get('libraryMode') ?? 'create'),
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

    const libraryNameError = validateLibraryName(payload.libraryName);
    if (libraryNameError) {
        errors.libraryName = [libraryNameError];
    }

    const libraryPasswordError = validateLibraryPassword(payload.libraryPassword);
    if (libraryPasswordError) {
        errors.libraryPassword = [libraryPasswordError];
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

function validateLibraryName(value) {
    if (!value) {
        return 'Nazwa biblioteki jest wymagana.';
    }

    if (value.length > 255) {
        return 'Nazwa biblioteki może mieć maksymalnie 255 znaków.';
    }

    return undefined;
}

function validateLibraryPassword(value) {
    if (!value) {
        return 'Hasło biblioteki jest wymagane.';
    }

    if (value.length < 8) {
        return 'Hasło biblioteki musi mieć co najmniej 8 znaków.';
    }

    return undefined;
}

function handleFailure(response, json, formState) {
    const problemType = json?.type ?? '';

    switch (response.status) {
        case 409:
            formState.setBanner({
                message: 'Ten email jest już zarejestrowany.',
                variant: 'error',
            });
            formState.focusField('email');
            break;
        case 422: {
            if (problemType.includes('library-conflict')) {
                formState.setFieldErrors({
                    libraryName: [json?.detail ?? 'Biblioteka o takiej nazwie już istnieje.'],
                });
                formState.focusField('libraryName');
                break;
            }

            if (problemType.includes('library-not-found')) {
                formState.setFieldErrors({
                    libraryName: ['Biblioteka o podanej nazwie nie istnieje.'],
                });
                formState.focusField('libraryName');
                break;
            }

            if (problemType.includes('invalid-library-password')) {
                formState.setFieldErrors({
                    libraryPassword: ['Nieprawidłowe hasło biblioteki.'],
                });
                formState.focusField('libraryPassword');
                break;
            }

            const errors = json?.errors ?? mapViolations(json?.violations ?? []);
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

