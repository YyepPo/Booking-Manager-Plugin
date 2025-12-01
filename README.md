# Booking Manager

A simple, lightweight booking manager plugin for WordPress. This plugin provides a frontend booking form (shortcode), an admin booking list, and basic booking notifications. It's intentionally small and designed to be easy to extend.

**Showcase Video**

- Watch the demo: [https://www.youtube.com/watch?v=REPLACE_WITH_YOUR_VIDEO_ID](https://youtu.be/rO0MPo4K1K8)

**Quick Overview**

- **Plugin folder / slug:** `booking-manager`
- **Shortcode:** `[bm_booking_form]` — insert on any page or post to show the booking form
- **CPT:** `bm_booking` — bookings are stored as a custom post type

**Features**

- Frontend booking form with name, email, date, time, and service fields
- Stores bookings in the WordPress admin under a Bookings list
- Sends a basic notification to the site administrator when a booking is created
- Small codebase intended for easy customization and extension

**Installation**

1. Copy the plugin folder to `wp-content/plugins/booking-manager-lite`
2. Activate the plugin from the WordPress admin Plugins screen

**Usage**

1. Edit or create a Page/Post where you want the form
2. Add the shortcode:

```
[bm_booking_form]
```

3. Publish the page and open it to submit a booking

**Extending & Customization Ideas**

- Add an admin settings page to configure notification behavior and templates
- Add customer confirmation emails and HTML templates
- Integrate calendar providers (Google Calendar, Outlook) for automatic event creation
- Add availability/slot management to prevent double-bookings
- Add REST API endpoints for headless or mobile usage

**Developer notes**

- Main plugin file: `booking-manager.php`
- Frontend JS: `bm-frontend.js`
- Frontend CSS: `bm-frontEnd.css`

**Contributing**

Contributions welcome. Open issues for bugs or feature requests and submit pull requests for changes. Keep PRs focused and include a short explanation of the change.

**License**

This project is released under the MIT License. See `LICENSE` for details.
