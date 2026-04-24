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
 * \file    listmonk/admin/setup.php
 * \ingroup listmonk
 * \brief   Listmonk module setup page.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
  $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
  $i--;
  $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
  $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
  $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
  $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
  $res = @include "../../../main.inc.php";
}
if (!$res) {
  die("Include of main fails");
}

global $langs, $user;

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/listmonk.lib.php';

$langs->loadLangs(array("admin", "listmonk@listmonk"));

$hookmanager->initHooks(array('listmonksetup', 'globalsetup'));

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

if (!$user->admin) {
  accessforbidden();
}

if (!class_exists('FormSetup')) {
  require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

$formSetup->newItem('LISTMONK_API_ENDPOINT');
$formSetup->newItem('LISTMONK_API_USER');
$tokenItem = $formSetup->newItem('LISTMONK_ACCESS_TOKEN');
$tokenItem->fieldInputOverride = '<input type="password" name="LISTMONK_ACCESS_TOKEN" value="' . dol_escape_htmltag(getDolGlobalString('LISTMONK_ACCESS_TOKEN')) . '" class="flat minwidth300" autocomplete="new-password">';


/*
 * Actions
 */

if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
  $formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

if ($action == 'test_connection') {
  if (!listmonk_is_configured()) {
    setEventMessages($langs->trans("ListmonkConfigIncomplete"), null, 'warnings');
  } else {
    $result = listmonk_api_call('GET', '/api/health');
    if ($result !== null) {
      setEventMessages($langs->trans("ListmonkConnectionSuccess"), null, 'mesgs');
    } else {
      setEventMessages($langs->trans("ListmonkConnectionFailed"), null, 'errors');
    }
  }
}

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "ListmonkSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-listmonk page-admin');

$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

print dol_get_fiche_head(array(), '', '', -1, '');

if (!empty($formSetup->items)) {
  print $formSetup->generateOutput(true);
  print '<br>';
} else {
  print '<br>' . $langs->trans("NothingToSetup");
}

print '<div class="tabsAction">';
print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=test_connection&token=' . newToken() . '">' . $langs->trans("ListmonkTestConnection") . '</a>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
