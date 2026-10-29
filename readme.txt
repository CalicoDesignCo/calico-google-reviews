=== Calico Google Reviews ===
Contributors: calicodesign
Tags: google reviews, places, testimonials
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.12.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect, import, manage, back up, and display Google reviews from Google Places API, Outscraper API, CSV, or manual entry.

== Description ==

Calico Google Reviews provides a dedicated review library and two API modes:

* Native multi-location profiles keep each business location's credentials, review feed, schedule, status, aggregate details, and review-request link separate.
* Google Places API rolling collection checks Google's sample every seven days and retains newly exposed reviews. The initial response is limited to five reviews, making it useful for testing but not backlog collection.
* Outscraper API full import retrieves complete review histories using a customer-supplied provider key and Place ID. Outscraper offers 500 free credits per month; one pulled review uses one credit, so most businesses will remain within the free allowance.
* Manual entry and CSV upload provide an API-free fallback with duplicate prevention.
* Export All Reviews downloads a complete CSV backup designed to re-import without losing identifiers, sources, dates, reviewer details, replies, photos, or source links.

Use the shortcode:

`[calico_google_reviews location="primary" limit="6" template="classic" columns="3" columns_tablet="2" columns_mobile="1" show_summary="yes"]`

Templates are `classic`, `minimal`, `quote`, and `spotlight`. The optional `source` attribute accepts `all`, `places`, `outscraper`, `csv`, or `manual`. Use the location ID shown by the location profile, or `location="all"` for a combined feed.

When Elementor is active, use the Calico Google Reviews widget for responsive columns and visual controls for every review element.

The separate Calico Review Summary widget can independently show or hide the total review count, average rating, stored 5-star review count, Google reviews link, and leave-a-review link. Both links support custom text and optional URL overrides, and the widget includes horizontal or vertical layouts plus container, value, label, and link styling controls.

The Elementor widget can paginate stored reviews above or below the cards, or append configurable batches through an external Elementor Button selected by CSS ID. Pagination includes visual styling controls.

Carousel mode displays up to 30 reviews in a single sliding row. The responsive column settings control how many slides are visible, with optional dots above or below, optional side arrows, touch swiping, mouse dragging, and detailed navigation state and positioning controls.

Global display settings can limit front-end output by star rating, hide reviews without written text, and collapse long reviews behind a configurable Read more control. Elementor can independently show or hide the business name and aggregate rating area, use equal-height or masonry cards, and build a custom card by dragging Avatar, Name, Date, Stars, Review, Response from Business, and View on Google elements into any order.

== Google Places API setup ==

1. In Google Cloud, enable Google Places API (New) for a billing-enabled project.
2. Create and appropriately restrict an API key.
3. In WordPress, open Google Reviews > Sources.
4. Add or select a location, enter its Place ID and API key, and save.
5. Use Test Place connection, then add the shortcode to a page.

Google Places API returns a small sample. Rolling collection may accumulate newly exposed reviews over time, but it does not guarantee a complete history.

== Outscraper API setup ==

1. Create an Outscraper account and API key.
2. Open Google Reviews > Sources.
3. Enter the API key and Place ID.
4. Choose the maximum review count and an automatic schedule: Weekly (default), Daily, or Hourly.
5. Save and click Import from Outscraper API now. Weekly pulls are recommended to remain below the 500-credit monthly allowance; hourly and daily pulls may lead to charges.

== Data and privacy ==

Administrators can enable Google Reviews > Credential Protection to start a renewable 90-day Outscraper credential lease. Defaults are 100 requested reviews per pull, five pulls per UTC calendar month, and 500 requested reviews per UTC calendar month across all locations. Settings are inactive until explicitly enabled. Save with Refresh access checked to renew; ordinary saves do not extend the deadline. After expiration, renew and enter the key again on Sources. Existing reviews remain available.

Notification email defaults dynamically to the WordPress Administration Email Address; enter an override to use a different address. A warning is sent within 30 days of expiration and another when access expires. Background checks and notifications depend on WP-Cron and configured WordPress mail delivery. Expiration is also checked immediately before API calls. Requests including failures/timeouts consume reserved usage; this is a local request budget, not a billing guarantee. Server administrators can modify local restrictions, and backups may retain credentials.

API keys are encrypted using keys derived from the WordPress authentication salts. Changing those salts invalidates saved credentials and requires saving the keys again.

All imported and collected reviews are stored in the WordPress database. API credentials are encrypted. Uninstalling the plugin removes its settings, credentials, scheduled events, and review table.

The plugin sends requests to these Google services when their corresponding mode or administrative action is used:

* places.googleapis.com
* api.outscraper.com

Review displays include per-review source links where Google or an import supplies them.

== Changelog ==

= 1.12.1 =
* Restricted Credential Protection visibility, saving and renewal to the designated owner's administrator account. Notification email overrides do not grant management access.

= 1.12.0 =
* Added native WordPress update checks for stable public GitHub releases, using the exact versioned plugin ZIP and cached metadata.
* Added plugin details and Check again refresh support. Install this version manually once to enable future Dashboard updates; earlier versions have no updater.

= 1.11.0 =
* Added administrator-only Credential Protection with explicit enablement, renewable expiration, credential erasure, and a pause switch for all Outscraper locations.
* Added per-pull and UTC monthly request/review budgets enforced for manual and scheduled requests, with serialized usage reservations.
* Added expiration warnings and expired-access emails using the WordPress Administration Email Address by default, with a custom recipient option.

= 1.10.0 =
* Added a Calico Review Summary Elementor widget with independent toggles for total reviews, average rating, stored 5-star reviews, Google reviews link, and leave-a-review link.
* Added custom labels and link text, horizontal and vertical layouts, and Elementor styling controls for the summary container, values, labels, and links.
* Made review summary data location-aware, including combined totals and weighted average ratings for All locations.

= 1.9.1 =
* Fixed unset Elementor responsive column values defaulting every layout to one column.
* Added defensive 3-column desktop, 2-column tablet, and 1-column mobile fallbacks for existing widgets.

= 1.9.0 =
* Added native multi-location management with separate source credentials, schedules, status, aggregate details, and review-request links.
* Added an Elementor Location selector for individual-location pages and combined All locations feeds.
* Added location assignment to manual reviews and standard CSV imports, plus a location filter on the Reviews screen.
* Replaced single-location cron events with hourly due checks that honor each location's weekly, daily, or hourly schedule.
* Migrates the existing configuration into a Primary Location and recognizes legacy review keys to prevent duplicate imports after upgrading.

= 1.8.1 =
* Changed masonry review placement from top-to-bottom columns to chronological left-to-right row order.
* Recalculates masonry card placement after responsive resizing, images, Read More changes, pagination, and Load More updates.

= 1.8.0 =
* Added permanent Delete ALL Reviews with an export-first warning and direct backup link.
* Added duplicate scanning and cleanup that keeps the newest, most complete review.
* Prevented Google Places API, Outscraper API, CSV, and manual imports from storing duplicate reviews.
* Added left, center, and right Elementor pagination alignment.
* Added carousel automatic scrolling, selectable timing, and a shared slides-per-scroll control for autoplay and arrows.
* Fixed Read More and other interactive review-card controls inside carousels.
* Expanded the Outscraper API review-limit guidance for initial backlog imports and ongoing pulls.

= 1.7.0 =
* Added an Elementor sliding-carousel display with a 30-review maximum and responsive slides-per-view based on the existing column settings.
* Added optional dots above or below the carousel and optional side arrows.
* Added dormant, hover, and active colors plus size, border, radius, spacing, and responsive position controls for dots and arrows.
* Added touch swiping, mouse dragging, responsive recalculation, and reduced-motion support.

= 1.6.0 =
* Added Elementor pagination with top or bottom placement, page numbers, previous/next labels, and complete style controls.
* Added external Elementor Button integration for loading configurable batches of additional reviews without a page refresh.
* Added automatic Place ID review-request links with an official-link override, Copy Link, Test Link, and setup instructions.
* Updated user-facing source names to Google Places API and Outscraper API.
* Added Outscraper's 500-free-credit monthly guidance and updated synchronization warnings.

= 1.5.0 =
* Added Export All Reviews with a lossless CSV format that can be re-imported without duplicates.
* Changed Google Places API rolling collection from every five minutes to every seven days.
* Clarified the five-review Google Places API limit and the full-history Outscraper API option.
* Restricted Outscraper API setup to Place IDs and removed URL-related guidance.
* Removed the Business Profile OAuth connection, synchronization, settings, and instructions.

= 1.4.0 =
* Removed the standalone source footer and link underline hover effects.
* Renamed Import to Manual Import.
* Added linked setup guides, Place ID instructions, and official resources.
* Updated Outscraper API source configuration.
* Added Weekly, Daily, and Hourly Outscraper API synchronization schedules with usage warnings; Weekly is the default.
* Added Response from Business to the draggable custom Elementor card template.
* Added Business Reply background, radius, padding, spacing, color, and typography controls.

= 1.3.0 =
* Added a translatable global Read more label and per-widget Elementor override.
* Added Elementor typography, color, background, padding, radius, hover, and spacing controls for the Read more link.
* Added an Elementor toggle between relative review dates and formatted actual dates.

= 1.2.0 =
* Added global front-end filters for individual star ratings and written reviews only.
* Added configurable word limits with an expandable Read more control.
* Added Elementor business-name and aggregate-area visibility controls.
* Added draggable custom Elementor card templates with per-element visibility.
* Added equal-height and masonry card layouts.
* Removed the summary-level source link above review cards.

= 1.1.0 =
* Added a dedicated Google Reviews admin area with searchable, filterable, paginated review management and bulk deletion.
* Added manual entry and validated CSV import with duplicate prevention.
* Added five-minute Google Places API rolling collection and Outscraper API full-history import with encrypted credentials.
* Added an Elementor widget with responsive columns, element-level styling, and Classic Cards, Minimal, Quote, and Spotlight templates.
* Added source setup instructions and a unified shortcode/Elementor renderer.

= 1.0.0 =
* Initial release.
