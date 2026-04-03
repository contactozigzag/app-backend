import { startStimulusApp } from '@symfony/stimulus-bundle';
import AdminDashboardController from './controllers/admin_dashboard_controller.js';
import GoogleAddressAutocompleteController from './controllers/google-address-autocomplete_controller.js';
import JsonPreviewController from './controllers/admin/json_preview_controller.js';
import LiveMapController from './controllers/admin/live_map_controller.js';
import TabsController from './controllers/admin/tabs_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('admin-dashboard', AdminDashboardController);
app.register('google-address-autocomplete', GoogleAddressAutocompleteController);
app.register('json-preview', JsonPreviewController);
app.register('live-map', LiveMapController);
app.register('tabs', TabsController);
