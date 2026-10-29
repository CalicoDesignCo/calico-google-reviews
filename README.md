# Calico Google Reviews

Version 1.12.0. A WordPress plugin for collecting, importing, managing and displaying Google reviews with Elementor widgets and multiple location profiles.

## Installation

Download the installable ZIP attached to the release, then use WordPress Plugins > Add Plugin > Upload Plugin. Requires WordPress 6.2 or later and PHP 7.4 or later.

## Configuration

Version 1.12.0 adds native WordPress updates from stable public GitHub releases. Manually upload this version once if upgrading from 1.11.0 or earlier. Future releases appear through Dashboard > Updates > Check again. Automatic update installation remains under the administrator's control. Checks use cached public metadata with no GitHub token; unavailable or invalid metadata leaves the installed plugin usable.

Use Google Reviews > Sources to configure Google Places API, Outscraper API, and location profiles. Manual entry and CSV import/export are also available. Full setup details and version history are in [readme.txt](readme.txt).

Elementor includes Calico Google Reviews for cards, masonry or carousel layouts, pagination, and custom card elements; Calico Review Summary independently displays review totals, average rating, stored five-star counts and review links.

## Credential protection

Enable Google Reviews > Credential Protection to start a renewable 90-day Outscraper credential lease. Defaults are 100 requested reviews per pull, five pulls per UTC calendar month, and 500 requested reviews per UTC calendar month across all locations. Settings are inactive until explicitly enabled. Save with Refresh access checked to renew; ordinary saves do not extend the deadline. After expiration, renew and enter the key again on Sources. Stored reviews remain available.

Notification email defaults to the WordPress Administration Email Address, with an optional override. WordPress cron and mail delivery must work for background checks and warning messages. Expiration is also enforced before API calls. Request budgets include failures/timeouts and do not guarantee provider billing. Anyone controlling the server can alter local protection; backups may retain credentials.

## Verification

Local WordPress Playground functional smoke tests, credential policy regressions and activation of the installable v1.11.0 ZIP passed. Test email delivery was intercepted. No production-site upgrade or live email delivery was tested.

## License

GPL-2.0-or-later, as declared in the plugin header and readme.txt.
