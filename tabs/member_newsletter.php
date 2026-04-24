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
 * \file    listmonk/tabs/member_newsletter.php
 * \ingroup listmonk
 * \brief   Newsletter tab on member card: shows Listmonk subscription status and allows subscribe/unsubscribe per list.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
  $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
  $i--;
  $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
  $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
  $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
  $res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
  $res = @include "../../../../main.inc.php";
}
if (!$res) {
  die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/member.lib.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
dol_include_once('/listmonk/lib/listmonk.lib.php');

$langs->loadLangs(array("members"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$listId = GETPOSTINT('list_id');

// Security check
if (!$user->hasRight('adherent', 'lire')) {
  accessforbidden();
}

$object = new Adherent($db);
$result = $object->fetch($id);
if ($result <= 0) {
  dol_print_error($db, $object->error);
  exit;
}

$restrictedArea = restrictedArea($user, 'adherent', $object->id, '', '', 'socid', 'rowid', 0);

/*
 * Actions
 */

if ($action === 'subscribe' && $listId && !empty($object->email)) {
  if (!listmonk_is_configured()) {
    setEventMessages('Listmonk non configur&eacute;.', null, 'warnings');
  } else {
    $name = trim($object->firstname . ' ' . $object->lastname);
    // Force re-subscription even if previously unsubscribed in Listmonk (admin intent)
    $ok = listmonk_subscribe_member($object->email, $name, $listId, true);
    if ($ok) {
      setEventMessages('Inscription effectu&eacute;e.', null, 'mesgs');
    } else {
      setEventMessages('Impossible d\'inscrire ce membre (subscriber bloqué dans Listmonk).', null, 'warnings');
    }
  }
  header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
  exit;
}

if ($action === 'unsubscribe' && $listId && !empty($object->email)) {
  if (!listmonk_is_configured()) {
    setEventMessages('Listmonk non configur&eacute;.', null, 'warnings');
  } else {
    $existing = listmonk_get_subscriber_by_email($object->email);
    if ($existing !== null) {
      $ok = listmonk_remove_subscriber_from_list((int) $existing['id'], $listId);
      if ($ok) {
        setEventMessages('D&eacute;sinscription effectu&eacute;e.', null, 'mesgs');
      } else {
        setEventMessages('Erreur lors de la d&eacute;sinscription.', null, 'errors');
      }
    } else {
      setEventMessages('Subscriber introuvable dans Listmonk.', null, 'warnings');
    }
  }
  header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
  exit;
}

/*
 * View
 */

$title = $langs->trans("Member") . " - Newsletter";
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-member page-card_newsletter');

$head = member_prepare_head($object);
print dol_get_fiche_head($head, 'newsletter', $langs->trans("Member"), -1, 'user');

$linkback = '<a href="' . DOL_URL_ROOT . '/adherents/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

$morehtmlref = '<a href="' . DOL_URL_ROOT . '/adherents/vcard.php?id=' . $object->id . '" class="refid">';
$morehtmlref .= img_picto($langs->trans("Download") . ' ' . $langs->trans("VCard"), 'vcard.png', 'class="valignmiddle marginleftonly paddingrightonly"');
$morehtmlref .= '</a>';

dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if (empty($object->email)) {
  print '<div class="info">';
  print 'Ce membre n\'a pas d\'adresse email. Impossible de consulter les abonnements Listmonk.';
  print '</div>';
  print '</div>';
  print dol_get_fiche_end();
  llxFooter();
  $db->close();
  exit;
}

if (!listmonk_is_configured()) {
  print '<div class="warning">';
  print 'Listmonk n\'est pas configur&eacute;. Rendez-vous dans Administration &gt; Listmonk.';
  print '</div>';
  print '</div>';
  print dol_get_fiche_end();
  llxFooter();
  $db->close();
  exit;
}

// Fetch subscriber and all lists from Listmonk
$subscriber = listmonk_get_subscriber_by_email($object->email);
$allLists = listmonk_get_all_lists();

// Build a map of list_id => subscription_status for this subscriber
$subscriberListStatuses = array();
$isBlocklisted = false;

if ($subscriber !== null) {
  $isBlocklisted = ($subscriber['status'] ?? '') === 'blocklisted';
  if (!empty($subscriber['lists'])) {
    foreach ($subscriber['lists'] as $list) {
      $subscriberListStatuses[(int) $list['id']] = $list['subscription_status'] ?? 'unknown';
    }
  }
}

// Subscriber global status
print '<br>';
print '<strong>Statut dans Listmonk&nbsp;: </strong>';
if ($subscriber === null) {
  print '<span class="badge badge-status0">Non inscrit</span>';
} elseif ($isBlocklisted) {
  print '<span class="badge badge-status8">Bloqu&eacute; (blocklisted)</span>';
  print '<br><div class="info" style="margin-top:8px">';
  print '<strong>Qu\'est-ce que &laquo;&nbsp;blocklisted&nbsp;&raquo;&nbsp;?</strong><br>';
  print 'Ce subscriber est bloqu&eacute; dans Listmonk : aucun email ne lui sera envoy&eacute;, quelle que soit la liste. ';
  print 'Cet &eacute;tat est g&eacute;n&eacute;ralement d&ecirc;f&icirc; par un hard bounce (adresse invalide) ou une plainte spam. ';
  print 'Pour le d&eacute;bloquer, il faut intervenir directement dans l\'interface Listmonk.';
  print '</div>';
} else {
  print '<span class="badge badge-status4">Actif</span>';
}
print '<br><br>';

if ($allLists === null) {
  print '<div class="error">Erreur lors de la r&eacute;cup&eacute;ration des listes Listmonk.</div>';
  print '</div>';
  print dol_get_fiche_end();
  llxFooter();
  $db->close();
  exit;
}

if (empty($allLists)) {
  print '<div class="info">Aucune liste trouv&eacute;e dans Listmonk.</div>';
  print '</div>';
  print dol_get_fiche_end();
  llxFooter();
  $db->close();
  exit;
}

// Lists table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Liste</td>';
print '<td>Statut</td>';
print '<td class="right">Action</td>';
print '</tr>';

foreach ($allLists as $list) {
  $lId = (int) $list['id'];
  $lName = dol_escape_htmltag($list['name']);
  $status = $subscriberListStatuses[$lId] ?? null;

  if ($status === 'confirmed') {
    $statusLabel = '<span class="badge badge-status4">Inscrit</span>';
  } elseif ($status === 'unsubscribed') {
    $statusLabel = '<span class="badge badge-status8">D&eacute;sinscrit</span>';
  } else {
    $statusLabel = '<span class="badge badge-status0">Non inscrit</span>';
  }

  print '<tr class="oddeven">';
  print '<td>' . $lName . '</td>';
  print '<td>' . $statusLabel . '</td>';
  print '<td class="right">';

  if ($isBlocklisted) {
    print '<span class="opacitymedium">Bloqu&eacute;</span>';
  } elseif ($status === 'confirmed') {
    print '<a class="butActionDelete" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=unsubscribe&list_id=' . $lId . '&token=' . newToken() . '">D&eacute;sinscrire</a>';
  } else {
    print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=subscribe&list_id=' . $lId . '&token=' . newToken() . '">Inscrire</a>';
  }

  print '</td>';
  print '</tr>';
}

print '</table>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
