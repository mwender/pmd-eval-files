<?php
/**
 * This file implements chhj statistics. Run it with `help` as
 * the first positional argument to output the help and exit.
 *
 * @author     MWENDER
 * @since      2023
 */
if( isset( $args[0] ) && in_array( $args[0], [ 'help', 'h' ] ) ){
  WP_CLI::line( "This script accepts 2 positional arguments:\n\n  • \$date - specify the month for the query in YYYY-MM. When empty, defaults to the current month.\n  • \$list_donations - When TRUE, lists all donations NOT belonging to `PickUpMyDonation.com`.");
  exit();
}

/**
 * 1ST POSITIONAL ARG - $date
 *
 * Use the 1st positional argument to specify the $date for our stats in YYYY-MM format.
 *
 * @var        string
 */
$date = ( isset( $args[0] ) )? $args[0] : null ;
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
 * 2ND POSITIONAL ARG - $list_donations
 *
 * Output the list of donations by setting $list_donations to TRUE.
 *
 * @var        bool
 */
$list_donations = ( ! empty( $args[1] ) && in_array( $args[1], [ 'TRUE', 'true', '1' ] ) )? true : false ;
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
    'relation'  => 'OR',
    /*
    [
      'key'     => '_organization_name',
      'value'   => 'College Hunks',
      'type'    => 'CHAR',
      'compare' => 'LIKE',
    ],
    [
      'key'     => '_organization_name',
      'value'   => 'PickUpMyDonation.com',
      'type'    => 'CHAR',
      'compare' => '=',
    ],
    /**/
    [
      'key'     => 'api_response',
      'compare' => 'EXISTS',
    ],
  ],
];

$donations = get_posts( $args );

if( $list_donations ){
  WP_CLI::line( '👋 Listing donations where organization name is NOT `PickUpMyDonation.com`:' );
  WP_CLI::line( 'Building Table...' );
  $rows = [];
  $row = 1;
  foreach ( $donations as $donation ) {
    $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
    if( 'PickUpMyDonation.com' != $organization_name ):
      WP_CLI::line( '📦 #' . $donation->ID . ' ' . get_the_title( $donation->ID ) . ' - ' . $organization_name );
      $response_code = get_post_meta( $donation->ID, 'api_response_code', true );
      if( empty( $response_code ) )
        $response_code = '🚨 EMPTY';
      $rows[] = [
        'No.' => $row,
        'ID' => $donation->ID,
        'Date' => get_the_date( 'Y-m-d H:i:s', $donation->ID ),
        'Response Code' => $response_code,
        'Title' => str_replace([ '&#8211;', '&amp;' ], [ '-', '&' ], substr( get_the_title( $donation->ID ), 0, 40 ) ),
        'Organization' => $organization_name,
      ];
    endif;
    $row++;
  }
  WP_CLI\Utils\format_items( 'table', $rows, 'No.,ID,Date,Response Code,Title,Organization' );
}
$donation_counts = [
  'priority'      => 0,
  'non-priority'  => 0,
];

$fails = 0;
foreach ( $donations as $donation ) {
  $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
  $api_response = get_post_meta( $donation->ID, 'api_response', true );
  if( stristr( $api_response, 'cURL Error' ) ){
    $fails++;
    WP_CLI::line('👉 ' . $fails . '.' . $organization_name . ' (' . $response_code . ') $api_response = ' . $api_response );
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

$donation_stats_option = get_option( 'chhj_donations' );
if( ! is_array( $donation_stats_option ) )
  $donation_stats_option = [];

$donation_stats_option[ $date ] = [
  'non-priority'  => $donation_counts['non-priority'],
  'priority'      => $donation_counts['priority'],
  'fails'         => $fails,
  'success_rate_percentage' => $success_rate_percentage,
];
update_option( 'chhj_donations', $donation_stats_option );
