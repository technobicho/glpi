<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

include ('../inc/includes.php');

if (isset($_GET['full_page_tab'])) {
   Html::header('Only tab for debug', $_SERVER['PHP_SELF']);
} else {
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

if (!isset($_GET['_glpi_tab'])) {
   exit();
}

if (!isset($_GET['_itemtype']) || empty($_GET['_itemtype'])) {
   exit();
}

if (!isset($_GET["sort"])) {
   $_GET["sort"] = "";
}

if (!isset($_GET["order"])) {
   $_GET["order"] = "";
}

if (!isset($_GET["withtemplate"])) {
   $_GET["withtemplate"] = "";
}

if ($item = getItemForItemtype($_GET['_itemtype'])) {
   if ($item instanceof CommonDBTM) {
      if (!isset($_GET["id"])
            || ($item->isNewID($_GET["id"]) && !$item->can(-1, 'w', $_GET))) {
         exit();
      } else if (!$item->can($_GET["id"],'r')){
         exit();
      }
   }
}

$notvalidoptions = array('_glpi_tab', '_itemtype', 'sort', 'order', 'withtemplate');
$options = $_GET;
foreach ($notvalidoptions as $key) {
   if (isset($options[$key])) {
      unset($options[$key]);
   }
}

CommonGLPI::displayStandardTab($item, $_GET['_glpi_tab'],$_GET["withtemplate"], $options);


if (isset($_GET['full_page_tab'])) {
   Html::footer();

   // I think that we should display this warning, because tabs are not prepare
   // for being used full space ...
   if (!isset($_SESSION['glpi_warned_about_full_page_tab'])) {
      // Debug string : not really need translation.
      $msg  = 'WARNING: full page tabs are only for debug/validation purpose !\n';
      $msg .= 'Actions on this page may have undefined result.';
      echo "<script type='text/javascript' >\n";
      echo "alert('$msg')";
      echo "</script>";
      $_SESSION['glpi_warned_about_full_page_tab'] = true;
   }

} else {
   Html::ajaxFooter();
}
?>