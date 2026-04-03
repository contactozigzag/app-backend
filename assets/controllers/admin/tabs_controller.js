import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        const hash = window.location.hash.substring(1);
        const target = hash ? this.tabTargets.find(t => t.dataset.tab === hash) : this.tabTargets[0];
        if (target) this.activate({ currentTarget: target });
    }

    activate(event) {
        const tab = event.currentTarget;
        const tabName = tab.dataset.tab;

        this.tabTargets.forEach(t => t.classList.toggle('active', t === tab));
        this.panelTargets.forEach(p => {
            const isActive = p.dataset.tab === tabName;
            p.classList.toggle('d-none', !isActive);
            if (isActive) {
                const frame = p.querySelector('turbo-frame[loading="lazy"]');
                if (frame) frame.removeAttribute('loading');
            }
        });

        history.replaceState(null, '', `#${tabName}`);
    }
}
