import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        reloadUrl: String,
    };

    static targets = ['modal', 'modalTitle', 'modalMessage', 'confirmButton'];

    connect() {
        this.resetState();
    }

    openDeleteConfirm(event) {
        event.preventDefault();

        const { id, title, url } = event.params;

        if (!url) {
            return;
        }

        this.pendingDelete = { id, title, url };
        this.triggerButton = event.currentTarget;

        if (this.triggerButton instanceof HTMLButtonElement) {
            this.triggerButton.disabled = true;
            this.triggerButton.setAttribute('aria-busy', 'true');
        }

        this.modalTitleTarget.textContent = 'Usuń książkę';
        this.modalMessageTarget.textContent = `Czy na pewno chcesz usunąć książkę "${title}"?`;

        this.modalTarget.hidden = false;

        this.confirmButtonTarget.disabled = false;
        this.confirmButtonTarget.removeAttribute('aria-busy');
        this.confirmButtonTarget.textContent = 'Usuń';
        this.confirmButtonTarget.focus();
    }

    cancelDelete() {
        this.closeModal();
    }

    confirmDelete() {
        if (!this.pendingDelete) {
            return;
        }

        const { url } = this.pendingDelete;

        this.confirmButtonTarget.disabled = true;
        this.confirmButtonTarget.setAttribute('aria-busy', 'true');
        this.confirmButtonTarget.textContent = 'Usuwanie…';

        fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                let detail;

                if (response.status === 204 || response.status === 200) {
                    this.closeModal(false);
                    this.redirectWithNotice('book-deleted');

                    return;
                }

                try {
                    const payload = await response.json();
                    detail = typeof payload?.detail === 'string' ? payload.detail : undefined;
                } catch (error) {
                    // ignore JSON parsing issues
                }

                this.closeModal(false);
                this.redirectWithNotice('book-delete-failed', detail);
            })
            .catch(() => {
                this.closeModal(false);
                this.redirectWithNotice('book-delete-failed');
            });
    }

    resetState() {
        this.pendingDelete = null;
        this.triggerButton = null;
    }

    closeModal(restoreFocus = true) {
        if (this.modalTarget) {
            this.modalTarget.hidden = true;
        }

        if (this.confirmButtonTarget) {
            this.confirmButtonTarget.disabled = false;
            this.confirmButtonTarget.removeAttribute('aria-busy');
            this.confirmButtonTarget.textContent = 'Usuń';
        }

        if (restoreFocus && this.triggerButton instanceof HTMLElement) {
            this.triggerButton.disabled = false;
            this.triggerButton.removeAttribute('aria-busy');
            this.triggerButton.focus();
        }

        this.resetState();
    }

    redirectWithNotice(code, detail) {
        const base = this.hasReloadUrlValue ? this.reloadUrlValue : window.location.href;
        const url = new URL(base, window.location.origin);

        if (code) {
            url.searchParams.set('notice', code);
        } else {
            url.searchParams.delete('notice');
        }

        if (detail) {
            url.searchParams.set('noticeDetail', detail);
        } else {
            url.searchParams.delete('noticeDetail');
        }

        window.location.href = url.toString();
    }
}

