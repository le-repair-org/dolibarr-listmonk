<?php
/* Copyright (C) 2024 Le Repair
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    listmonk/lib/listmonk.lib.php
 * \ingroup listmonk
 * \brief   Library functions for Listmonk API integration
 */

/**
 * Check whether the core Listmonk configuration constants (endpoint, user, token) are set.
 *
 * @return bool
 */
function listmonk_is_configured()
{
  $endpoint = getDolGlobalString('LISTMONK_API_ENDPOINT');
  $user = getDolGlobalString('LISTMONK_API_USER');
  $token = getDolGlobalString('LISTMONK_ACCESS_TOKEN');

  return !empty($endpoint) && !empty($user) && !empty($token);
}

/**
 * Perform an HTTP request against the Listmonk API.
 *
 * @param string     $method   HTTP method (GET, POST, PUT, DELETE)
 * @param string     $path     API path, e.g. "/api/subscribers"
 * @param array|null $data     Request body (will be JSON-encoded)
 * @param array      $query    Query string parameters
 * @return array|null          Decoded response body, or null on failure
 */
function listmonk_api_call($method, $path, $data = null, $query = array())
{
  $endpoint = rtrim(getDolGlobalString('LISTMONK_API_ENDPOINT'), '/');
  $user = getDolGlobalString('LISTMONK_API_USER');
  $token = getDolGlobalString('LISTMONK_ACCESS_TOKEN');

  $url = $endpoint . $path;

  if (!empty($query)) {
    $url .= '?' . http_build_query($query);
  }

  $ch = curl_init($url);

  $headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
  );

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $token);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

  if ($data !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    dol_syslog('[listmonk] Listmonk API curl error on ' . $method . ' ' . $path . ': ' . $curlError, LOG_ERR);
    return null;
  }

  if ($httpCode < 200 || $httpCode >= 300) {
    dol_syslog('[listmonk] Listmonk API HTTP ' . $httpCode . ' on ' . $method . ' ' . $path . ': ' . $response, LOG_ERR);
    return null;
  }

  if (empty($response)) {
    return array();
  }

  $decoded = json_decode($response, true);
  if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    dol_syslog('[listmonk] Listmonk API JSON decode error on ' . $method . ' ' . $path . ': ' . json_last_error_msg(), LOG_ERR);
    return null;
  }

  return $decoded;
}

/**
 * Find a Listmonk subscriber by email address.
 *
 * @param string $email
 * @return array|null Subscriber data array, or null if not found or error
 */
function listmonk_get_subscriber_by_email($email)
{
  $result = listmonk_api_call('GET', '/api/subscribers', null, array(
    'query' => "subscribers.email = '" . addslashes($email) . "'",
    'page' => 1,
    'per_page' => 1,
  ));

  if ($result === null) {
    return null;
  }

  $total = isset($result['data']['total']) ? (int) $result['data']['total'] : 0;
  if ($total === 0) {
    return null;
  }

  return isset($result['data']['results'][0]) ? $result['data']['results'][0] : null;
}

/**
 * Find or create a subscriber in Listmonk and update their profile (name).
 * Preserves all existing list subscriptions — the PUT /api/subscribers/{id} endpoint
 * clears list memberships when the lists field is omitted, so existing IDs are always passed.
 * Accepts an already-fetched subscriber array to avoid a redundant API call.
 *
 * @param string     $email
 * @param string     $name
 * @param array|null $existing  Pre-fetched subscriber data (from listmonk_get_subscriber_by_email), or null to fetch
 * @return int|null Listmonk subscriber ID, or null on error
 */
function listmonk_ensure_subscriber($email, $name, $existing = null)
{
  if ($existing === null) {
    $existing = listmonk_get_subscriber_by_email($email);
  }

  if ($existing !== null) {
    $subscriberId = (int) $existing['id'];

    // Collect existing list IDs to preserve subscriptions — omitting `lists` from the PUT
    // body causes Listmonk to clear all list memberships for the subscriber.
    $existingListIds = array();
    if (!empty($existing['lists'])) {
      foreach ($existing['lists'] as $list) {
        $existingListIds[] = (int) $list['id'];
      }
    }

    listmonk_api_call('PUT', '/api/subscribers/' . $subscriberId, array(
      'email' => $email,
      'name' => $name,
      'status' => $existing['status'] ?? 'enabled',
      'lists' => $existingListIds,
    ));
    return $subscriberId;
  }

  // Create new subscriber with no initial list assignments
  $result = listmonk_api_call('POST', '/api/subscribers', array(
    'email' => $email,
    'name' => $name,
    'status' => 'enabled',
    'preconfirm_subscriptions' => true,
    'lists' => array(),
  ));

  if ($result === null || !isset($result['data']['id'])) {
    return null;
  }

  return (int) $result['data']['id'];
}

/**
 * Add a subscriber to a Listmonk list (confirmed subscription).
 *
 * @param int $subscriberId  Listmonk subscriber ID
 * @param int $listId        Listmonk list ID
 * @return bool True on success
 */
function listmonk_add_subscriber_to_list($subscriberId, $listId)
{
  $result = listmonk_api_call('PUT', '/api/subscribers/lists', array(
    'ids' => array((int) $subscriberId),
    'action' => 'add',
    'target_list_ids' => array((int) $listId),
    'status' => 'confirmed',
  ));

  return $result !== null;
}

/**
 * Unsubscribe a subscriber from a Listmonk list.
 *
 * @param int $subscriberId  Listmonk subscriber ID
 * @param int $listId        Listmonk list ID
 * @return bool True on success
 */
function listmonk_remove_subscriber_from_list($subscriberId, $listId)
{
  $result = listmonk_api_call('PUT', '/api/subscribers/lists', array(
    'ids' => array((int) $subscriberId),
    'action' => 'unsubscribe',
    'target_list_ids' => array((int) $listId),
  ));

  return $result !== null;
}

/**
 * Blocklist a Listmonk subscriber (soft delete, prevents future sends on all lists).
 *
 * @param int $subscriberId  Listmonk subscriber ID
 * @return bool True on success
 */
function listmonk_blocklist_subscriber($subscriberId)
{
  $result = listmonk_api_call('PUT', '/api/subscribers/blocklist', array(
    'ids' => array((int) $subscriberId),
  ));

  return $result !== null;
}

/**
 * Fetch all lists from Listmonk.
 *
 * @return array|null  Array of list objects, or null on error
 */
function listmonk_get_all_lists()
{
  $result = listmonk_api_call('GET', '/api/lists', null, array(
    'per_page' => 'all',
  ));

  if ($result === null || !isset($result['data']['results'])) {
    return null;
  }

  return $result['data']['results'];
}

/**
 * Subscribe a member to a Listmonk list.
 *
 * By default, respects an explicit unsubscribe recorded in Listmonk (i.e. does not re-subscribe).
 * Pass $force = true to override the unsubscribed state (e.g. admin action). Blocklisted status
 * is always respected regardless of $force.
 *
 * If the subscriber does not exist yet, it is created.
 *
 * @param string $email
 * @param string $name
 * @param int    $listId
 * @param bool   $force  When true, re-subscribes even if the subscriber had explicitly unsubscribed
 * @return bool  True if subscribed, false if blocklisted or on error
 */
function listmonk_subscribe_member($email, $name, $listId, $force = false)
{
  $existing = listmonk_get_subscriber_by_email($email);

  if ($existing !== null && ($existing['status'] ?? '') === 'blocklisted') {
    dol_syslog('[listmonk] subscribe_member: subscriber ' . $email . ' is blocklisted, skipping');
    return false;
  }

  if (!$force) {
    $currentListStatuses = array();
    if ($existing !== null && !empty($existing['lists'])) {
      foreach ($existing['lists'] as $list) {
        $currentListStatuses[(int) $list['id']] = $list['subscription_status'] ?? 'unknown';
      }
    }

    if (($currentListStatuses[$listId] ?? null) === 'unsubscribed') {
      dol_syslog('[listmonk] subscribe_member: subscriber ' . $email . ' explicitly unsubscribed from list ' . $listId . ', skipping');
      return false;
    }
  }

  $subscriberId = listmonk_ensure_subscriber($email, $name, $existing);
  if ($subscriberId === null) {
    dol_syslog('[listmonk] subscribe_member: failed to ensure subscriber for email=' . $email, LOG_ERR);
    return false;
  }

  return listmonk_add_subscriber_to_list($subscriberId, $listId);
}
