# Pickup Mtaani - WooCommerce Plugin

**Version:** 1.0.0  
**Author:** Eric Muthemba Kiarie  
**License:** GPL-3.0

## Description
Pickup Mtaani for WooCommerce integrates Pickup Mtaani shipping into checkout.
Customers can choose either:
- `Pickup from Agent`
- `Doorstep Dropoff`

The plugin validates the Pickup Mtaani API key before enabling the shipping method and prevents activation when validation fails.

## Features
- WooCommerce shipping method: `Pickup Mtaani`
- Checkout delivery mode selector:
  - `Pickup from Agent`
  - `Doorstep Dropoff`
- Pickup agent map selector (Google Maps) for pickup mode
- API key validation on settings save before activation
- Clear admin error reason when API key validation fails
- Order shipment creation on order processing
- Dashboard widget for in-transit Pickup Mtaani shipments

## Requirements
- WordPress 6.0+
- WooCommerce 7.0+
- PHP 8.0+
- Pickup Mtaani production API key
- Google Maps API key (for pickup map UI)

## Installation
### 1. Upload via WordPress Admin
1. Go to `Plugins > Add New > Upload Plugin`
2. Select the plugin zip
3. Click `Install Now` and `Activate`

### 2. Upload via FTP
1. Extract plugin folder into `/wp-content/plugins/pickup-mtaani/`
2. Activate in `Plugins`

## Configuration
1. Go to `WooCommerce > Settings > Shipping > Shipping Zones`
2. Add shipping method: `Pickup Mtaani`
3. Open the method settings and configure:
   - `Enable Pickup Mtaani Shipping`
   - `Method Title`
   - `API Key`
   - `Google Maps API Key`
4. Save settings

### API key validation behavior
- When enabled is checked and settings are saved, the plugin verifies the API key first.
- If verification fails, the method is not activated and an admin error is shown with the failure reason.

## Usage
### Customer checkout flow
1. Customer selects `Pickup Mtaani` shipping method.
2. Customer selects delivery option:
   - `Pickup from Agent`: choose an agent on the map.
   - `Doorstep Dropoff`: no agent selection required.
3. Customer completes checkout.

### Order fulfillment flow
- On order status `processing`, the plugin creates a Pickup Mtaani shipment.
- For `Pickup from Agent`, payload includes selected pickup agent.
- For `Doorstep Dropoff`, payload uses doorstep shipment path.

## API behavior
- Production-only base URL: `https://api.pickupmtaani.com`
- No sandbox mode in current implementation.

### Endpoints used
- Credential validation: `/locations/agents`
- Agent listing for checkout map: `/locations/agents`
- Pickup shipment: `/packages/agent-agent`
- Doorstep shipment default: `/packages/doorstep-package`
- Tracking: `/packages/track/{tracking_number}`

### Filters
- `pm_doorstep_shipment_endpoint`
  - Override doorstep shipment endpoint if your account uses a different route.

## Admin dashboard widget
The plugin registers a dashboard widget via `wp_dashboard_setup` and displays in:
- `Dashboard > Home` (`/wp-admin/index.php`)

Widget title:
- `Pickup Mtaani - Shipments In Transit`

## Changelog
### 1.0.0
- Initial WooCommerce integration
- Shipping method support
- Shipment creation and tracking hooks

### Current implementation updates
- Added API key verification before activation
- Added explicit validation error reason on save
- Added checkout delivery option: pickup agent or doorstep dropoff
- Removed sandbox environment from settings and runtime (production only)

## Support
- Email: `emkiarie0@gmail.com`
- GitHub: `https://github.com/Eric-Muthemba/Pick-Up-Mtaani-WooCommerce-Plugin`

## License
GPL-3.0
