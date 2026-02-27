# Pickup Mtaani - WooCommerce Plugin

**Version:** 1.0.0  
**Author:** Eric Muthemba Kiarie  
**License:** GPL-3.0

**Description:**  
This is a WooCommerce plugin designed to provide a seamless "local pickup" experience for Kenyan e-commerce stores. Customers can select nearby pickup points (“mtaani” meaning neighborhood in Swahili) at checkout, and merchants can manage multiple pickup locations across various neighborhoods. The plugin ensures a smooth logistics experience for both merchants and customers.

---

## Features

- **Neighborhood Pickup Locations**: Allow customers to select pickup points near their location.
- **Customizable Pickup Points**: Admins can create, update, and delete pickup points with details like address, working hours, and contact info.
- **WooCommerce Checkout Integration**: Pickup options are integrated directly into the WooCommerce checkout flow.
- **Delivery Fee Configuration**: Set custom pickup fees per location or globally.
- **Pickup Instructions**: Option to add custom instructions for each pickup point.
- **Neighborhood Filtering**: Customers see only the pickup points relevant to their selected city or region.
- **Notifications**: Send confirmation emails or SMS (if integrated) for selected pickup points.
- **Admin Dashboard**: View pickup orders, manage locations, and track usage metrics.
- **Lightweight & Extensible**: Built with performance and future integrations in mind.

---

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 8.0+

---

## Installation

### 1. Upload via WordPress Admin

1. Go to `Plugins > Add New > Upload Plugin`
2. Select the `pickup-mtaani.zip` file
3. Click `Install Now` and then `Activate`

### 2. Upload via FTP

1. Extract the plugin folder to `/wp-content/plugins/pickup-mtaani/`
2. Go to `Plugins` in WordPress Admin and activate the plugin

---

## Configuration

1. Navigate to `WooCommerce > Settings > Pickup Mtaani`
2. Add your pickup locations:
    - **Location Name** – Name of the pickup point
    - **Address** – Physical address
    - **Neighborhood/City** – For filtering at checkout
    - **Pickup Fee** – Optional fee for this location
    - **Operating Hours** – Optional
3. Enable “Pickup Mtaani” as a shipping method in WooCommerce:
    - `WooCommerce > Settings > Shipping > Shipping Zones`
    - Add a new method: **Pickup Mtaani**

---

## Usage

### Customer Checkout Flow

1. Customer selects a product and proceeds to checkout.
2. In the shipping method section, they select **Pickup Mtaani**.
3. A dropdown of nearby pickup points is displayed based on their city or neighborhood.
4. Customer selects their preferred pickup point and completes the order.

### Admin Management

- View orders with pickup location details in `WooCommerce > Orders`.
- Update pickup point availability or instructions as needed.

---

## Developer Notes

- The plugin is designed with extendibility in mind.
- Hooks & Filters:
    - `pickup_mtaani_get_locations` – Filter pickup locations dynamically.
    - `pickup_mtaani_checkout_display` – Modify how pickup options display on checkout.
- The plugin uses **custom post types** to manage pickup locations.

---

## Screenshots

1. **Pickup Locations Dashboard**
2. **Checkout Pickup Selection Dropdown**
3. **Pickup Point Settings**


---

## Frequently Asked Questions (FAQ)

**Q: Can I charge different fees for different pickup points?**  
A: Yes, the plugin supports per-location pickup fees.

**Q: Can I restrict pickup points by city?**  
A: Yes, the plugin filters locations based on the customer’s city or neighborhood.

**Q: Is the plugin compatible with multi-currency WooCommerce stores?**  
A: Yes, pickup fees will follow the store’s currency settings.

---

## Changelog

**1.0.0** – Initial release
- Added neighborhood pickup feature
- Integrated with WooCommerce checkout
- Added admin management of pickup locations

---

## Support

If you encounter any issues or need feature requests, please contact:  
**Email:** emkiarie0@gmail.com  
**GitHub:** [https://github.com/Eric-Muthemba/Pickup-Mtaani](https://github.com/Eric-Muthemba/Pick-Up-Mtaani-WooCommerce-Plugin)

---

## License

This plugin is licensed under the [GPL-3.0 License](https://www.gnu.org/licenses/gpl-3.0.html).  