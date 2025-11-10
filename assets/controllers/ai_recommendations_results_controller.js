import { Controller } from '@hotwired/stimulus';

const ACCEPT_REDIRECT_URL = '/books';
const BOOKS_ENDPOINT = '/api/books';
const GENRES_ENDPOINT = '/api/genres';
const SHELVES_ENDPOINT = '/api/shelves?includeSystem=true';
const ACCEPT_ENDPOINT_TEMPLATE = (eventId) => `/api/ai/recommendations/${eventId}/accept`;
const EVENT_ENDPOINT_TEMPLATE = (eventId) => `/api/ai/recommendations/${eventId}`;

const ACTION_STATES = {
    idle: 'idle',
    accepting: 'accepting',
    accepted: 'accepted',
    error: 'error',
};

const ALERT_TYPES = new Set(['error', 'success', 'info', 'warning']);
const STATUS_MESSAGES = {
    dismissed: 'Propozycja została odrzucona.',
    accepted: 'Propozycja została zaakceptowana.',
    accepting: 'Przetwarzamy akceptację…',
    error: 'Nie udało się zaakceptować propozycji. Spróbuj ponownie.',
};

export default class extends Controller {
    static targets = [
        'loader',
        'content',
        'cardsContainer',
        'emptyState',
        'summary',
        'totalCount',
        'acceptedCount',
        'dismissedCount',
        'statusRegion',
        'bannerTemplate',
        'globalAlertRegion',
        'cardTemplate',
    ];

    static values = {
        eventId: Number,
        preloadedEventJson: String,
        genresCatalogJson: String,
        purchaseShelfId: String,
    };

    connect() {
        this.state = {
            isLoading: false,
            loadError: null,
            event: null,
            genresCatalog: [],
            purchaseShelfId: this.hasPurchaseShelfIdValue ? this.purchaseShelfIdValue : null,
        };

        this.dismissedTempIds = new Set();
        this.acceptedTempIds = new Set();
        this.actionStateByTempId = {};
        this.cardErrorByTempId = {};
        this.proposalsByTempId = new Map();
        this.focusTimer = null;

        this.showLoader(true);
        this.showContent(false);
        this.showEmptyState(false);
        this.toggleLoaderState(true);
        this.showGlobalAlert(null);

        this.focusHeadingSoon();
        this.initialiseData()
            .catch((error) => {
                console.error('Failed to initialise AI recommendations results view', error);
            });
    }

    disconnect() {
        if (this.focusTimer) {
            clearTimeout(this.focusTimer);
            this.focusTimer = null;
        }
    }

    async initialiseData() {
        this.state.isLoading = true;
        this.renderStatusMessage('Ładujemy rekomendacje…');
        this.showLoader(true);
        this.showContent(false);
        this.showEmptyState(false);
        this.showGlobalAlert(null);

        try {
            await this.loadEventData();
            await this.ensureGenresCatalog();
            await this.ensurePurchaseShelfId();
            this.state.loadError = null;
            this.showContent(true);
            this.toggleLoaderState(false);
            this.renderCards();
            this.renderSummary();
            this.renderStatusMessage('Załadowano rekomendacje AI.');
        } catch (error) {
            const message = this.resolveErrorMessage(error);
            this.state.loadError = message;
            this.showContent(false);
            this.showEmptyState(false);
            this.showGlobalAlert({ type: 'error', message });
            this.renderStatusMessage(message);
        } finally {
            this.state.isLoading = false;
            this.showLoader(false);
            this.toggleLoaderState(false);
        }
    }

    async loadEventData() {
        if (this.hasPreloadedEventJsonValue && this.preloadedEventJsonValue) {
            try {
                this.state.event = JSON.parse(this.preloadedEventJsonValue);
                this.cacheProposals(this.state.event?.recommended ?? []);
                return;
            } catch (error) {
                console.warn('Nieprawidłowy JSON zdarzenia rekomendacji', error);
            }
        }

        const eventId = this.resolveEventId();

        if (eventId === null) {
            throw new Error('Nieprawidłowe ID zdarzenia rekomendacji.');
        }

        const response = await fetch(EVENT_ENDPOINT_TEMPLATE(eventId), {
            method: 'GET',
            headers: this.buildJsonHeaders(),
            credentials: 'same-origin',
        });

        if (response.status === 404) {
            throw new Error('Nie znaleziono zestawu rekomendacji.');
        }

        if (!response.ok) {
            throw new Error('Nie udało się pobrać rekomendacji AI. Spróbuj ponownie później.');
        }

        const payload = await this.safeJson(response);

        if (!payload || typeof payload !== 'object') {
            throw new Error('Otrzymano pustą odpowiedź z serwera.');
        }

        this.state.event = payload;
        this.cacheProposals(this.state.event?.recommended ?? []);
    }

    async ensureGenresCatalog() {
        if (this.hasGenresCatalogJsonValue && this.genresCatalogJsonValue) {
            try {
                const parsed = JSON.parse(this.genresCatalogJsonValue);
                this.state.genresCatalog = this.normalizeGenresCatalogPayload(parsed);
                this.genreNameById = null;
                return;
            } catch (error) {
                console.warn('Nieprawidłowy JSON katalogu gatunków', error);
            }
        }

        const response = await fetch(GENRES_ENDPOINT, {
            method: 'GET',
            headers: this.buildJsonHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Nie udało się pobrać katalogu gatunków.');
        }

        const payload = await this.safeJson(response);
        this.state.genresCatalog = this.normalizeGenresCatalogPayload(payload);
        this.genreNameById = null;
    }

    async ensurePurchaseShelfId() {
        if (this.state.purchaseShelfId) {
            return;
        }

        const response = await fetch(SHELVES_ENDPOINT, {
            method: 'GET',
            headers: this.buildJsonHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Nie udało się pobrać listy regałów systemowych.');
        }

        const payload = await this.safeJson(response);
        const shelves = Array.isArray(payload)
            ? payload
            : Array.isArray(payload?.data)
                ? payload.data
                : null;

        if (!Array.isArray(shelves)) {
            throw new Error('Nie udało się ustalić regału „Do zakupu”.');
        }

        const normalizedShelves = shelves.filter((shelf) => shelf && typeof shelf === 'object');
        const systemShelves = normalizedShelves.filter((shelf) => shelf.isSystem === true);
        const candidateShelves = systemShelves.length > 0 ? systemShelves : normalizedShelves;

        if (candidateShelves.length === 0) {
            throw new Error('Nie udało się ustalić regału „Do zakupu”.');
        }

        const purchaseShelf = candidateShelves.find((shelf) => {
            const rawCode = (shelf.code ?? '').toString();
            const rawName = (shelf.name ?? '').toString();
            const code = rawCode.trim().toUpperCase();
            const name = rawName.trim().toLowerCase();

            if (code === 'PURCHASE') {
                return true;
            }

            return name === 'do zakupu';
        }) ?? systemShelves[0] ?? normalizedShelves[0] ?? null;

        if (!purchaseShelf?.id) {
            throw new Error('Brak regału systemowego „Do zakupu” – skontaktuj się z administratorem.');
        }

        this.state.purchaseShelfId = purchaseShelf.id;
    }

    async accept(event) {
        const button = event.currentTarget;

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const tempId = button.dataset.tempId;

        if (!tempId) {
            return;
        }

        if (this.actionStateByTempId[tempId] === ACTION_STATES.accepting) {
            return;
        }

        const proposal = this.proposalsByTempId.get(tempId);

        if (!proposal) {
            this.setCardError(tempId, 'Nie znaleziono danych propozycji.');
            return;
        }

        if (!this.state.purchaseShelfId) {
            this.setCardError(tempId, 'Brak regału docelowego dla akceptacji.');
            return;
        }

        this.setActionState(tempId, ACTION_STATES.accepting);
        this.setCardError(tempId, null);
        this.renderCardState(tempId);

        const eventId = this.resolveEventId();

        if (eventId === null) {
            this.setActionState(tempId, ACTION_STATES.error);
            this.setCardError(tempId, 'Nieprawidłowe ID zdarzenia rekomendacji.');
            this.renderCardState(tempId);
            return;
        }

        try {
            const bookResponse = await this.createBook(proposal, eventId);
            const bookData = await this.safeJson(bookResponse);
            const bookId = bookData?.id ?? bookData?.bookId ?? null;

            if (!bookId) {
                throw new Error('Nie udało się zapisać książki.');
            }

            const acceptResponse = await this.acceptProposal(eventId, bookId);

            if (acceptResponse.status === 200 || acceptResponse.status === 201) {
                this.actionStateByTempId[tempId] = ACTION_STATES.accepted;
                this.acceptedTempIds.add(tempId);
                this.updateAcceptedBookIds(bookId);
                this.renderCards();
                this.renderSummary();
                this.renderStatusMessage(`Zaakceptowano propozycję „${proposal.title}”. Przekierowanie do listy książek…`);

                const redirectParams = new URLSearchParams({
                    notice: 'book-created',
                    addedFromAi: 'true',
                    title: proposal.title ?? '',
                });

                window.location.assign(`${ACCEPT_REDIRECT_URL}?${redirectParams.toString()}`);
                return;
            }

            if (acceptResponse.status === 409) {
                this.setCardError(tempId, 'Ta propozycja została już zaakceptowana.');
                this.actionStateByTempId[tempId] = ACTION_STATES.accepted;
                this.acceptedTempIds.add(tempId);
                this.renderCards();
                this.renderSummary();
                return;
            }

            if (acceptResponse.status === 404) {
                throw new Error('Nie znaleziono zdarzenia rekomendacji. Odśwież stronę.');
            }

            throw new Error('Nie udało się zatwierdzić rekomendacji. Spróbuj ponownie.');
        } catch (error) {
            console.error('Błąd akceptacji rekomendacji', error);
            this.setActionState(tempId, ACTION_STATES.error);
            this.setCardError(tempId, this.resolveErrorMessage(error));
            this.renderCardState(tempId);
        }
    }

    reject(event) {
        const button = event.currentTarget;

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const tempId = button.dataset.tempId;

        if (!tempId) {
            return;
        }

        this.dismissedTempIds.add(tempId);
        this.renderCards();
        this.renderSummary();
        this.renderStatusMessage('Propozycja została odrzucona.');
    }

    renderCards() {
        if (!this.hasCardsContainerTarget || !this.hasCardTemplateTarget) {
            return;
        }

        const container = this.cardsContainerTarget;
        const template = this.cardTemplateTarget;
        const eventData = this.state.event;
        const proposals = Array.isArray(eventData?.recommended) ? eventData.recommended : [];
        const acceptedBookIds = Array.isArray(eventData?.acceptedBookIds) ? eventData.acceptedBookIds : [];
        const acceptedIdsSet = new Set([...this.acceptedTempIds, ...acceptedBookIds]);

        container.innerHTML = '';

        const visibleProposals = proposals.filter((proposal) => {
            const tempId = proposal?.tempId;

            if (!tempId) {
                return false;
            }

            if (acceptedIdsSet.has(tempId)) {
                return false;
            }

            if (this.dismissedTempIds.has(tempId)) {
                return false;
            }

            return true;
        });

        const hasVisibleProposals = visibleProposals.length > 0;

        this.toggleElementVisibility(container, hasVisibleProposals);
        this.showEmptyState(!hasVisibleProposals);

        if (!hasVisibleProposals) {
            return;
        }

        visibleProposals.forEach((proposal, index) => {
            const clone = template.content.cloneNode(true);
            const article = clone.querySelector('article');

            if (!(article instanceof HTMLElement)) {
                return;
            }

            const tempId = proposal.tempId;
            article.dataset.tempId = tempId;
            article.dataset.index = String(index);

            this.populateCard(article, proposal);
            container.appendChild(article);
        });
    }

    populateCard(article, proposal) {
        const titleElement = article.querySelector('.ai-recommendations-results-card__title');
        const authorElement = article.querySelector('.ai-recommendations-results-card__author');
        const reasonElement = article.querySelector('.ai-recommendations-results-card__reason');
        const genresList = article.querySelector('[data-role="genres"]');
        const statusElement = article.querySelector('.ai-recommendations-results-card__status');
        const acceptButton = article.querySelector('[data-role="acceptButton"]');
        const rejectButton = article.querySelector('[data-role="rejectButton"]');

        if (titleElement) {
            titleElement.textContent = proposal.title ?? 'Nieznany tytuł';
        }

        if (authorElement) {
            authorElement.textContent = proposal.author ? `Autor: ${proposal.author}` : 'Autor nieznany';
        }

        if (reasonElement) {
            reasonElement.textContent = proposal.reason ?? '';
        }

        if (genresList instanceof HTMLElement) {
            genresList.innerHTML = '';

            const genres = Array.isArray(proposal.genresId) ? proposal.genresId : [];
            const genreNames = this.resolveGenreNames(genres);

            genreNames.forEach((name) => {
                const li = document.createElement('li');
                li.className = 'ai-recommendations-results-card__genre';
                li.textContent = name;
                genresList.appendChild(li);
            });
        }

        if (acceptButton instanceof HTMLButtonElement) {
            acceptButton.dataset.tempId = proposal.tempId;
            this.updateAcceptButtonState(acceptButton, proposal.tempId);
        }

        if (rejectButton instanceof HTMLButtonElement) {
            rejectButton.dataset.tempId = proposal.tempId;
        }

        this.updateStatusElement(statusElement, proposal.tempId);
    }

    renderSummary() {
        if (!this.hasTotalCountTarget || !this.hasAcceptedCountTarget || !this.hasDismissedCountTarget) {
            return;
        }

        const eventData = this.state.event;
        const proposals = Array.isArray(eventData?.recommended) ? eventData.recommended : [];
        const total = proposals.length;
        const accepted = this.resolveAcceptedCount();
        const dismissed = this.dismissedTempIds.size;

        this.totalCountTarget.textContent = String(total);
        this.acceptedCountTarget.textContent = String(accepted);
        this.dismissedCountTarget.textContent = String(dismissed);
    }

    renderCardState(tempId) {
        const card = this.findCardByTempId(tempId);

        if (!(card instanceof HTMLElement)) {
            return;
        }

        const statusElement = card.querySelector('.ai-recommendations-results-card__status');
        const acceptButton = card.querySelector('[data-role="acceptButton"]');

        this.updateStatusElement(statusElement, tempId);
        this.updateAcceptButtonState(acceptButton, tempId);
    }

    updateStatusElement(statusElement, tempId) {
        if (!(statusElement instanceof HTMLElement)) {
            return;
        }

        statusElement.textContent = this.resolveCardStatusText(tempId);
    }

    updateAcceptButtonState(button, tempId) {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const state = this.actionStateByTempId[tempId] ?? ACTION_STATES.idle;
        const spinner = button.querySelector('[data-role="spinner"]');
        const label = button.querySelector('[data-role="label"]');

        const isLoading = state === ACTION_STATES.accepting;

        button.disabled = isLoading;
        button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
        button.classList.toggle('is-loading', isLoading);

        if (spinner instanceof HTMLElement) {
            spinner.hidden = !isLoading;
        }

        if (label instanceof HTMLElement) {
            label.textContent = isLoading ? 'Przetwarzanie…' : 'Akceptuj';
        }
    }

    resolveCardStatusText(tempId) {
        if (this.cardErrorByTempId[tempId]) {
            return this.cardErrorByTempId[tempId];
        }

        const state = this.actionStateByTempId[tempId] ?? ACTION_STATES.idle;

        switch (state) {
        case ACTION_STATES.accepting:
            return STATUS_MESSAGES.accepting;
        case ACTION_STATES.accepted:
            return STATUS_MESSAGES.accepted;
        case ACTION_STATES.error:
            return STATUS_MESSAGES.error;
        default:
            return '';
        }
    }

    findCardByTempId(tempId) {
        if (!this.hasCardsContainerTarget) {
            return null;
        }

        return this.cardsContainerTarget.querySelector(`article[data-temp-id="${tempId}"]`);
    }

    updateAcceptedBookIds(bookId) {
        if (!bookId) {
            return;
        }

        if (!this.state.event) {
            return;
        }

        const accepted = Array.isArray(this.state.event.acceptedBookIds) ? this.state.event.acceptedBookIds : [];

        if (!accepted.includes(bookId)) {
            accepted.push(bookId);
        }

        this.state.event.acceptedBookIds = accepted;
    }

    resolveAcceptedCount() {
        const eventData = this.state.event;
        const acceptedFromEvent = Array.isArray(eventData?.acceptedBookIds) ? eventData.acceptedBookIds.length : 0;
        return Math.max(acceptedFromEvent, this.acceptedTempIds.size);
    }

    cacheProposals(proposals) {
        this.proposalsByTempId.clear();

        if (!Array.isArray(proposals)) {
            return;
        }

        proposals.forEach((proposal) => {
            if (proposal?.tempId) {
                this.proposalsByTempId.set(proposal.tempId, proposal);
            }
        });
    }

    setActionState(tempId, state) {
        this.actionStateByTempId[tempId] = state;
    }

    setCardError(tempId, message) {
        if (message) {
            this.cardErrorByTempId[tempId] = message;
        } else {
            delete this.cardErrorByTempId[tempId];
        }
    }

    async createBook(proposal, eventId) {
        const payload = {
            title: proposal.title,
            author: proposal.author,
            genreIds: Array.isArray(proposal.genresId) ? proposal.genresId : [],
            shelfId: this.state.purchaseShelfId,
            source: 'ai_recommendation',
            recommendationId: eventId,
        };

        const response = await fetch(BOOKS_ENDPOINT, {
            method: 'POST',
            headers: this.buildJsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (response.status === 201) {
            return response;
        }

        if (response.status === 400) {
            throw new Error('Nieprawidłowe dane książki.');
        }

        if (response.status === 404) {
            throw new Error('Nie znaleziono wymaganego zasobu. Odśwież stronę.');
        }

        throw new Error('Nie udało się zapisać książki. Spróbuj ponownie.');
    }

    async acceptProposal(eventId, bookId) {
        const headers = this.buildJsonHeaders();
        headers['Idempotency-Key'] = self.crypto?.randomUUID ? self.crypto.randomUUID() : this.generateFallbackUUID();

        return fetch(ACCEPT_ENDPOINT_TEMPLATE(eventId), {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ bookId }),
        });
    }

    showLoader(visible) {
        if (!this.hasLoaderTarget) {
            return;
        }

        this.toggleElementVisibility(this.loaderTarget, visible);
    }

    showContent(visible) {
        if (!this.hasContentTarget) {
            return;
        }

        this.toggleElementVisibility(this.contentTarget, visible);
    }

    showEmptyState(visible) {
        if (!this.hasEmptyStateTarget) {
            return;
        }

        this.toggleElementVisibility(this.emptyStateTarget, visible);
    }

    toggleLoaderState(isLoading) {
        this.element.classList.toggle('is-loading', isLoading);
    }

    toggleElementVisibility(element, visible) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        if (visible) {
            element.hidden = false;
            element.classList.remove('is-hidden');
            element.style.removeProperty('display');
            element.removeAttribute('aria-hidden');
            return;
        }

        element.hidden = true;
        element.classList.add('is-hidden');
        element.style.display = 'none';
        element.setAttribute('aria-hidden', 'true');
    }

    showGlobalAlert(alert) {
        if (!this.hasGlobalAlertRegionTarget) {
            return;
        }

        const container = this.globalAlertRegionTarget;
        container.innerHTML = '';
        container.hidden = true;

        if (!alert) {
            return;
        }

        const { type, message } = alert;

        if (!message) {
            return;
        }

        const normalizedType = ALERT_TYPES.has(type) ? type : 'info';
        const template = this.hasBannerTemplateTarget ? this.bannerTemplateTarget : null;
        let element = null;

        if (template) {
            const clone = template.content.cloneNode(true);
            const alertElement = clone.querySelector('[role="alert"]');

            if (alertElement) {
                alertElement.className = `alert alert--${normalizedType}`;
                const textElement = alertElement.querySelector('.alert__message');

                if (textElement) {
                    textElement.textContent = message;
                }

                element = alertElement;
            }
        }

        if (!element) {
            element = document.createElement('div');
            element.className = `alert alert--${normalizedType}`;
            element.setAttribute('role', 'alert');
            element.textContent = message;
        }

        element.setAttribute('tabindex', '-1');
        container.appendChild(element);
        container.hidden = false;
        element.focus?.();
    }

    renderStatusMessage(message) {
        if (this.hasStatusRegionTarget) {
            this.statusRegionTarget.textContent = message ?? '';
        }
    }

    resolveGenreNames(ids) {
        if (!Array.isArray(ids) || ids.length === 0) {
            return ['Nieznany gatunek'];
        }

        const map = this.genreNameById ?? this.buildGenreMap(this.state.genresCatalog);

        return ids.map((id) => {
            const numericId = Number.parseInt(id, 10);

            if (!Number.isNaN(numericId) && map.has(numericId)) {
                return map.get(numericId);
            }

            return `Nieznany gatunek (id: ${id})`;
        });
    }

    buildGenreMap(catalog) {
        const map = new Map();
        const source = Array.isArray(catalog) ? catalog : [];

        source.forEach((item) => {
            if (!item || typeof item !== 'object') {
                return;
            }

            const id = Number.parseInt(item.id, 10);
            const name = typeof item.name === 'string' ? item.name.trim() : '';

            if (!Number.isNaN(id) && name !== '') {
                map.set(id, name);
            }
        });

        this.genreNameById = map;
        return map;
    }

    normalizeGenresCatalogPayload(payload) {
        if (Array.isArray(payload)) {
            return payload;
        }

        if (payload && typeof payload === 'object' && Array.isArray(payload.data)) {
            return payload.data;
        }

        return [];
    }

    resolveEventId() {
        if (this.hasEventIdValue && Number.isFinite(this.eventIdValue)) {
            return this.eventIdValue;
        }

        const idFromDataset = Number.parseInt(this.element.dataset.eventId, 10);

        if (!Number.isNaN(idFromDataset)) {
            return idFromDataset;
        }

        return null;
    }

    focusHeadingSoon() {
        const heading = this.element.querySelector('#ai-results-title');

        if (!heading) {
            return;
        }

        this.focusTimer = window.setTimeout(() => {
            heading.focus({ preventScroll: false });
            this.focusTimer = null;
        }, 50);
    }

    buildJsonHeaders() {
        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        const csrfToken = this.resolveCsrfToken();

        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
            headers['csrf-token-authenticate'] = csrfToken;
        }

        return headers;
    }

    resolveCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token-authenticate"]');

        return meta?.getAttribute('content') ?? null;
    }

    async safeJson(response) {
        const contentType = response.headers.get('content-type');

        if (!contentType || !contentType.includes('application/json')) {
            return null;
        }

        try {
            return await response.json();
        } catch (error) {
            console.warn('Nie udało się sparsować odpowiedzi JSON', error);
            return null;
        }
    }

    resolveErrorMessage(error) {
        if (!error) {
            return 'Wystąpił nieoczekiwany błąd.';
        }

        if (typeof error === 'string') {
            return error;
        }

        if (error instanceof Error && error.message) {
            return error.message;
        }

        return 'Wystąpił nieoczekiwany błąd.';
    }

    generateFallbackUUID() {
        const template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';

        return template.replace(/[xy]/g, (character) => {
            const random = (Math.random() * 16) | 0;
            const value = character === 'x' ? random : (random & 0x3) | 0x8;

            return value.toString(16);
        });
    }
}


