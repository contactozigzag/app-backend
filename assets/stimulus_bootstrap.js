import { startStimulusApp } from '@symfony/stimulus-bundle';
import AdminDashboardController from './controllers/admin_dashboard_controller.js';
import GoogleAddressAutocompleteController from './controllers/google-address-autocomplete_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('admin-dashboard', AdminDashboardController);
app.register('google-address-autocomplete', GoogleAddressAutocompleteController);
