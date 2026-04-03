import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'content'];

    toggle() {
        const content = this.contentTarget;
        const button = this.toggleTarget;
        const isHidden = content.classList.contains('d-none');

        content.classList.toggle('d-none', !isHidden);
        button.textContent = isHidden ? 'Hide JSON' : 'Show JSON';
    }
}
