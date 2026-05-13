<?php
/* Copyright (C) 2024 Le Repair
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   listmonk     Module Listmonk
 * \brief      Listmonk module descriptor.
 *
 * \file       htdocs/custom/listmonk/core/modules/modListmonk.class.php
 * \ingroup    listmonk
 * \brief      Description and activation file for module Listmonk
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Listmonk
 */
class modListmonk extends DolibarrModules
{
  /**
   * Constructor. Define names, constants, directories, boxes, permissions
   *
   * @param DoliDB $db Database handler
   */
  public function __construct($db)
  {
    global $conf, $langs;

    $this->db = $db;

    // Id for module (must be unique in the Dolibarr module list)
    $this->numero = 500010;
    $this->rights_class = 'listmonk';

    $this->family = "other";
    $this->module_position = '90';
    $this->name = preg_replace('/^mod/i', '', get_class($this));
    $this->description = "Listmonk email marketing integration";
    $this->descriptionlong = "Provides a generic API wrapper for the Listmonk email marketing platform.";

    $this->editor_name = 'Le Repair';
    $this->editor_url = '';

    $this->version = '1.0';
    $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
    $this->picto = 'email';

    // Define module parts
    $this->module_parts = array(
      'triggers' => 0,
      'login' => 0,
      'substitutions' => 0,
      'menus' => 0,
      'tpl' => 0,
      'barcode' => 0,
      'models' => 0,
      'printing' => 0,
      'theme' => 0,
      'css' => array(),
      'js' => array(),
      'hooks' => array(),
      'moduleforexternal' => 0,
      'websitetemplates' => 0,
      'captcha' => 0,
    );

    // Data directories to create when module is enabled
    $this->dirs = array();

    // Config pages
    $this->config_page_url = array("setup.php@listmonk");

    // Dependencies
    $this->hidden = getDolGlobalInt('MODULE_LISTMONK_DISABLED');
    $this->depends = array();
    $this->requiredby = array();
    $this->conflictwith = array();
    $this->langfiles = array("listmonk@listmonk");
    $this->phpmin = array(7, 4);
    $this->need_dolibarr_version = array(22, 0);
    $this->need_javascript_ajax = 0;
    $this->warnings_activation = array();
    $this->warnings_activation_ext = array();

    // Constants
    $this->const = array(
      0 => array('LISTMONK_API_ENDPOINT', 'chaine', '', '', 0, 'current', 0),
      1 => array('LISTMONK_API_USER',     'chaine', '', '', 0, 'current', 0),
      2 => array('LISTMONK_ACCESS_TOKEN', 'chaine', '', '', 0, 'current', 0),
    );

    if (!isModEnabled('listmonk')) {
      $conf->listmonk = new stdClass();
      $conf->listmonk->enabled = 0;
    }

    $this->tabs = array(
      array('data' => 'member:+newsletter:Newsletter:listmonk@listmonk:isModEnabled("listmonk") && isModEnabled("adherent"):/listmonk/tabs/member_newsletter.php?id=__ID__'),
    );
    $this->dictionaries = array();
    $this->extrafields = array();
    $this->boxes = array();
    $this->cronjobs = array();
    $this->rights = array();
    $this->menu = array();
  }

  /**
   * Function called when module is enabled.
   *
   * @param string $options Options when enabling module ('', 'noboxes')
   * @return int             1 if OK, 0 if KO
   */
  public function init($options = '')
  {
    $result = $this->_load_tables('/listmonk/sql/');
    if ($result < 0) {
      return -1;
    }

    return $this->_init(array(), $options);
  }

  /**
   * Function called when module is disabled.
   *
   * @param string $options Options when disabling module ('')
   * @return int             1 if OK, 0 if KO
   */
  public function remove($options = '')
  {
    $sql = array();
    return $this->_remove($sql, $options);
  }
}
