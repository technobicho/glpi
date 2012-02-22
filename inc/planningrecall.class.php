<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class PlanningRecall
// @since version 0.84
class PlanningRecall extends CommonDBTM {


   static function getTypeName($nb=0) {
      return _n('Planning reminder', 'Planning reminders', $nb);
   }


   function canCreate() {
      return true;
   }


   function canCreateItem() {
      return $this->fields['users_id'] == Session::getLoginUserID();
   }


   static function isAvailable() {
      global $CFG_GLPI;

      // Cache in session
      if (isset($_SESSION['glpiplanningreminder_isavailable'])) {
         return $_SESSION['glpiplanningreminder_isavailable'];
      }

      $_SESSION['glpiplanningreminder_isavailable'] = 0;
      if ($CFG_GLPI["use_mailing"]) {
         $task = new Crontask();
         if ($task->getFromDBbyName('PlanningRecall','planningrecall')) {
            // Only disabled by config
            if ($task->isDisabled() != 1) {
               if (Session::haveRight("show_planning", "1")
                   || Session::haveRight("show_all_planning", "1")
                   || Session::haveRight("show_group_planning","1")) {
                  $_SESSION['glpiplanningreminder_isavailable'] = 1;
               }
            }
         }
      }

      return $_SESSION['glpiplanningreminder_isavailable'];
   }


   /**
    * Retrieve an item from the database
    *
    * @param $itemtype     string   itemtype to get
    * @param $items_id     integer  id of the item
    * @param $users_id     integer  id of the user
    *
    * @return true if succeed else false
   **/
   function getFromDBForItemAndUser($itemtype, $items_id, $users_id) {
      global $DB;

      $query = "SELECT *
                FROM `".$this->getTable()."`
                WHERE `itemtype` = '$itemtype'
                  AND `items_id` = '$items_id'
                  AND `users_id` = '$users_id'";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)>0) {
            $this->fields = $DB->fetch_assoc($result);
            return true;
         }
      }
      return false;
   }


   /**
    * @see inc/CommonDBTM::post_updateItem()
   **/
   function post_updateItem($history=1) {
         $alert = new Alert();
         $alert->clear($this->getType(), $this->fields['id'], Alert::ACTION);
   }


   /**
    * Manage recall set
    *
    * @param $data array of data to manage
   **/
   static function manageDatas(array $data) {

      // Check data informations
      if (!isset($data['itemtype'])
          || !isset($data['items_id'])
          || !isset($data['users_id'])
          || !isset($data['before_time'])
          || !isset($data['field'])) {
         return false;
      }

      $pr = new self();
      // Datas OK : check if recall already exists
      if ($pr->getFromDBForItemAndUser($data['itemtype'], $data['items_id'],
                                       $data['users_id'])) {

         if ($data['before_time'] != $pr->fields['before_time']) {
            // Recall exists and is different : update datas and clean alert
            if ($pr->can($pr->fields['id'],'w')) {
               if ($item=getItemForItemtype($data['itemtype'])) {
                  if ($item->getFromDB($data['items_id'])
                      && isset($item->fields[$data['field']])
                      && !empty($item->fields[$data['field']])) {

                     $when = date("Y-m-d H:i:s",
                                  strtotime($item->fields[$data['field']]) - $data['before_time']);
                     if ($data['before_time'] >= 0) {
                        $pr->update(array('id'          => $pr->fields['id'],
                                          'before_time' => $data['before_time'],
                                          'when'        => $when));
                     } else {
                        $pr->delete(array('id' => $pr->fields['id']));
                     }
                  }
               }
            }
         }

      } else {
         // Recall does not exists : create it
         if ($pr->can(-1,'w',$data)) {
               if ($item=getItemForItemtype($data['itemtype'])) {
                  $item->getFromDB($data['items_id']);
                  if ($item->getFromDB($data['items_id'])
                      && isset($item->fields[$data['field']])
                      && !empty($item->fields[$data['field']])) {
                     $data['when'] = date("Y-m-d H:i:s",
                                          strtotime($item->fields[$data['field']])
                                                      - $data['before_time']);
                     if ($data['before_time'] >= 0) {
                        $pr->add($data);
                     }
                  }
               }
         }
      }
   }


   /**
    * Update planning recal date when changing begin of planning
    *
    * @param $itemtype  string   itemtype to get
    * @param $items_id  integer  id of the item
    * @param $begin     datetime new begin date
    *
    * @return true if succeed else false
   **/
   static function managePlanningUpdates($itemtype, $items_id, $begin) {
      global $DB;

      $query = "UPDATE `glpi_planningrecalls`
                SET `when` = DATE_SUB('$begin', INTERVAL `before_time` SECOND)
                WHERE `itemtype` = '$itemtype'
                      AND `items_id` = '$items_id';";
      return $DB->query($query);
   }


   /**
    * Make a select box with recall times
    *
    * Mandatory options : itemtype, items_id
    *
    * @param $options array of possible options:
    *    - itemtype : string itemtype
    *    - items_id : integer id of the item
    *    - users_id : integer id of the user (if not set used login user)
    *    - value    : integer preselected value for before_time
    *    - field    : string  field used as time mark (default begin)
    *
    * @return nothing (print out an HTML select box) / return false if mandatory fields are not ok
   **/
   static function dropdown($options=array()) {
      global $DB, $CFG_GLPI;

      // Default values
      $p['itemtype'] = '';
      $p['items_id'] = 0;
      $p['users_id'] = Session::getLoginUserID();
      $p['value']    = Entity::CONFIG_NEVER;
      $p['field']    = 'begin';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      if (!($item = getItemForItemtype($p['itemtype']))) {
         return false;
      } // Do not check items_id and item get because may be used when creating item (task for example)

      $pr = new self();
      // Get recall for item and user
      if ($pr->getFromDBForItemAndUser($p['itemtype'], $p['items_id'], $p['users_id'])) {
         $p['value'] = $pr->fields['before_time'];
      }

      $possible_values                       = array();
      $possible_values[Entity::CONFIG_NEVER] = __('None');

      $min_values = array(0, 15, 30, 45);
      foreach ($min_values as $val) {
         $possible_values[$val*MINUTE_TIMESTAMP] = sprintf(_n('%1$d minute','%1$d minutes',$val),
                                                           $val);
      }

      $h_values = array(1, 2, 3, 4, 12);
      foreach ($h_values as $val) {
         $possible_values[$val*HOUR_TIMESTAMP] = sprintf(_n('%1$d hour','%1$d hours',$val), $val);
      }
      $d_values = array(1, 2);
      foreach ($d_values as $val) {
         $possible_values[$val*DAY_TIMESTAMP] = sprintf(_n('%1$d day','%1$d days',$val), $val);
      }
      $w_values = array(1);
      foreach ($w_values as $val) {
         $possible_values[$val*7*DAY_TIMESTAMP] = sprintf(_n('%1$d week','%1$d weeks',$val), $val);
      }

      ksort($possible_values);

      Dropdown::showFromArray('_planningrecall[before_time]', $possible_values,
                              array('value' => $p['value']));
      echo "<input type='hidden' name='_planningrecall[itemtype]' value='".$p['itemtype']."'>";
      echo "<input type='hidden' name='_planningrecall[items_id]' value='".$p['items_id']."'>";
      echo "<input type='hidden' name='_planningrecall[users_id]' value='".$p['users_id']."'>";
      echo "<input type='hidden' name='_planningrecall[field]' value='".$p['field']."'>";
      return true;
   }


   /**
    * Dispaly specific form when no edit right
    *
    * Mandatory options : itemtype, items_id
    *
    * @param $options array of possible options:
    *    - itemtype : string itemtype
    *    - items_id : integer id of the item
    *    - users_id : integer id of the user (if not set used login user)
    *    - value    : integer preselected value for before_time
    *    - field    : string  field used as time mark (default begin)
    *
    * @return nothing (print out an HTML select box) / return false if mandatory fields are not ok
   **/
   static function specificForm($options=array()) {
      global $CFG_GLPI;

      // Default values
      $p['itemtype'] = '';
      $p['items_id'] = 0;
      $p['users_id'] = Session::getLoginUserID();
      $p['value']    = Entity::CONFIG_NEVER;
      $p['field']    = 'begin';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      if (!($item = getItemForItemtype($p['itemtype']))) {
         return false;
      } // Do not check items_id and item get because may be used when creating item (task for example)

      echo "<form method='post' action='".$CFG_GLPI['root_doc']."/front/planningrecall.form.php'>";
      self::dropdown($options);
      echo " <input type='submit' name='update' value=\""._sx('button', 'Save')."\" class='submit'>";

      echo "</form>";
   }


   /**
    * Give cron information
    *
    * @param $name : task's name
    *
    * @return arrray of information
   **/
   static function cronInfo($name) {

      switch ($name) {
         case 'planningrecall' :
            return array('description' => __('Send planning recalls'));
      }
      return array();
   }


   /**
    * Cron action on contracts : alert depending of the config : on notice and expire
    *
    * @param $task for log, if NULL display (default NULL)
   **/
   static function cronPlanningRecall($task=NULL) {
      global $DB, $CFG_GLPI;

      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }

      $cron_status   = 0;
      $query = "SELECT `glpi_planningrecalls`.*
                FROM `glpi_planningrecalls`
                LEFT JOIN `glpi_alerts` ON (`glpi_planningrecalls`.`id` = `glpi_alerts`.`items_id`
                                            AND `glpi_alerts`.`itemtype` = 'PlanningReminder'
                                            AND `glpi_alerts`.`type`='".Alert::ACTION."')
                WHERE `glpi_planningrecalls`.`when` IS NOT NULL
                      AND `glpi_planningrecalls`.`when` < NOW()
                      AND `glpi_alerts`.`date` IS NULL";

      $pr = new self();
      foreach ($DB->request($query) as $data) {
         $pr->getFromDB($data['id']);
         if (NotificationEvent::raiseEvent('planningrecall', $pr)) {

            $cron_status         = 1;
            $task->addVolume(1);
            $alert               = new Alert();
            $input["itemtype"]   = __CLASS__;
            $input["type"]       = Alert::ACTION;
            $input["items_id"]   = $data['id'];

            $alert->add($input);
         }
      }
      return $cron_status;
   }
}
?>