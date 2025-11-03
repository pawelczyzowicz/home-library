import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'offset'];

    static values = {
        delay: { type: Number, default: 400 },
    };

    connect() {
        this.submitTimeout = null;
    }

    disconnect() {
        this.clearTimeout();
    }

    debouncedSubmit() {
        this.clearTimeout();

        if (this.hasSearchTarget) {
            const trimmed = this.searchTarget.value.trim();

            if (this.searchTarget.value !== trimmed) {
                this.searchTarget.value = trimmed;
            }
        }

        this.submitTimeout = window.setTimeout(() => {
            this.submit();
        }, this.delayValue);
    }

    resetOffsetAndSubmit() {
        if (this.hasOffsetTarget) {
            this.offsetTarget.value = '0';
        }

        this.submit();
    }

    submit() {
        this.clearTimeout();
        this.element.requestSubmit();
    }

    clearTimeout() {
        if (this.submitTimeout) {
            window.clearTimeout(this.submitTimeout);
            this.submitTimeout = null;
        }
    }
}

