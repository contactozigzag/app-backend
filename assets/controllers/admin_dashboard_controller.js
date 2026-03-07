import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mercureUrl: String,
    };

    static targets = ['kpiCard', 'routesTbody', 'alertsTbody'];

    #eventSource = null;

    connect() {
        const url = new URL(this.mercureUrlValue);
        url.searchParams.append('topic', 'admin/dashboard/stats');
        url.searchParams.append('topic', 'admin/alerts');
        url.searchParams.append('topic', 'admin/routes');

        this.#eventSource = new EventSource(url.toString());
        this.#eventSource.addEventListener('message', (event) => {
            this.#handleMessage(event);
        });
        this.#eventSource.addEventListener('error', () => {
            // Silently ignore SSE errors (hub may be unavailable in dev/test)
        });
    }

    disconnect() {
        this.#eventSource?.close();
        this.#eventSource = null;
    }

    #handleMessage(event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch {
            return;
        }

        // Detect payload type by keys present
        if ('schools' in data) {
            this.#handleStats(data);
        } else if ('alertId' in data) {
            this.#handleAlert(data);
        } else if ('progressPct' in data || ('id' in data && 'startedAt' in data)) {
            this.#handleRoute(data);
        }
    }

    #handleStats(data) {
        for (const card of this.kpiCardTargets) {
            const key = card.dataset.stat;
            if (key in data) {
                const newVal = String(data[key]);
                if (card.textContent.trim() !== newVal) {
                    card.textContent = newVal;
                    card.classList.add('text-highlight');
                    setTimeout(() => card.classList.remove('text-highlight'), 1500);
                }
            }
        }
    }

    #handleAlert(data) {
        const tbody = this.alertsTbodyTarget;
        const existing = tbody.querySelector(`[data-alert-id="${data.alertId}"]`);

        if (!data.isOpen) {
            existing?.remove();
            return;
        }

        const badge = data.status === 'PENDING' ? 'danger' : 'warning';
        const html = `
            <tr data-alert-id="${this.#escHtml(data.alertId)}">
                <td>${this.#escHtml(data.driverName)}</td>
                <td><span class="badge bg-${badge}">${this.#escHtml(data.status)}</span></td>
                <td><small>${this.#escHtml(data.triggeredAt)}</small></td>
            </tr>`;

        if (existing) {
            existing.outerHTML = html;
        } else {
            // Remove "no alerts" placeholder row if present
            const placeholder = tbody.querySelector('tr td[colspan]');
            placeholder?.parentElement?.remove();
            tbody.insertAdjacentHTML('afterbegin', html);
        }
    }

    #handleRoute(data) {
        const tbody = this.routesTbodyTarget;
        const existing = tbody.querySelector(`[data-route-id="${data.id}"]`);
        if (!existing) return;

        // Update status label if status changed
        const pct = data.progressPct ?? 0;
        const progressBar = existing.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = `${pct}%`;
        }
        if (data.status === 'completed' || data.status === 'cancelled') {
            existing.remove();
        }
    }

    #escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}
