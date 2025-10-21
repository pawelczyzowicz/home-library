import { Controller } from '@hotwired/stimulus';
import { generateCsrfHeaders } from './csrf_protection_controller.js';

const bannerTimeoutMs = 6000;

export default class extends Controller {
    static targets = [
        'banner',
        'form',
        'nameInput',
        'nameError',
        'createButton',
        'tableBody',
        'modal',
        'modalTitle',
        'modalMessage',
    ];

    connect() {
        this.loadingList = false;
        this.creating = false;
        this.deletingId = null;
        this.confirmState = { open: false };
        this.bannerState = null;
        this.vm = { items: [], total: 0 };

        this.loadShelves();
    }

    disconnect() {
        this.clearBannerTimeout();
    }

    async loadShelves() {
        if (this.loadingList) {
            return;
        }

        this.loadingList = true;

        try {
            const response = await fetch('/api/shelves', {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load shelves');
            }

            const payload = await response.json();
            this.vm = this.transformListPayload(payload);
            this.renderTable();
            this.renderSummary();
        } catch (error) {
            this.showBanner({ type: 'error', text: 'Wystąpił błąd podczas ładowania regałów. Spróbuj ponownie.' });
        } finally {
            this.loadingList = false;
        }
    }

    transformListPayload(payload) {
        const items = Array.isArray(payload?.data)
            ? payload.data.map((item) => this.toRowVm(item))
            : [];

        return {
            items,
            total: typeof payload?.meta?.total === 'number' ? payload.meta.total : items.length,
        };
    }

    toRowVm(dto) {
        const createdAtLabel = this.formatDate(dto?.createdAt);
        const updatedAtLabel = this.formatDate(dto?.updatedAt);
        const isSystem = Boolean(dto?.isSystem);

        return {
            id: dto?.id ?? '',
            name: dto?.name ?? '',
            isSystem,
            createdAtLabel,
            updatedAtLabel,
            canDelete: !isSystem,
        };
    }

    formatDate(value) {
        if (!value) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat('pl-PL', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(value));
        } catch (error) {
            return value;
        }
    }

    createShelf(event) {
        event.preventDefault();

        if (this.creating) {
            return;
        }

        const name = this.nameInputTarget.value.trim();
        const validationError = this.validateName(name);

        if (validationError) {
            this.showNameError(validationError);
            return;
        }

        this.submitCreate(name);
    }

    validateName(name) {
        if (name.length === 0) {
            return 'Nazwa regału jest wymagana.';
        }

        if (name.length > 50) {
            return 'Nazwa regału nie może przekraczać 50 znaków.';
        }

        return null;
    }

    showNameError(message) {
        this.nameErrorTarget.textContent = message;
        this.nameErrorTarget.hidden = false;
        this.nameInputTarget.setAttribute('aria-invalid', 'true');
        this.nameInputTarget.setAttribute('aria-describedby', this.nameErrorTarget.id || this.ensureErrorId());
    }

    ensureErrorId() {
        if (!this.nameErrorTarget.id) {
            this.nameErrorTarget.id = `${this.element.id || 'shelves'}-name-error`;
        }

        return this.nameErrorTarget.id;
    }

    clearNameError() {
        this.nameErrorTarget.textContent = '';
        this.nameErrorTarget.hidden = true;
        this.nameInputTarget.removeAttribute('aria-invalid');
        this.nameInputTarget.removeAttribute('aria-describedby');
    }

    async submitCreate(name) {
        this.creating = true;
        this.clearNameError();
        this.createButtonTarget.disabled = true;
        this.createButtonTarget.setAttribute('aria-busy', 'true');
        this.createButtonTarget.textContent = 'Dodawanie…';

        try {
            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            // Ensure CSRF token is generated before reading headers
            try { generateCsrfHeaders(this.formTarget); } catch (e) { /* noop */ }
            Object.assign(headers, generateCsrfHeaders(this.formTarget));

            const response = await fetch('/api/shelves', {
                method: 'POST',
                headers,
                body: JSON.stringify({ name }),
            });

            switch (response.status) {
                case 201:
                    this.nameInputTarget.value = '';
                    this.clearBanner();
                    this.showBanner({ type: 'success', text: 'Regał został utworzony.' });
                    await this.loadShelves();
                    return;
                case 409: {
                    const conflict = await response.json();
                    const detail = conflict?.detail ?? `Regał o nazwie "${name}" już istnieje. Wybierz inną nazwę.`;
                    this.showBanner({ type: 'error', text: detail });
                    return;
                }
                case 422: {
                    const errors = await response.json();
                    const violationMessage = errors?.violations?.[0]?.title ?? 'Nieprawidłowa nazwa regału.';
                    this.showNameError(violationMessage);
                    return;
                }
                default:
                    if (!response.ok) {
                        throw new Error('Create shelf failed');
                    }
            }

            this.showBanner({ type: 'success', text: 'Regał został utworzony.' });
            await this.loadShelves();
        } catch (error) {
            this.showBanner({ type: 'error', text: 'Wystąpił błąd. Spróbuj ponownie.' });
        } finally {
            this.creating = false;
            this.createButtonTarget.disabled = false;
            this.createButtonTarget.removeAttribute('aria-busy');
            this.createButtonTarget.textContent = 'Dodaj';
        }
    }

    renderTable() {
        this.tableBodyTarget.innerHTML = '';

        if (this.vm.items.length === 0) {
            const emptyRow = document.createElement('tr');
            const emptyCell = document.createElement('td');
            emptyCell.colSpan = 5;
            emptyCell.className = 'shelves-view__empty';
            emptyCell.textContent = 'Brak regałów do wyświetlenia.';
            emptyRow.appendChild(emptyCell);
            this.tableBodyTarget.appendChild(emptyRow);
            return;
        }

        this.vm.items.forEach((item) => {
            const row = document.createElement('tr');
            row.dataset.shelfId = item.id;

            const nameCell = document.createElement('td');
            nameCell.textContent = item.name;

            const systemCell = document.createElement('td');
            systemCell.innerHTML = item.isSystem
                ? '<span class="badge badge--system" aria-label="Regał systemowy">Systemowy</span>'
                : '';

            const createdCell = document.createElement('td');
            createdCell.textContent = item.createdAtLabel;

            const updatedCell = document.createElement('td');
            updatedCell.textContent = item.updatedAtLabel;

            const actionsCell = document.createElement('td');

            if (item.canDelete) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = 'Usuń';
                button.dataset.action = 'shelves#openDeleteConfirm';
                button.dataset.shelfId = item.id;
                button.dataset.shelfName = item.name;
                button.setAttribute('aria-label', `Usuń regał ${item.name}`);
                actionsCell.appendChild(button);
            } else {
                actionsCell.textContent = '—';
            }

            row.appendChild(nameCell);
            row.appendChild(systemCell);
            row.appendChild(createdCell);
            row.appendChild(updatedCell);
            row.appendChild(actionsCell);

            this.tableBodyTarget.appendChild(row);
        });
    }

    renderSummary() {
        const summaryEl = this.element.querySelector('[data-shelves-target="summary"]');

        if (!summaryEl) {
            return;
        }

        const total = Number.isFinite(this.vm.total) ? this.vm.total : this.vm.items.length;
        summaryEl.textContent = total === 1 ? '1 regał' : `${total} regałów`;
    }

    openDeleteConfirm(event) {
        const button = event.currentTarget;
        const shelfId = button.dataset.shelfId;
        const shelfName = button.dataset.shelfName;

        this.confirmState = {
            open: true,
            shelfId,
            shelfName,
            trigger: button,
        };

        this.modalTitleTarget.textContent = 'Usuń regał';
        this.modalMessageTarget.textContent = `Czy na pewno chcesz usunąć regał „${shelfName}”?`;
        this.modalTarget.hidden = false;

        const confirmButton = this.modalTarget.querySelector('button[data-action="shelves#confirmDelete"]');

        if (confirmButton) {
            confirmButton.focus();
        }
    }

    cancelDelete() {
        this.closeModal();
    }

    async confirmDelete() {
        if (!this.confirmState.open || !this.confirmState.shelfId) {
            return;
        }

        const { shelfId, shelfName } = this.confirmState;
        this.deletingId = shelfId;

        try {
            const headers = {
                Accept: 'application/json',
            };

            Object.assign(headers, generateCsrfHeaders(this.formTarget));

            const response = await fetch(`/api/shelves/${encodeURIComponent(shelfId)}`, {
                method: 'DELETE',
                headers,
            });

            switch (response.status) {
                case 204:
                    this.showBanner({ type: 'success', text: 'Regał został usunięty.' });
                    await this.loadShelves();
                    break;
                case 403: {
                    const body = await response.json();
                    this.showBanner({
                        type: 'error',
                        text: body?.detail ?? 'Regał systemowy nie może być usunięty.',
                    });
                    break;
                }
                case 404:
                    this.showBanner({ type: 'error', text: 'Nie znaleziono regału.' });
                    break;
                case 409: {
                    const body = await response.json();
                    const detail = body?.detail ?? 'Regał nie może zostać usunięty.';
                    const count = body?.booksCount;
                    this.showBanner({
                        type: 'warning',
                        text: count ? `${detail} (${count} książek).` : detail,
                    });
                    break;
                }
                default:
                    if (!response.ok) {
                        throw new Error('Delete failed');
                    }
                    this.showBanner({ type: 'success', text: 'Regał został usunięty.' });
                    await this.loadShelves();
            }
        } catch (error) {
            this.showBanner({ type: 'error', text: 'Wystąpił błąd. Spróbuj ponownie.' });
        } finally {
            this.deletingId = null;
            this.closeModal();
        }
    }

    closeModal() {
        const triggerButton = this.confirmState?.trigger;

        this.modalTarget.hidden = true;
        this.confirmState = { open: false };

        if (triggerButton instanceof HTMLElement) {
            triggerButton.focus();
        }
    }

    showBanner(banner) {
        this.bannerState = banner;
        this.renderBanner();
    }

    clearBanner() {
        this.bannerState = null;
        this.renderBanner();
    }

    renderBanner() {
        this.clearBannerTimeout();

        const container = this.bannerTarget;
        container.innerHTML = '';

        if (!this.bannerState) {
            return;
        }

        const banner = document.createElement('div');
        banner.className = `banner banner--${this.bannerState.type}`;
        banner.setAttribute('role', 'alert');
        banner.tabIndex = -1;

        const message = document.createElement('span');
        message.className = 'banner__message';
        message.textContent = this.bannerState.text;

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'banner__close';
        closeButton.setAttribute('aria-label', 'Zamknij komunikat');
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', () => {
            this.clearBanner();
        });

        banner.appendChild(message);
        banner.appendChild(closeButton);
        container.appendChild(banner);

        banner.focus();

        this.bannerTimeout = window.setTimeout(() => {
            this.clearBanner();
        }, bannerTimeoutMs);
    }

    clearBannerTimeout() {
        if (this.bannerTimeout) {
            window.clearTimeout(this.bannerTimeout);
            this.bannerTimeout = null;
        }
    }
}


