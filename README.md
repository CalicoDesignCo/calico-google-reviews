# Calico Google Reviews

Version 1.14.0. A WordPress plugin for collecting, importing, managing and displaying Google reviews with Elementor widgets and multiple location profiles.

## Installation

Download the installable ZIP attached to the release, then use WordPress Plugins > Add Plugin > Upload Plugin. Requires WordPress 6.2 or later and PHP 7.4 or later.

## Configuration

Version 1.12.0 adds native WordPress updates from stable public GitHub releases. Manually upload this version once if upgrading from 1.11.0 or earlier. Future releases appear through Dashboard > Updates > Check again. Automatic update installation remains under the administrator's control. Checks use cached public metadata with no GitHub token; unavailable or invalid metadata leaves the installed plugin usable.

Use Google Reviews > Sources to configure Outscraper API and location profiles. Manual entry and CSV import/export are also available. Full setup details and version history are in [readme.txt](readme.txt).

Elementor includes Calico Google Reviews for cards, masonry or carousel layouts, pagination, and custom card elements; Calico Review Summary independently displays review totals, average rating, stored five-star counts and review links.

## Background imports

Outscraper submissions now use asynchronous requests. Sources shows each location's saved request ID, status and import counts. WordPress cron checks pending results about every minute, so you can leave the page. **Check saved request now** retrieves the existing job, without launching a replacement scrape. Completed results use the existing duplicate checks. No new scrape is automatically submitted after an uncertain submission or a failed job; check provider Usage History before confirming **Start a new request**.

Working WordPress cron and site traffic (or a hosting cron) are required. Provider results expire, so checks must run promptly; checks stop after 24 hours if a job never completes. Polls have bounded timeouts, respect credential pause and expiration, and do not reserve another scrape's monthly budget. Requests that timed out in older plugin versions have no locally saved ID and are not automatically recovered. The initial submission still requires a successful API response containing an ID; background retrieval cannot repair a provider outage that prevents submission itself.

## Credential protection

Credential Protection can be viewed, saved, disabled or renewed only by the designated owner's WordPress account with administrator permissions. The notification recipient and WordPress Administration Email Address do not grant access to these controls. Account email changes, server access and database access can bypass this local restriction.

Enable Google Reviews > Credential Protection to start a renewable 90-day Outscraper credential lease. Defaults are 100 requested reviews per pull, five pulls per UTC calendar month, and 500 requested reviews per UTC calendar month across all locations. Settings are inactive until explicitly enabled. Save with Refresh access checked to renew; ordinary saves do not extend the deadline. After expiration, renew and enter the key again on Sources. Stored reviews remain available.

Notification email defaults to the WordPress Administration Email Address, with an optional override. WordPress cron and mail delivery must work for background checks and warning messages. Expiration is also enforced before API calls. Request budgets include failures/timeouts and do not guarantee provider billing. Anyone controlling the server can alter local protection; backups may retain credentials.

## Verification

The repository includes isolated WordPress Playground tests for review behavior, asynchronous job persistence/retries, duplicate prevention, location isolation, credential policies, package activation and GitHub-backed updates. API responses and email delivery are mocked in regression tests. A passing local test does not prove live provider availability, production cron execution or email delivery.

## License

GPL-2.0-or-later, as declared in the plugin header and readme.txt.
