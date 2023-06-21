<?php
/**
 * List PMD orgainizations.
 *
 * @author     MWENDER
 * @since      2023
 */
$orgs = get_posts([
  'post_type'       => 'organization',
  'post_status'     => 'publish',
  'posts_per_page'  => -1,
  'orderby'         => 'title',
  'order'           => 'ASC',
]);

if( $orgs ):
  foreach ($orgs as $org ) {
    $pickup_dow = get_field( 'pickup_settings_pickup_dates', $org->ID );
    $days_of_week = implode( ', ', $pickup_dow );
    if( 'Monday, Tuesday, Wednesday, Thursday, Friday, Saturday' == $days_of_week || empty( $days_of_week ) )
      $days_of_week = '-default-';
    $rows[] = [
      'ID'          => $org->ID,
      'Name'        => $org->post_title,
      'Pick Up DOW' => $days_of_week,
    ];
  }
  WP_CLI\Utils\format_items( 'table', $rows, 'ID,Name,Pick Up DOW' );
endif;