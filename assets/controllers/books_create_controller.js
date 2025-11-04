import { Controller } from '@hotwired/stimulus';
import { generateCsrfHeaders } from './csrf_protection_controller.js';
import { createFormState } from '../auth/form-state.js';

const bannerTimeoutMs = 6000;
const successRedirectUrl = '/books?notice=book-created';

export default class extends Controller {
    static targets = [
        'banner',
        'fieldSummary',
        'fieldSummaryList',
        'form',
        'submit',
        'titleInput',
        'titleError',
        'authorInput',
        'authorError',
        'shelfSelect',
        'shelfError',
        'genresContainer',
        'genresError',
        'isbnInput',
        'isbnError',
        'pageCountInput',
        'pageCountError',
    ];

    connect() {
        this.bannerState = null;
        this.bannerTimeout = null;
        this.loadingShelves = false;
        this.loadingGenres = false;
        this.submitting = false;

        this.vm = {
            shelves: [],
            genres: [],
            selectedShelfId: '',
            selectedGenreIds: [],
        };

        this.touchedFields = {
            title: false,
            author: false,
            shelfId: false,
            genreIds: false,
            isbn: false,
            pageCount: false,
        };

        this.initialiseFormState();
        this.prepareGenresContainer();
        this.showInitialLoadingState();
        this.loadOptions();
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
            fields: {
                title: {
                    input: this.hasTitleInputTarget ? this.titleInputTarget : null,
                    messageElement: this.hasTitleErrorTarget ? this.titleErrorTarget : null,
                },
                author: {
                    input: this.hasAuthorInputTarget ? this.authorInputTarget : null,
                    messageElement: this.hasAuthorErrorTarget ? this.authorErrorTarget : null,
                },
                shelfId: {
                    input: this.hasShelfSelectTarget ? this.shelfSelectTarget : null,
                    messageElement: this.hasShelfErrorTarget ? this.shelfErrorTarget : null,
                },
                genreIds: {
                    input: this.hasGenresContainerTarget ? this.genresContainerTarget : null,
                    messageElement: this.hasGenresErrorTarget ? this.genresErrorTarget : null,
                },
                isbn: {
                    input: this.hasIsbnInputTarget ? this.isbnInputTarget : null,
                    messageElement: this.hasIsbnErrorTarget ? this.isbnErrorTarget : null,
                },
                pageCount: {
                    input: this.hasPageCountInputTarget ? this.pageCountInputTarget : null,
                    messageElement: this.hasPageCountErrorTarget ? this.pageCountErrorTarget : null,
                },
            },
        });

        const originalFocusField = this.formState.focusField;
        this.formState.focusField = (fieldName) => {
            if (fieldName === 'genreIds') {
                this.focusFirstGenreCheckbox();
                return;
            }

            originalFocusField.call(this.formState, fieldName);
        };

        const originalFocusFirstError = this.formState.focusFirstError;
        this.formState.focusFirstError = () => {
            const [firstError] = Object.keys(this.formState.state.fieldErrors ?? {});

            if (firstError === 'genreIds') {
                this.focusFirstGenreCheckbox();
                return;
            }

            originalFocusFirstError.call(this.formState);
        };
    }

    prepareGenresContainer() {
        if (!this.hasGenresContainerTarget) {
            return;
        }

        if (!this.genresContainerTarget.hasAttribute('tabindex')) {
            this.genresContainerTarget.setAttribute('tabindex', '-1');
        }
    }

    showInitialLoadingState() {
        if (this.hasShelfSelectTarget) {
            this.renderShelvesLoading();
        }

        if (this.hasGenresContainerTarget) {
            this.renderGenresLoading();
        }
    }

    async loadOptions({ showErrorBanner = true, preserveBanner = false } = {}) {
        if (!this.hasShelfSelectTarget || !this.hasGenresContainerTarget) {
            return;
        }

        this.loadingShelves = true;
        this.loadingGenres = true;

        this.vm.selectedShelfId = this.shelfSelectTarget.value || '';
        this.vm.selectedGenreIds = this.getSelectedGenreIds();

        this.renderShelvesLoading();
        this.renderGenresLoading();

        try {
            const [shelves, genres] = await Promise.all([
                this.fetchShelves(),
                this.fetchGenres(),
            ]);

            this.vm.shelves = shelves;
            this.vm.genres = genres;

            this.renderShelves(shelves);
            this.renderGenres(genres);

            if (this.vm.selectedShelfId && !shelves.some((shelf) => shelf.id === this.vm.selectedShelfId)) {
                this.vm.selectedShelfId = '';
                this.shelfSelectTarget.value = '';
            }

            const validGenreIds = new Set(genres.map((genre) => genre.id));
            this.vm.selectedGenreIds = this.vm.selectedGenreIds.filter((id) => validGenreIds.has(id));
            this.syncGenreSelection();

            const currentFieldErrors = this.formState?.state?.fieldErrors ?? {};

            if (this.formState && !currentFieldErrors.genreIds) {
                this.formState.clearFieldError('genreIds');
            }

            if (!preserveBanner) {
                this.clearBanner();
            }
        } catch (error) {
            console.error('Failed to load book form options', error);

            if (showErrorBanner) {
                this.showBanner({
                    type: 'error',
                    text: 'Nie udało się załadować list regałów i gatunków. Spróbuj ponownie.',
                    action: {
                        label: 'Spróbuj ponownie',
                        handler: () => {
                            this.showBanner(null);
                            this.loadOptions({ showErrorBanner: true });
                        },
                    },
                    autoHide: false,
                });
            }
        } finally {
            this.loadingShelves = false;
            this.loadingGenres = false;
        }
    }

    async fetchShelves() {
        const response = await fetch('/api/shelves', {
            headers: { Accept: 'application/json' },
        });

        if (response.status === 401 || response.status === 403) {
            window.location.assign('/auth/login');
            return [];
        }

        if (!response.ok) {
            throw new Error(`Shelves request failed with status ${response.status}`);
        }

        const payload = await response.json();
        const raw = Array.isArray(payload?.data) ? payload.data : [];

        return raw
            .filter((item) => typeof item?.id === 'string' && typeof item?.name === 'string')
            .map((item) => ({
                id: item.id,
                name: item.name,
                isSystem: Boolean(item?.isSystem),
            }));
    }

    async fetchGenres() {
        const response = await fetch('/api/genres', {
            headers: { Accept: 'application/json' },
        });

        if (response.status === 401 || response.status === 403) {
            window.location.assign('/auth/login');
            return [];
        }

        if (!response.ok) {
            throw new Error(`Genres request failed with status ${response.status}`);
        }

        const payload = await response.json();
        const raw = Array.isArray(payload?.data) ? payload.data : [];

        return raw
            .filter((item) => typeof item?.id === 'number' && typeof item?.name === 'string')
            .map((item) => ({
                id: item.id,
                name: item.name,
            }));
    }

    renderShelvesLoading() {
        if (!this.hasShelfSelectTarget) {
            return;
        }

        this.shelfSelectTarget.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Ładowanie…';
        placeholder.disabled = true;
        placeholder.selected = true;
        this.shelfSelectTarget.appendChild(placeholder);
        this.shelfSelectTarget.disabled = true;
    }

    renderGenresLoading() {
        if (!this.hasGenresContainerTarget) {
            return;
        }

        this.genresContainerTarget.innerHTML = '';
        const loading = document.createElement('p');
        loading.className = 'books-create-view__loading';
        loading.textContent = 'Ładowanie gatunków…';
        this.genresContainerTarget.appendChild(loading);
    }

    renderShelves(shelves) {
        if (!this.hasShelfSelectTarget) {
            return;
        }

        this.shelfSelectTarget.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Wybierz regał';
        placeholder.disabled = true;
        placeholder.selected = !this.vm.selectedShelfId;
        this.shelfSelectTarget.appendChild(placeholder);

        shelves.forEach((shelf) => {
            const option = document.createElement('option');
            option.value = shelf.id;
            option.textContent = shelf.name;

            if (shelf.id === this.vm.selectedShelfId) {
                option.selected = true;
            }

            this.shelfSelectTarget.appendChild(option);
        });

        this.shelfSelectTarget.disabled = shelves.length === 0;
    }

    renderGenres(genres) {
        if (!this.hasGenresContainerTarget) {
            return;
        }

        this.genresContainerTarget.innerHTML = '';
        this.genresContainerTarget.classList.add('checkbox-group');

        if (genres.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'books-create-view__empty';
            empty.textContent = 'Brak dostępnych gatunków.';
            this.genresContainerTarget.appendChild(empty);
            return;
        }

        genres.forEach((genre) => {
            const label = document.createElement('label');
            label.className = 'checkbox';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'genreIds[]';
            checkbox.value = String(genre.id);
            checkbox.dataset.action = 'change->books-create#onGenreChange';

            if (this.vm.selectedGenreIds.includes(genre.id)) {
                checkbox.checked = true;
            }

            const text = document.createElement('span');
            text.textContent = genre.name;

            label.appendChild(checkbox);
            label.appendChild(text);
            this.genresContainerTarget.appendChild(label);
        });
    }

    syncGenreSelection() {
        if (!this.hasGenresContainerTarget) {
            return;
        }

        const selectedIds = new Set(this.vm.selectedGenreIds);
        const checkboxes = this.genresContainerTarget.querySelectorAll('input[type="checkbox"][name="genreIds[]"]');

        checkboxes.forEach((checkbox) => {
            const value = Number.parseInt(checkbox.value, 10);
            checkbox.checked = selectedIds.has(value);
        });
    }

    onTitleInput() {
        if (!this.formState) {
            return;
        }

        if (!this.touchedFields.title) {
            this.formState.clearFieldError('title');
            return;
        }

        this.setFieldError('title', this.validateTitle(this.titleInputTarget.value));
    }

    onTitleBlur() {
        if (!this.formState) {
            return;
        }

        this.touchedFields.title = true;
        this.setFieldError('title', this.validateTitle(this.titleInputTarget.value));
    }

    onAuthorInput() {
        if (!this.formState) {
            return;
        }

        if (!this.touchedFields.author) {
            this.formState.clearFieldError('author');
            return;
        }

        this.setFieldError('author', this.validateAuthor(this.authorInputTarget.value));
    }

    onAuthorBlur() {
        if (!this.formState) {
            return;
        }

        this.touchedFields.author = true;
        this.setFieldError('author', this.validateAuthor(this.authorInputTarget.value));
    }

    onShelfChange() {
        if (!this.formState) {
            return;
        }

        this.touchedFields.shelfId = true;
        this.vm.selectedShelfId = this.shelfSelectTarget.value;
        this.setFieldError('shelfId', this.validateShelf(this.shelfSelectTarget.value));
    }

    onGenreChange(event) {
        const checkbox = event.target;

        if (!(checkbox instanceof HTMLInputElement)) {
            return;
        }

        this.touchedFields.genreIds = true;

        const selectedIds = this.getSelectedGenreIds();

        if (checkbox.checked && selectedIds.length > 3) {
            checkbox.checked = false;
            this.showGenreSelectionError('Możesz wybrać maksymalnie 3 gatunki.');
            return;
        }

        this.vm.selectedGenreIds = selectedIds;

        const error = this.validateGenres(selectedIds);

        if (error) {
            this.setFieldError('genreIds', error);
        } else {
            this.formState.clearFieldError('genreIds');
        }
    }

    onIsbnInput() {
        if (!this.formState) {
            return;
        }

        if (!this.touchedFields.isbn) {
            this.formState.clearFieldError('isbn');
            return;
        }

        this.setFieldError('isbn', this.validateIsbn(this.isbnInputTarget.value));
    }

    onIsbnBlur() {
        if (!this.formState) {
            return;
        }

        this.touchedFields.isbn = true;
        this.setFieldError('isbn', this.validateIsbn(this.isbnInputTarget.value));
    }

    onPageCountInput() {
        if (!this.formState) {
            return;
        }

        if (!this.touchedFields.pageCount) {
            this.formState.clearFieldError('pageCount');
            return;
        }

        this.setFieldError('pageCount', this.validatePageCount(this.pageCountInputTarget.value));
    }

    onPageCountBlur() {
        if (!this.formState) {
            return;
        }

        this.touchedFields.pageCount = true;
        this.setFieldError('pageCount', this.validatePageCount(this.pageCountInputTarget.value));
    }

    async submit(event) {
        event.preventDefault();

        if (this.submitting || !this.formState) {
            return;
        }

        const titleValue = this.hasTitleInputTarget ? this.titleInputTarget.value : '';
        const authorValue = this.hasAuthorInputTarget ? this.authorInputTarget.value : '';
        const shelfValue = this.hasShelfSelectTarget ? this.shelfSelectTarget.value : '';
        const genreIds = this.getSelectedGenreIds();
        const isbnValue = this.hasIsbnInputTarget ? this.isbnInputTarget.value : '';
        const pageCountValue = this.hasPageCountInputTarget ? this.pageCountInputTarget.value : '';

        const validationErrors = this.validateForm({
            title: titleValue,
            author: authorValue,
            shelfId: shelfValue,
            genreIds,
            isbn: isbnValue,
            pageCount: pageCountValue,
        });

        if (Object.keys(validationErrors).length > 0) {
            this.formState.setFieldErrors(validationErrors);
            this.formState.focusFirstError();
            this.showBanner({ type: 'error', text: 'Popraw błędy w formularzu i spróbuj ponownie.', autoHide: false });
            return;
        }

        this.formState.setFieldErrors({});
        this.showBanner(null);

        const payload = this.buildPayload({
            title: titleValue,
            author: authorValue,
            shelfId: shelfValue,
            genreIds,
            isbn: isbnValue,
            pageCount: pageCountValue,
        });

        this.submitting = true;
        this.formState.setSubmitting(true);

        try {
            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            try {
                generateCsrfHeaders(this.formTarget);
            } catch (csrfError) {
                console.warn('CSRF header generation warning', csrfError);
            }

            Object.assign(headers, generateCsrfHeaders(this.formTarget));

            const response = await fetch('/api/books', {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
            });

            await this.handleSubmitResponse(response);
        } catch (error) {
            console.error('Failed to create book', error);
            this.showBanner({ type: 'error', text: 'Wystąpił błąd. Spróbuj ponownie.' });
        } finally {
            this.submitting = false;
            this.formState.setSubmitting(false);
        }
    }

    async handleSubmitResponse(response) {
        if (response.status === 201) {
            window.location.assign(successRedirectUrl);
            return;
        }

        if (response.status === 401 || response.status === 403) {
            window.location.assign('/auth/login');
            return;
        }

        if (response.status === 404) {
            this.showBanner({
                type: 'error',
                text: 'Nie znaleziono wybranego regału lub gatunku. Odśwież listy i spróbuj ponownie.',
                action: {
                    label: 'Odśwież listy',
                    handler: () => this.loadOptions({ showErrorBanner: false, preserveBanner: true }),
                },
                autoHide: false,
            });

            await this.loadOptions({ showErrorBanner: false, preserveBanner: true });
            return;
        }

        if (response.status === 422) {
            let payload;

            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            const errors = this.mapProblemErrors(payload);
            this.formState.setFieldErrors(errors);
            this.formState.focusFirstError();
            this.showBanner({ type: 'error', text: 'Popraw błędy w formularzu i spróbuj ponownie.', autoHide: false });
            return;
        }

        if (!response.ok) {
            throw new Error(`Unexpected response (${response.status})`);
        }

        window.location.assign(successRedirectUrl);
    }

    buildPayload(values) {
        const trimmedTitle = values.title.trim();
        const trimmedAuthor = values.author.trim();
        const trimmedIsbn = values.isbn.trim();
        const digitsIsbn = trimmedIsbn.replace(/[^0-9]/g, '');
        const isbn = digitsIsbn.length ? digitsIsbn : null;

        const pageCountTrimmed = values.pageCount.trim();
        const pageCount = pageCountTrimmed ? Number.parseInt(pageCountTrimmed, 10) : null;

        return {
            title: trimmedTitle,
            author: trimmedAuthor,
            shelfId: values.shelfId,
            genreIds: values.genreIds,
            isbn,
            pageCount: Number.isInteger(pageCount) ? pageCount : null,
        };
    }

    validateForm(values) {
        const errors = {};

        const titleError = this.validateTitle(values.title);
        if (titleError) {
            errors.title = [titleError];
        }

        const authorError = this.validateAuthor(values.author);
        if (authorError) {
            errors.author = [authorError];
        }

        const shelfError = this.validateShelf(values.shelfId);
        if (shelfError) {
            errors.shelfId = [shelfError];
        }

        const genreError = this.validateGenres(values.genreIds);
        if (genreError) {
            errors.genreIds = [genreError];
        }

        const isbnError = this.validateIsbn(values.isbn);
        if (isbnError) {
            errors.isbn = [isbnError];
        }

        const pageCountError = this.validatePageCount(values.pageCount);
        if (pageCountError) {
            errors.pageCount = [pageCountError];
        }

        return errors;
    }

    validateTitle(value) {
        const normalized = value.trim();

        if (!normalized) {
            return 'Tytuł jest wymagany.';
        }

        if (normalized.length < 1 || normalized.length > 255) {
            return 'Tytuł musi mieć od 1 do 255 znaków.';
        }

        return null;
    }

    validateAuthor(value) {
        const normalized = value.trim();

        if (!normalized) {
            return 'Autor jest wymagany.';
        }

        if (normalized.length < 1 || normalized.length > 255) {
            return 'Autor musi mieć od 1 do 255 znaków.';
        }

        return null;
    }

    validateShelf(value) {
        const shelfId = value?.trim();

        if (!shelfId) {
            return 'Wybierz regał.';
        }

        if (!this.vm.shelves.some((shelf) => shelf.id === shelfId)) {
            return 'Wybrany regał nie jest dostępny.';
        }

        return null;
    }

    validateGenres(genreIds) {
        const count = Array.isArray(genreIds) ? genreIds.length : 0;

        if (count < 1) {
            return 'Wybierz co najmniej 1 gatunek.';
        }

        if (count > 3) {
            return 'Możesz wybrać maksymalnie 3 gatunki.';
        }

        if (!genreIds.every((id) => this.vm.genres.some((genre) => genre.id === id))) {
            return 'Wybrany gatunek nie jest dostępny.';
        }

        return null;
    }

    validateIsbn(value) {
        const normalized = value.trim();

        if (!normalized) {
            return null;
        }

        const digits = normalized.replace(/[^0-9]/g, '');

        if (digits.length === 0) {
            return 'ISBN może zawierać wyłącznie cyfry.';
        }

        if (digits.length !== 10 && digits.length !== 13) {
            return 'ISBN musi zawierać 10 lub 13 cyfr.';
        }

        return null;
    }

    validatePageCount(value) {
        const normalized = value.trim();

        if (!normalized) {
            return null;
        }

        if (!/^\d+$/.test(normalized)) {
            return 'Liczba stron musi być liczbą całkowitą.';
        }

        const numeric = Number.parseInt(normalized, 10);

        if (Number.isNaN(numeric)) {
            return 'Liczba stron musi być liczbą całkowitą.';
        }

        if (numeric < 1 || numeric > 50000) {
            return 'Liczba stron musi zawierać się w zakresie 1–50000.';
        }

        return null;
    }

    getSelectedGenreIds() {
        if (!this.hasGenresContainerTarget) {
            return [];
        }

        const checkboxes = Array.from(
            this.genresContainerTarget.querySelectorAll('input[type="checkbox"][name="genreIds[]"]'),
        );

        return checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => Number.parseInt(checkbox.value, 10))
            .filter((value) => Number.isInteger(value));
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

    showGenreSelectionError(message) {
        if (!this.formState) {
            return;
        }

        const currentErrors = this.formState.state.fieldErrors ?? {};
        const current = { ...currentErrors, genreIds: [message] };
        this.formState.setFieldErrors(current);
        this.focusFirstGenreCheckbox();
    }

    focusFirstGenreCheckbox() {
        if (!this.hasGenresContainerTarget) {
            return;
        }

        const firstCheckbox = this.genresContainerTarget.querySelector('input[type="checkbox"]');

        if (firstCheckbox instanceof HTMLElement) {
            firstCheckbox.focus({ preventScroll: false });
            return;
        }

        if (this.genresContainerTarget instanceof HTMLElement) {
            this.genresContainerTarget.focus({ preventScroll: false });
        }
    }

    mapProblemErrors(problem) {
        const errors = {};

        const problemErrors = problem?.errors;
        if (problemErrors && typeof problemErrors === 'object') {
            Object.entries(problemErrors).forEach(([field, messages]) => {
                if (!Array.isArray(messages) || messages.length === 0) {
                    return;
                }

                errors[field] = messages.map((message) => String(message));
            });
        }

        if (Array.isArray(problem?.violations)) {
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

        return errors;
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
}


