<?php
/**
 * Generate CSV of Orphaned Providers
 *
 * @author     MWENDER
 * @since      2023
 */
global $wpdb;
$results = $wpdb->get_results( "SELECT store_name,zipcode,email_address,receive_emails,priority,last_donation_report FROM {$wpdb->prefix}donman_contacts ORDER BY store_name ASC", OBJECT );
if( $results ):
  $rows = [];
  //$rows[] = [ 'store_name,zipcode,email_address,receive_emails,priority,last_donation_report' ];
  foreach ( $results as $result ) {
    $rows[] = [
      'store_name' => $result->store_name,
      'zipcode' => $result->zipcode,
      'email_address' => $result->email_address,
      'receive_emails' => $result->receive_emails,
      'priority' => $result->priority,
      'last_donation_report' => $result->last_donation_report,
    ];
  }

  $filename = 'orphaned-providers.csv';
  $fp = fopen( $filename, 'w' );
  fputcsv( $fp, array_keys( $rows[0] ) );
  foreach( $rows as $row ){
    fputcsv( $fp, $row );
  }
  fclose( $fp );
endif;