<?php
/*
Plugin Name: API ShortURL Analytics
Plugin URI: https://github.com/stefanofranco/yourls-api-shorturl-analytics
Description: This plugin defines a custom API action 'shorturl_analytics'
Version: 1.0.0
Author: Stefano Franco
Author URI: https://github.com/stefanofranco/
*/
require __DIR__ . '/../../../includes/vendor/autoload.php';
use WhichBrowser\Parser;

yourls_add_filter('api_action_shorturl_analytics', 'shorturl_analytics');

/**
 * @return array
 * @throws Exception
 */
function shorturl_analytics(): array
{
    try {
        validations();
    } catch (\Throwable $e) {
        return [
            'statusCode' => 400,
            'message'    => $e->getMessage(),
        ];
    }
    $date_start = $_REQUEST['date'];
    $date_end   = $_REQUEST['date_end'] ?? $date_start;
    $shorturl   = $_REQUEST['shorturl'];
    $stats = extractStats($shorturl, $date_start, $date_end);
    return [
        'statusCode' => 200,
        'message'    => 'success',
        'stats'     => $stats
    ];
}

/**
 * @throws \Exception
 */
function validations() {
    // The date parameter must exist
    if( !isset( $_REQUEST['date'] ) ) {
        throw new Exception("Missing date parameter");
    }
    $date_start = $_REQUEST['date'];
    $date_end = $_REQUEST['date_end'] ?? $date_start;

    // Check if the date format is right
    if (
        !checkDateFormat($date_start) ||
        !checkDateFormat($date_end)
    ) {
        throw new Exception("Wrong date format");
    }

    // Check if "date_end" is not smaller than "date_start"
    if( $date_end < $date_start ) {
        throw new Exception('The date_end parameter cannot be smaller than date');
    }

    // Need 'shorturl' parameter
    if( !isset( $_REQUEST['shorturl'] ) ) {
        throw new Exception('Missing shorturl parameter');
    }
    $shorturl = $_REQUEST['shorturl'];

    // Check if valid shorturl
    if( !yourls_is_shorturl( $shorturl ) ) {
        throw new Exception("Not Found");
    }
}

/**
 * @throws Exception
 */
function extractStats($shorturl, $date_start, $date_end = null)
{
    global $ydb;
    $table_log = YOURLS_DB_TABLE_LOG;
    if (empty($date_start)) {
        return [];
    }

    $date_end = ($date_end ?? $date_start);
    $datesRange = getDateRange($date_start, ($date_end ?? $date_start));

    // Date must be in YYYY-MM-DD format
    $date_start .= ' 00:00:00';
    $date_end .= ' 23:59:59';

    $sql_count_total = "SELECT
    COUNT(*) AS count
    FROM $table_log
    WHERE `shorturl` = :shorturl
    ";

    $sql_count_by_day = "SELECT
    DATE(`click_time`) AS date
    , COUNT(*) AS count
    FROM $table_log
    WHERE `shorturl` = :shorturl
    AND `click_time` BETWEEN :date_start AND :date_end
    GROUP BY `date`
    ";

    $sql_devices = "SELECT
    user_agent 
    FROM `$table_log` 
    WHERE shorturl=:shorturl
    ";

    try {
        // Count total numbers of click
        $total_clicks = $ydb->fetchOne($sql_count_total, ['shorturl' => $shorturl]);
        // Count total numbers of click for any date in the range
        $daily_clicks = $ydb->fetchPairs($sql_count_by_day, ['shorturl' => $shorturl, 'date_start' => $date_start, 'date_end' => $date_end]);
        // Get User Agents
        $ua_clicks = $ydb->fetchObjects($sql_devices, ['shorturl' => $shorturl]);
        $devicesStats = dissectUserAgent($ua_clicks);
    } catch (\Throwable $e) {
        var_dump($e->getMessage()); die;
    }

    $results = [
        'total_clicks' => (int) $total_clicks[array_key_first($total_clicks)],
        'range_clicks' => 0,
        'daily_clicks' => [],
        'clicks_by_device' => $devicesStats['device'],
        'clicks_by_browser' => $devicesStats['browser'],
        'clicks_by_platform' => $devicesStats['platform']
    ];
    foreach ($datesRange as $date) {
        $results['daily_clicks'][$date] = (int) ($daily_clicks[$date] ?? 0);
        $results['range_clicks'] += $results['daily_clicks'][$date];
    }

    return $results;
}

/**
 * Check if a date is in the format 'Y-m-d'.
 *
 * @param string $date The date to check for.
 * @param string $format
 * @return bool True if $date format is equal to the one specified by $format
 * (default: 'Y-m-d'). Otherwise, the function returns False.
 */
function checkDateFormat($date, $format='Y-m-d'): bool
{
    $dateObject = DateTime::createFromFormat($format, $date);
    return $dateObject && $dateObject->format($format) === $date;
}

/**
 * @throws Exception
 */
function getDateRange($startDate, $endDate): array
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end = $end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval ,$end);

    $results = [];
    foreach ($dateRange as $date) {
        $results[] = $date->format('Y-m-d');
    }

    return $results;
}

function dissectUserAgent(array $clicks_logs) : array
{
    $result_array = [
        'device'    => [],
        'browser'   => [],
        'platform' => []
    ];

    if (!$clicks_logs) {
        return $result_array;
    }

    $DEVICE_DATASERIES = [];
    $BROWSER_DATASERIES = [];
    $PLATFORMS_DATASERIES = [];

    foreach ($clicks_logs as $click_log) {
        if (!property_exists($click_log, 'user_agent')) { continue; }
        // Parse user agent
        $wbresult = new Parser($click_log->user_agent);
        $DEVICE_DATASERIES      = analytics_count_distinct_categories($wbresult->device->type, $DEVICE_DATASERIES);
        $BROWSER_DATASERIES     = analytics_count_distinct_categories($wbresult->browser->name, $BROWSER_DATASERIES);
        $PLATFORMS_DATASERIES   = analytics_count_distinct_categories($wbresult->os->name, $PLATFORMS_DATASERIES);
    }
    arsort($DEVICE_DATASERIES);
    arsort($BROWSER_DATASERIES);
    arsort($PLATFORMS_DATASERIES);

    return [
        'device' => $DEVICE_DATASERIES,
        'browser' => $BROWSER_DATASERIES,
        'platform' => $PLATFORMS_DATASERIES
    ];
}

function analytics_count_distinct_categories(?string $category_name, array $counter) {
    $category_name ??= '';
    $category_name = $category_name === '' ? 'Unknown' : ucfirst($category_name);
    if (!key_exists($category_name, $counter)) {
        $counter[$category_name] = 0;
    }
    $counter[$category_name]++;
    return $counter;
}
