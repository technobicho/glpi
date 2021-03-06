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
* @since version 0.83
*/

if (!defined('GLPI_ROOT')) {
   include ('../inc/includes.php');
}

$link = new Problem_User();

Session ::checkLoginUser();
Html::popHeader(__('Email followup'), $_SERVER['PHP_SELF']);

if (isset($_POST["update"])) {
   $link->check($_POST["id"], UPDATE);
   $link->update($_POST);

} else if (isset($_POST['delete'])) {
   $link->check($_POST['id'], DELETE);
   $link->delete($_POST);

   Event::log($link->fields['problems_id'], "problem", 4, "maintain",
              //TRANS: %s is the user login
              sprintf(__('%s deletes an actor'), $_SESSION["glpiname"]));
   Html::redirect($CFG_GLPI["root_doc"]."/front/problem.form.php?id=".$link->fields['problems_id']);

} else if (isset($_GET["id"])) {
   $link->showUserNotificationForm($_GET["id"]);
} else {
   Html::displayErrorAndDie('Lost');
}

Html::popFooter();
?>