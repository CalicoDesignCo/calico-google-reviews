<?php
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
register_shutdown_function( function () { $error = error_get_last(); if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR ), true ) ) { echo "\nTEST ERROR: " . wp_json_encode( $error ) . "\n"; } } );
require_once '/wordpress/wp-load.php';
require_once '/plugin/calico-google-reviews.php';
function job_assert( $test, $message ) { if ( ! $test ) { throw new RuntimeException( $message ); } }
function job_response( $http, $data ) { return array( 'response' => array( 'code' => $http ), 'body' => wp_json_encode( $data ), 'headers' => array() ); }
add_filter( 'pre_wp_mail', '__return_true' );
CGR_Plugin::activate();
$db = new CGR_Database(); $api = new CGR_Google_API(); $jobs = new CGR_Outscraper_Jobs( $db, $api );
$api->update_location( 'primary', array( 'outscraper_query' => 'ChIJTest', 'outscraper_limit' => 10, 'outscraper_api_key_enc' => CGR_Crypto::encrypt( 'fake-key' ) ) );
update_option( 'cgr_credential_policy', array( 'enabled' => true, 'expires' => time() + DAY_IN_SECONDS, 'notice' => 'warning:' . ( time() + DAY_IN_SECONDS ), 'monthly_pulls' => 1, 'monthly_reviews' => 10, 'per_pull' => 10 ) );
$stage = 'pending'; $submits = 0; $polls = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$stage, &$submits, &$polls ) {
 job_assert( 0 === strpos( $url, 'https://api.outscraper.com/' ), 'Unexpected external request' );
 job_assert( 'fake-key' === $args['headers']['X-API-KEY'], 'Missing authentication' );
 if ( false !== strpos( $url, '/google-maps-reviews?' ) ) {
  $submits++; parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
  job_assert( 'true' === $q['async'] && 10 === (int) $q['reviewsLimit'] && $args['timeout'] <= 30, 'Submission must be asynchronous and bounded' );
  // Concurrent admin/cron call cannot submit twice.
  if ( get_option( 'cgr_outscraper_lock_primary', false ) ) {
   $other = new CGR_Outscraper_Jobs( new CGR_Database(), new CGR_Google_API() );
   $busy = $other->run( 'primary', true );
   job_assert( is_wp_error( $busy ) && 'cgr_job_busy' === $busy->get_error_code(), 'Location lock failed' );
  }
  if ( 'timeout' === $stage ) { return new WP_Error( 'http_request_failed', 'Simulated timeout' ); }
  if ( 'bad-submit' === $stage ) { return job_response( 202, array( 'status' => 'Pending' ) ); }
  if ( 'immediate' === $stage ) { return job_response( 200, array( 'id' => 'immediate-job', 'status' => 'Success', 'data' => array() ) ); }
  return job_response( 202, array( 'id' => 'saved-job', 'status' => 'Pending', 'results_location' => 'https://untrusted.example/leak' ) );
 }
 $polls++; job_assert( false !== strpos( $url, '/requests/saved-job' ), 'Polling used an unsafe URL or lost its ID' );
 job_assert( $args['timeout'] <= 20, 'Polling is unbounded' );
 if ( 'network-error' === $stage ) { return new WP_Error( 'http_request_failed', 'Download interrupted' ); }
 if ( 'failure' === $stage ) { return job_response( 204, array() ); }
 if ( 'malformed' === $stage ) { return job_response( 200, array( 'status' => 'Success' ) ); }
 if ( 'success' === $stage || 'wrong-business' === $stage ) {
  return job_response( 200, array( 'id' => 'saved-job', 'status' => 'Success', 'data' => array( array( array( 'place_id' => 'wrong-business' === $stage ? 'ChIJOther' : 'ChIJTest', 'name' => 'Test Business', 'rating' => 4.8, 'reviews' => 139, 'reviews_data' => array( array( 'review_id' => 'review-1', 'author_title' => 'Test Reviewer', 'review_rating' => 5, 'review_text' => 'Great visit', 'review_datetime_utc' => '10/19/2026 16:33:41' ) ) ) ) ) ) );
 }
 return job_response( 202, array( 'status' => 'Pending' ) );
}, 10, 3 );
$r = $jobs->run( 'primary', true );
job_assert( 'pending' === $r['status'] && 'saved-job' === CGR_Outscraper_Jobs::get('primary')['id'] && 1 === $submits, 'ID was not persisted' );
job_assert( wp_next_scheduled( CGR_Outscraper_Jobs::HOOK, array( 'primary' ) ), 'Pending job not scheduled' );
job_assert( 1 === CGR_Credential_Policy::usage()['pulls'], 'Usage reservation missing' );
$jobs = new CGR_Outscraper_Jobs( $db, new CGR_Google_API() );
$r = $jobs->run( 'primary', true );
job_assert( 'pending' === $r['status'] && 1 === $submits && 1 === $polls, 'Repeat click submitted again' );
$stage = 'network-error'; $jobs->run( 'primary', true );
job_assert( 'saved-job' === CGR_Outscraper_Jobs::get('primary')['id'] && 1 === $submits, 'Network retry lost ID' );
$stage = 'malformed'; $r = $jobs->run( 'primary', true );
job_assert( is_wp_error($r) && 'pending' === CGR_Outscraper_Jobs::get('primary')['status'], 'Malformed response marked complete' );
$stage = 'wrong-business'; $jobs->run( 'primary', true );
job_assert( 0 === $db->count_all(), 'Wrong business was imported' );
$stage = 'success'; $r = $jobs->run( 'primary', true );
job_assert( 'complete' === $r['status'] && 1 === $r['imported'] && 1 === $db->count_all(), 'Nested result not imported' );
job_assert( 139 === $api->get_location_settings('primary')['total_reviews'], 'Aggregate count not preserved' );
job_assert( ! wp_next_scheduled( CGR_Outscraper_Jobs::HOOK, array('primary') ), 'Completed job still scheduled' );
job_assert( 1 === CGR_Credential_Policy::usage()['pulls'], 'Polling consumed another scrape budget' );
$saved = $r; $saved['status'] = 'pending'; update_option('cgr_outscraper_job_primary',$saved);
$r = $jobs->run('primary',true);
job_assert( 0 === $r['imported'] && 1 === $r['skipped'] && 1 === $db->count_all(), 'Replayed result duplicated reviews' );
// Disabled and expired credentials prevent polling, including when monthly scrape budget is already used.
$saved['status']='pending'; update_option('cgr_outscraper_job_primary',$saved);
$policy=CGR_Credential_Policy::settings(); $policy['paused']=true; update_option('cgr_credential_policy',$policy); $before=$polls;
job_assert( is_wp_error($jobs->run('primary',true)) && $polls===$before, 'Paused policy permitted polling' );
$policy['paused']=false; $policy['expires']=time()-1; update_option('cgr_credential_policy',$policy);
job_assert( is_wp_error($jobs->run('primary',true)) && $polls===$before, 'Expired policy permitted polling' );
update_option('cgr_credential_policy',array('enabled'=>false)); $api->update_location('primary',array('outscraper_api_key_enc'=>CGR_Crypto::encrypt('fake-key')));
// Changing the business or deleting the location must never route a saved job to another location.
$api->update_location('primary',array('outscraper_query'=>'ChIJChanged')); $jobs->run('primary',true);
job_assert( $polls===$before, 'Changed query was not blocked' );
$api->update_location('primary',array('outscraper_query'=>'ChIJTest'));
$second=$api->create_location('Second'); $api->update_location($second,array('outscraper_query'=>'ChIJTest','outscraper_api_key_enc'=>CGR_Crypto::encrypt('fake-key'),'outscraper_limit'=>10));
$stage='pending'; $r=$jobs->run($second,true); job_assert( 'pending'===$r['status'] && 'pending'===CGR_Outscraper_Jobs::get('primary')['status'], 'Location jobs collided' );
$api->delete_location($second); $before=$polls;
job_assert( is_wp_error($jobs->run($second,true)) && $before===$polls, 'Deleted job fell back to another location' );
// Unknown submission blocks all automatic/new clicks; only explicit restart can create another request.
delete_option('cgr_outscraper_job_primary'); $stage='timeout'; $r=$jobs->run('primary',true); $before=$submits;
job_assert( is_wp_error($r) && 'unknown'===CGR_Outscraper_Jobs::get('primary')['status'], 'Ambiguous submission was discarded' );
$jobs->run('primary',true); (new CGR_Sync($db,$api))->run_due_location_syncs();
job_assert( $submits===$before, 'Unknown request automatically re-submitted' );
$stage='pending'; $r=$jobs->run('primary',true,true); job_assert( 'pending'===$r['status'] && $submits===$before+1, 'Confirmed retry failed' );
$before=$submits; job_assert( is_wp_error($jobs->run('primary',true,true)) && $submits===$before, 'Active job allowed replacement' );
$stage='failure'; $jobs->run('primary',true); job_assert( 'failed'===CGR_Outscraper_Jobs::get('primary')['status'], 'Provider failure not recorded' );
$stage='bad-submit'; $jobs->run('primary',true,true); job_assert( 'unknown'===CGR_Outscraper_Jobs::get('primary')['status'], 'Missing ID allowed retries' );
$stage='immediate'; $r=$jobs->run('primary',true,true); job_assert( 'complete'===$r['status'], 'Immediate result not supported' );
// Resume pending checks after deactivation; never start another scrape.
$saved['status']='pending'; update_option('cgr_outscraper_job_primary',$saved); CGR_Plugin::deactivate(); $jobs->resume();
job_assert( wp_next_scheduled(CGR_Outscraper_Jobs::HOOK,array('primary')), 'Pending job not resumed' );
$saved['started']=time()-DAY_IN_SECONDS-1; update_option('cgr_outscraper_job_primary',$saved); $before=$polls; $jobs->run('primary',true);
job_assert( 'expired'===CGR_Outscraper_Jobs::get('primary')['status'] && $polls===$before, 'Pending job never expires' );
echo "Async job lifecycle, retries, duplicate prevention, budget, credential, isolation and recovery tests passed. No external API requests made.\n";
delete_option('cgr_outscraper_job_primary'); $before=$submits;
update_option('cgr_credential_policy',array('enabled'=>true,'expires'=>time()+DAY_IN_SECONDS,'monthly_pulls'=>1,'monthly_reviews'=>10,'per_pull'=>10));
update_option('cgr_credential_usage',array('month'=>gmdate('Y-m'),'pulls'=>1,'reviews'=>10,'last_request'=>time()));
job_assert( is_wp_error($jobs->run('primary',true)) && ! CGR_Outscraper_Jobs::get('primary') && $submits===$before, 'Budget refusal created a blocking job without submitting' );
update_option('cgr_credential_usage',array('month'=>'2000-01','pulls'=>1,'reviews'=>10)); $stage='pending';
$r=$jobs->run('primary',true); job_assert( 'pending'===$r['status'] && $submits===$before+1, 'Month rollover failed to resume new submissions' );
echo "Budget rollover recovery passed.\n";
require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user(1);
$admin = new CGR_Admin($db,$api,new CGR_Sync($db,$api),new CGR_Importer($db,$api));
$_GET['location']='primary'; ob_start(); $admin->render_sources(); $html=ob_get_clean();
job_assert( false!==strpos($html,'saved-job') && false!==strpos($html,'Check saved request now') && false!==strpos($html,'Waiting for results'), 'Sources did not expose pending ID/check controls' );
$pending=CGR_Outscraper_Jobs::get('primary'); $pending['status']='unknown'; $pending['message']='<script>bad()</script>'; update_option('cgr_outscraper_job_primary',$pending);
ob_start(); $admin->render_sources(); $html=ob_get_clean();
job_assert( false!==strpos($html,'Start a new request') && false!==strpos($html,'return confirm(') && false!==strpos($html,'&lt;script&gt;bad()&lt;/script&gt;'), 'Failure UI lacks confirmation or safe escaping' );
echo "Sources status and recovery UI rendering passed.\n";
