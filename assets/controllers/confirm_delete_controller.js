import { Controller } from '@hotwired/stimulus';

const DEFAULT_MESSAGE = 'Czy na pewno chcesz kontynuować?';

export default class extends Controller {
    static values = {
        title: String,
        message: String,
        confirmLabel: String,
        cancelLabel: String,
    };

    confirm(event) {
        const message = this.messageValue || DEFAULT_MESSAGE;

        const confirmed = window.confirm(message);

        if (!confirmed) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }
}

