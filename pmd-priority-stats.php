<?php
/**
 * This file implements Priority Donation statistics. Run it with `help` as
 * the first positional argument to output the help and exit.
 *
 * @author     MWENDER
 * @since      2024
 */
if( isset( $args[0] ) && in_array( $args[0], [ 'help', 'h' ] ) ){
  WP_CLI::line( "This script accepts 3 positional arguments:\n\n  1. \$org_key - Must be either `chhj` or `1800gj`.\n  2. \$date - specify the month for the query in YYYY-MM. When empty, defaults to the current month.\n  3. \$list_donations (bool, optional) Output a list of the donations.");
  exit();
}

/**
 * 1st POSITIONAL ARG - $organization
 *
 * Specify the organization.
 *
 * @var        bool
 */
$org_key = ( ! empty( $args[0] ) && in_array( $args[0], [ 'chhj', '1800gj' ] ) )? $args[0] : false ;
$organizations = [
  'chhj' => [ 'id' => 511971, 'name' => 'College Hunks Hauling Junk', 'api_method' => 'chhj_api' ],
  '1800gj' => [ 'id' => 521689, 'name' => '1-800-GOT-JUNK?', 'api_method' => '1800gj_api' ],
];
if( $org_key ){
  $org_name =  $organizations[ $org_key ]['name'];
  $org_id =  $organizations[ $org_key ]['id'];
  $org_api_method =  $organizations[ $org_key ]['api_method'];
  WP_CLI::line( '⚙️ Organization: ' . $org_name );
} else {
  WP_CLI::error( 'Please specify your org key (chhj or 1800gj) with the 2nd positional argument.' );
}

/**
 * 2nd POSITIONAL ARG - $date
 *
 * Use the 2nd positional argument to specify the $date for our stats in YYYY-MM format.
 *
 * @var        string
 */
$date = ( isset( $args[1] ) )? $args[1] : null ;
if( empty( $date ) ){
  $timestamp = current_time( 'mysql' );
  $dateObj = date_create( $timestamp );
  $date = date_format( $dateObj, 'Y-m');
}
WP_CLI::line( '👉 $date = ' . $date );
if( ! stristr( $date, '-' ) || 7 != strlen( $date ) || empty( $date ) || '-' != substr( $date, 4, 1 ) )
  WP_CLI::error( '🚨 Please provide a month in the format YYYY-MM as the first positional argument when calling this file.');

$date_array = explode( '-', $date );
$year = $date_array[0];
$month = $date_array[1];

WP_CLI::line( '$year = ' . $year . "\n" . '$month = ' . $month );

/**
 * 3ND POSITIONAL ARG - $list_donations
 *
 * Output the list of donations by setting $list_donations to TRUE.
 *
 * @var        bool
 */
$list_donations = ( ! empty( $args[2] ) && in_array( $args[2], [ 'TRUE', 'true', '1' ] ) )? true : false ;
if( $list_donations )
  WP_CLI::line( '👉 We are listing donations...' );

$args = [
  'post_type'       => 'donation',
  'order'           => 'ASC',
  'orderby'         => 'ID',
  'posts_per_page'  => -1,
  'date_query'  => [
    [
      'year' => $year,
      'month' => $month,
    ],
  ],
  'meta_query'  => [
    [
      'key'     => 'api_method',
      'value'   => $org_api_method,
      'type'    => 'CHAR',
      'compare' => '=',
    ],
  ],
];

$donations = get_posts( $args );

if( $list_donations ){
  //WP_CLI::line( '👋 Listing donations where organization name is NOT `PickUpMyDonation.com`:' );
  WP_CLI::line( 'Building Table...' );
  $rows = [];
  $row = 1;
  foreach ( $donations as $donation ) {
    $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
    //if( 'PickUpMyDonation.com' != $organization_name ):
      $sanitized_title = str_replace([ '&#8211;', '&amp;' ], [ '-', '&' ], get_the_title( $donation->ID ) );
      //WP_CLI::line( '📦 #' . $donation->ID . ' ' . $sanitized_title . ' - ' . $organization_name );
      $response_code = get_post_meta( $donation->ID, 'api_response_code', true );
      $response_message = get_post_meta( $donation->ID, 'api_response_message', true );
      if( empty( $response_code ) )
        $response_code = 'EMPTY';
      //*
      if( 200 == $response_code ){
        $response_code = '✅ ' . $response_code;
      } else {
        $response_code = '🚨 ' . $response_code;
      }
      /**/
      $rows[] = [
        'No.' => $row,
        'ID' => $donation->ID,
        'Date' => get_the_date( 'Y-m-d H:i:s', $donation->ID ),
        'Code' => $response_code,
        'Message' => $response_message,
        'API Method'  => get_post_meta( $donation->ID, 'api_method', true ),
        'Title' => substr( $sanitized_title, 0, 40 ),
        'Organization' => $organization_name,
      ];
    //endif;
    $row++;
  }
  WP_CLI\Utils\format_items( 'table', $rows, 'No.,ID,Date,Code,Message,API Method,Title,Organization' );
}
$donation_counts = [
  'priority'      => 0,
  'non-priority'  => 0,
];

$fails = 0;
foreach ( $donations as $donation ) {
  $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
  $api_response = get_post_meta( $donation->ID, 'api_response', true );
  $response_code = get_post_meta( $donation->ID, 'api_response_code', true );
  $response_message = get_post_meta( $donation->ID, 'api_response_message', true );
  if( 200 != $response_code  ){
    $fails++;
    WP_CLI::line('👉 #' . $donation->ID . ' ' . $organization_name . ' (' . $response_code . ' - ' . $response_message . ').' );
  }
  if( 'PickUpMyDonation.com' == $organization_name ){
    $donation_counts['non-priority']++;
  } else {
    $donation_counts['priority']++;
  }
}

$failure_rate = ($fails/( $donation_counts['non-priority'] + $donation_counts['priority'] ) ) * 100;
$success_rate = 100 - $failure_rate;
$success_rate_percentage = number_format( $success_rate, 2 );

$stats = [];
$stats[] = [
  'Month'         => $date,
  'Total'         => $donation_counts['non-priority'] + $donation_counts['priority'],
  'Non-Priority'  => $donation_counts['non-priority'],
  'Priority'      => $donation_counts['priority'],
  'Fails'         => $fails,
  'Success Rate'  => $success_rate_percentage . '%',
];
WP_CLI\Utils\format_items( 'table', $stats, 'Month,Total,Non-Priority,Priority,Fails,Success Rate' );
WP_CLI::line( 'NOTE: Success Rate is calculated by dividing fails by the total number of Non-Priority and Priority donations and subtracting from 100%.' );

$donation_stats_option = get_option( "{$org_key}_donations" );
if( ! is_array( $donation_stats_option ) )
  $donation_stats_option = [];

$donation_stats_option[ $date ] = [
  'non-priority'  => $donation_counts['non-priority'],
  'priority'      => $donation_counts['priority'],
  'fails'         => $fails,
  'success_rate_percentage' => $success_rate_percentage,
];
update_option( "{$org_key}_donations", $donation_stats_option );
