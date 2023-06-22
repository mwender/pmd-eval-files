# PickUpMyDonation.com Eval-Files

This is a collection of PHP scripts to run under the WP CLI using `wp eval-file`.

## Contents

- chhj-stats.php - Displays and stores the number of donations sent to the CHHJ API.

## Changelog

### 1.3.0

- Adding `export-orphaned-providers.php` for exporting a CSV of all Orphaned Providers.

### 1.2.0

- Adding `list-orgs.php` for displaying a list of Organizations with their "Pick Up DOW".

### 1.1.0

- Removing $query_type in favor of one query which retrieves all Non-Priority and Priority donations

### 1.0.1

- Allowing `chhj-stats.php` to run without passing any arguments. If no arguments are passed, we assume we are running stats for the current month.

### 1.0.0

- Initial release with `chhj-stats.php`.