# Plugin for YOURLS: API ShortURL Analytics

[![Plugin Name](https://img.shields.io/badge/Plugin%20Name-ShortURL%20Analytics-blue)](https://github.com/stefanofranco/yourls-api-shorturl-analytics)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Description

Yourls API ShortURL Analytics is a plugin for Yourls that provides analytics for short URLs via a custom API action. This plugin allows users to retrieve detailed statistics for short URLs within a specified date range.

## Requirements

This plugin requires YOURLS version 1.7.3 and above and Php >= 7.0.

**Note**: Please, if you've tested it with other versions and it works fine, contact me and I will update this section.


## Installation
Download the latest release from the releases page.
Extract the contents to a folder named `yourls-api-shorturl-analytics` within the `/user/plugins` directory of your Yourls installation.
Activate the plugin from the Plugins administration page (http://yourls-site.com/admin/plugins.php).
You're ready to go!
License
This package is licensed under the MIT License.

## Usage

This plugin extends Yourls API functionality to include a custom action for retrieving analytics data for short URLs. The action can be accessed through the endpoint `/yourls-api.php`, and requires the following parameters:

- `date`: The start date for the analytics data (required).
- `date_end`: The end date for the analytics data (optional, defaults to the start date if not provided).
- `shorturl`: The short URL for which analytics data is requested (required).

### Example - Stats for a specific date

```bash
curl -X GET "http://yourls-site.com/yourls-api.php?signature=YourSignature&action=shorturl_analytics&date=2024-01-01&shorturl=abc123"
```
```bash
# Sample result for a specific date
# Parameters: date = 2024-01-01
{
  "statusCode": 200,
  "message": "success",
  "stats": {
    "total_clicks": 15, # Total clicks (lifetime)
    "range_clicks": 15, # Total clicks (in the range)
    "daily_clicks": {
      "2024-01-01": 15 # Total clicks (in the specific date)
    }
  }
}
```

### Example - Stats for a date range

```bash
curl -X GET "http://yourls-site.com/yourls-api.php?signature=YourSignature&action=shorturl_analytics&date=2024-01-01&date_end=2024-12-31&shorturl=abc123"
```

```bash
# Sample result for a date range
# Parameters: date = 2024-01-01, date_end = 2024-01-05
{
    "statusCode": 200,
    "message": "success",
    "stats": {
        "total_clicks": 120, # Total clicks (lifetime)
        "range_clicks": 24, # Total clicks (in the range)
        "daily_clicks": {
          "2024-01-01": 15 # Total clicks (in the specific date)
          "2024-01-02": 8,
          "2024-01-03": 1,
          "2024-01-04": 0,
          "2024-01-05": 0
        }
    }
}
```

## Author
Created by [Stefano Franco](https://github.com/stefanofranco/yourls-api-shorturl-analytics).
