import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['offset'];

    reset(event) {
        event.preventDefault();

        this.clearInputs();

        if (this.hasOffsetTarget) {
            this.offsetTarget.value = '0';
        }

        this.element.requestSubmit();
    }

    clearInputs() {
        this.element.querySelectorAll('input[type="search"], input[type="text"]').forEach((input) => {
            input.value = '';
        });

        const shelfSelect = this.element.querySelector('select[name="shelfId"]');
        if (shelfSelect) {
            shelfSelect.value = '';
        }

        this.element.querySelectorAll('input[name="genreIds[]"]').forEach((checkbox) => {
            checkbox.checked = false;
        });

        const sortSelect = this.element.querySelector('select[name="sort"]');
        if (sortSelect) {
            sortSelect.value = 'createdAt';
        }

        const orderSelect = this.element.querySelector('select[name="order"]');
        if (orderSelect) {
            orderSelect.value = 'desc';
        }

        const limitSelect = this.element.querySelector('select[name="limit"]');
        if (limitSelect) {
            limitSelect.value = '20';
        }
    }
}

