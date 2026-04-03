import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['mapContainer', 'routeDetail', 'alertBanner', 'alertText'];
    static values = {
        mercureUrl: String,
        driversUrl: String,
    };

    connect() {
        this._map = null;
        this._markers = new Map(); // driverId → Leaflet marker
        this._eventSources = [];
        this._reconnectTimers = [];

        this._initMap();
        this._fetchDrivers();
    }

    disconnect() {
        this._eventSources.forEach((es) => es.close());
        this._reconnectTimers.forEach((t) => clearTimeout(t));
        if (this._map) {
            this._map.remove();
            this._map = null;
        }
    }

    // ─── Map initialisation ───────────────────────────────────────────────

    _initMap() {
        const L = window.L;
        if (!L) {
            console.warn('Leaflet not loaded');
            return;
        }

        this._map = L.map(this.mapContainerTarget).setView([-34.6037, -58.3816], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(this._map);
    }

    // ─── Driver positions ─────────────────────────────────────────────────

    async _fetchDrivers() {
        try {
            const res = await fetch(this.driversUrlValue);
            if (!res.ok) return;

            const drivers = await res.json();
            drivers.forEach((d) => this._upsertMarker(d));

            // Subscribe to Mercure for each active driver
            drivers.forEach((d) => {
                if (d.driverId) {
                    this._subscribeTo(`/tracking/driver/${d.driverId}`, (data) => {
                        this._onDriverUpdate(data);
                    });
                }
            });

            // Subscribe to admin-wide alert topic
            this._subscribeTo('/alerts/admin/0', (data) => {
                this._onAlert(data);
            });
        } catch (e) {
            console.error('Failed to fetch drivers', e);
        }
    }

    _upsertMarker(driver) {
        const L = window.L;
        if (!L || !this._map) return;
        if (!driver.latitude && !driver.longitude) return;

        const latlng = [driver.latitude, driver.longitude];

        if (this._markers.has(driver.driverId)) {
            const marker = this._markers.get(driver.driverId);
            marker.setLatLng(latlng);
        } else {
            const icon = L.divIcon({
                html: '<span style="font-size:1.4rem">🚌</span>',
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            });
            const marker = L.marker(latlng, { icon })
                .addTo(this._map)
                .bindPopup(`<strong>${driver.name}</strong><br>${driver.routeName ?? ''}`);
            marker._routeId = driver.activeRouteId;
            this._markers.set(driver.driverId, marker);
        }
    }

    _onDriverUpdate(data) {
        if (data.driverId) this._upsertMarker(data);
    }

    // ─── Route selection (triggered by route card click) ──────────────────

    selectRoute(event) {
        const card = event.currentTarget;
        const routeId = card.dataset.routeId;
        if (!routeId) return;

        // Highlight selected card
        document.querySelectorAll('.route-card').forEach((c) => {
            c.classList.toggle('selected', c === card);
        });

        // Pan map to this route's driver marker
        this._markers.forEach((marker) => {
            if (String(marker._routeId) === String(routeId)) {
                this._map?.panTo(marker.getLatLng());
                marker.openPopup();
            }
        });

        // Load detail panel via Turbo frame navigation (safe — no innerHTML)
        const frame = document.getElementById('route-detail-panel');
        if (frame) {
            frame.setAttribute('src', `/admin/api/route/${routeId}/detail-panel`);
            this.routeDetailTarget.classList.remove('d-none');
        }
    }

    // ─── Alert overlay ────────────────────────────────────────────────────

    _onAlert(data) {
        if (!data.driverName && !data.routeName) return;

        this.alertTextTarget.textContent =
            `Driver: ${data.driverName ?? '?'} — Route: ${data.routeName ?? '?'}`;
        this.alertBannerTarget.style.display = 'block';
    }

    // ─── Mercure subscription with exponential backoff ────────────────────

    _subscribeTo(topic, handler, retryDelay = 1000) {
        const url = new URL(this.mercureUrlValue);
        url.searchParams.append('topic', topic);

        const es = new EventSource(url.toString());

        es.onmessage = (event) => {
            try {
                handler(JSON.parse(event.data));
            } catch {
                // non-JSON message — ignore
            }
        };

        es.onerror = () => {
            es.close();
            this._eventSources = this._eventSources.filter((s) => s !== es);
            const nextDelay = Math.min(retryDelay * 2, 30000);
            const t = setTimeout(() => this._subscribeTo(topic, handler, nextDelay), retryDelay);
            this._reconnectTimers.push(t);
        };

        this._eventSources.push(es);
    }
}
