<?php
$date = $args[0];
WP_CLI::line( '👉 $date = ' . $date );

$query_types = [ 'response_code', 'organization', 'api_response' ];
$query_type = ( ! empty( $args[1] ) && in_array( $args[1], $query_types ) )? $args[1] : 'api_response';
WP_CLI::line( '👉 $query_type = ' . $query_type );

$list_donations = ( ! empty( $args[2] ) && in_array( $args[2], [ 'TRUE', 'true', '1' ] ) )? true : false ;
if( $list_donations )
  WP_CLI::line( '👉 We are listing donations...' );

if( ! stristr( $date, '-' ) || 7 != strlen( $date ) || empty( $date ) || '-' != substr( $date, 4, 1 ) )
  WP_CLI::error( '🚨 Please provide a month in the format YYYY-MM as the first positional argument when calling this file.');

$date_array = explode( '-', $date );
$year = $date_array[0];
$month = $date_array[1];

WP_CLI::line( '$year = ' . $year . "\n" . '$month = ' . $month );

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
  ]
];
switch ( $query_type ) {
  case 'organization':
    $args['meta_key'] = '_organization_name';
    $args['meta_value'] = 'PickUpMyDonation.com';
    $args['meta_type']  = 'CHAR';
    break;

  case 'api_response':
    $args['meta_key'] = 'api_response';
    $args['meta_compare'] = 'EXISTS';
    break;

  default:
    $args['meta_key'] = 'api_response_code';
    $args['meta_value'] = 200;
    $args['meta_type'] = 'NUMERIC';
    break;
}

$donations = get_posts( $args );

if( $list_donations ){
  foreach ($donations as $donation ) {
    $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
    if( 'PickUpMyDonation.com' != $organization_name )
      WP_CLI::line( '📦 #' . $donation->ID . ' ' . get_the_title( $donation->ID ) . ' - ' . $organization_name );
  }
}
WP_CLI::success( count( $donations ) . ' donations sent to CHHJ in ' . $date );
$donation_counts = [
  'priority'      => 0,
  'non-priority'  => 0,
];
if( 'api_response' == $query_type ){
  foreach ( $donations as $donation ) {
    $organization_name = get_post_meta( $donation->ID, '_organization_name', true );
    if( 'PickUpMyDonation.com' == $organization_name ){
      $donation_counts['non-priority']++;
    } else {
      $donation_counts['priority']++;
    }
  }
  WP_CLI::line( '  - ' . $donation_counts['non-priority'] . ' non-priority' . "\n" . '  - ' . $donation_counts['priority'] . ' priority' );

  $donation_stats_option = get_option( 'chhj_donations' );
  if( ! is_array( $donation_stats_option ) )
    $donation_stats_option = [];

  $donation_stats_option[ $date ] = [
    'non-priority'  => $donation_counts['non-priority'],
    'priority'      => $donation_counts['priority'],
  ];
  update_option( 'chhj_donations', $donation_stats_option );
}

