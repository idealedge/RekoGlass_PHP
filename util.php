<?php
/*
* Copyright (C) 2013 Google Inc.
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*      http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/
//  Author: Jenny Murphy - http://google.com/+JennyMurphy

require_once 'config.php';
require_once 'mirror-client.php';
require_once 'google-api-php-client/src/Google_Client.php';
require_once 'google-api-php-client/src/contrib/Google_MirrorService.php';

// Returns an unauthenticated service
function get_google_api_client() {
  global $api_client_id, $api_client_secret, $api_simple_key, $base_url;
  // Set your cached access token. Remember to replace $_SESSION with a
  // real database or memcached.
  session_start();

  $client = new Google_Client();

  $client->setApplicationName('Google Mirror API PHP Quick Start');

  // These are set in config.php
  $client->setClientId($api_client_id);
  $client->setClientSecret($api_client_secret);
  $client->setDeveloperKey($api_simple_key);
  $client->setRedirectUri($base_url."/oauth2callback.php");

  $client->setScopes(array(
    'https://www.googleapis.com/auth/glass.timeline',
    'https://www.googleapis.com/auth/glass.location',
    'https://www.googleapis.com/auth/userinfo.profile'));

  return $client;
}

function store_credentials($user_id, $credentials) {
  $db = init_db();

  $user_id = $db->escapeString(strip_tags($user_id));
  $credentials = $db->escapeString(strip_tags($credentials));

  $insert = "insert into credentials values ('$user_id', '$credentials')";
  $db->exec($insert);

}

function get_credentials($user_id) {
  $db = init_db();
  $user_id = $db->escapeString(strip_tags($user_id));

  $query = $db->query("select * from credentials where userid = '$user_id'");

  $row = $query->fetchArray();
  return $row['credentials'];
}

function list_credentials() {
  $db = init_db();

  // Must use explicit select instead of * to get the rowid
  $query = $db->query('select userid, credentials from credentials');
  return $db->fetchAll($query, SQLITE_ASSOC);

}

// Create the credential storage if it does not exist
function init_db() {
  global $sqlite_database;
  
  print_r($sqlite_database);

  $db = new SQLite3($sqlite_database);
 // $db->open();
  $test_query = "select count(*) from sqlite_master where name = 'credentials'";
  if ($db->querySingle($test_query) == 0) {
    $create_table = "create table credentials (userid text, credentials text);";
    $db->exec($create_table);
  }
  return $db;
}

function bootstrap_new_user() {
  global $base_url;

  $client = get_google_api_client();
  $client->setAccessToken(get_credentials($_SESSION['userid']));

  // A glass service for interacting with the Mirror API
  $mirror_service = new Google_MirrorService($client);

  $timeline_item = new Google_TimelineItem();
  $timeline_item->setText("Welcome to the Mirror API PHP Quick Start");

  insertTimelineItem($mirror_service, $timeline_item, null, null);

  insertContact($mirror_service, "php-quick-start", "PHP Quick Start",
      $base_url . "/static/images/chipotle-tube-640x360.jpg");

  subscribeToNotifications($mirror_service, "timeline",
    $_SESSION['userid'], $base_url . "/notify.php");
}
