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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Profile class
**/
class Profile extends CommonDBTM {

   // Specific ones

   /// Helpdesk fields of helpdesk profiles
   static public $helpdesk_rights = array('create_ticket_on_login', 'followup',
                                          'knowbase', 'helpdesk_hardware', 'helpdesk_item_type',
                                          'password_update', 'reminder_public',
                                          'reservation', 'rssfeed_public',
                                          'show_group_hardware', 'task', 'ticket',
                                          'tickettemplates_id', 'ticket_cost',
                                          'ticketvalidation');


   /// Common fields used for all profiles type
   static public $common_fields = array('id', 'interface', 'is_default', 'name');


   //TODO right never used ?
   /// Fields not related to a basic right
//   static public $noright_fields = array('comment', 'change_status', 'date_mod',
//                                         'helpdesk_hardware','helpdesk_item_type', 'own_ticket',
//                                         'problem_status', 'show_group_hardware',
//                                         'show_group_ticket', 'ticket_status');

   var $dohistory = true;

   static $rightname = 'profile';

   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'MassiveAction'.MassiveAction::CLASS_ACTION_SEPARATOR.'update';
      return $forbidden;
   }


   static function getTypeName($nb=0) {
      return _n('Profile', 'Profiles', $nb);
   }


   function defineTabs($options=array()) {

      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('Profile_User', $ong, $options);
      $this->addStandardTab('Log',$ong, $options);
      return $ong;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case __CLASS__ :
               if ($item->fields['interface'] == 'helpdesk') {
                  $ong[1] = __('Simplified interface'); // Helpdesk

               } else {
                  /// TODO split it in 2 or 3 tabs
                  $ong[2] = __('Assets');
                  $ong[3] =  sprintf(__('%1$s/%2$s'), __('Management'), __('Tools'));
                  $ong[4] = __('Assistance');
                  $ong[5] = __('Life cycles');
                  $ong[6] = __('Administration');
                  $ong[7] = __('Setup');
               }
               return $ong;
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == __CLASS__) {
         $item->cleanProfile();
         switch ($tabnum) {
            case 1 :
               $item->showFormHelpdesk();
               break;

            case 2 :
               $item->showFormAsset();
               break;

            case 3 :
               $item->showFormInventory();
               break;

            case 4 :
               $item->showFormTracking();
               break;

            case 5 :
               $item->showFormLifeCycle();
               break;

            case 6 :
               $item->showFormAdmin();
               break;
            case 7 :
               $item->showFormSetup();
               break;

         }
      }
      return true;
   }


   function post_updateItem($history=1) {
      global $DB;

      if (count($this->profileRight) > 0) {
         $profile_right = new ProfileRight();
         $profile_right->updateProfileRights($this->getID(), $this->profileRight);
         unset($this->profileRight);
      }

      if (in_array('is_default',$this->updates) && ($this->input["is_default"] == 1)) {
         $query = "UPDATE ". $this->getTable()."
                   SET `is_default` = '0'
                   WHERE `id` <> '".$this->input['id']."'";
         $DB->query($query);
      }
   }


   function post_addItem() {
      global $DB;

      if (count($this->profileRight) > 0) {
         $profile_right = new ProfileRight();
         $profile_right->updateProfileRights($this->getID(), $this->profileRight);
         unset($this->profileRight);
      }
      if (isset($this->fields['is_default']) && ($this->fields["is_default"] == 1)) {
         $query = "UPDATE ". $this->getTable()."
                   SET `is_default` = '0'
                   WHERE `id` <> '".$this->fields['id']."'";
         $DB->query($query);
      }
   }


   function cleanDBonPurge() {
      global $DB;

      $gpr = new ProfileRight();
      $gpr->cleanDBonItemDelete($this->getType(), $this->fields['id']);

      $gpu = new Profile_User();
      $gpu->cleanDBonItemDelete($this->getType(), $this->fields['id']);


      Rule::cleanForItemAction($this);
      // PROFILES and UNIQUE_PROFILE in RuleMailcollector
      Rule::cleanForItemCriteria($this, 'PROFILES');
      Rule::cleanForItemCriteria($this, 'UNIQUE_PROFILE');

      $gki = new KnowbaseItem_Profile();
      $gki->cleanDBonItemDelete($this->getType(), $this->fields['id']);

      $gr = new Profile_Reminder();
      $gr->cleanDBonItemDelete($this->getType(), $this->fields['id']);

   }


   function prepareInputForUpdate($input) {

      // Check for faq
      if (isset($input["interface"]) && ($input["interface"] == 'helpdesk')) {
         if (isset($input["faq"]) && ($input["faq"] == KnowbaseItem::PUBLISHFAQ)) {
            $input["faq"] == KnowbaseItem::READFAQ;
         }
      }

      if (isset($input["_helpdesk_item_types"])) {
         if ((!isset($input["helpdesk_item_type"])) || (!is_array($input["helpdesk_item_type"]))) {
            $input["helpdesk_item_type"] = array();
         }
         // Linear_HIT: $input["helpdesk_item_type"] = array_keys($input["helpdesk_item_type"]
         $input["helpdesk_item_type"] = exportArrayToDB($input["helpdesk_item_type"]);
      }

      if (isset($input['helpdesk_hardware']) && is_array($input['helpdesk_hardware'])) {
         $helpdesk_hardware = 0;
         foreach ($input['helpdesk_hardware'] as $right => $value) {
            if ($value) {
               $helpdesk_hardware += $right;
            }
         }
         $input['helpdesk_hardware'] = $helpdesk_hardware;
      }

      if (isset($input["_cycle_ticket"])) {
         $tab   = Ticket::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_ticket"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["ticket_status"] = exportArrayToDB($cycle);
      }

      if (isset($input["_cycle_problem"])) {
         $tab   = Problem::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_problem"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["problem_status"] = exportArrayToDB($cycle);
      }

      if (isset($input["_cycle_change"])) {
         $tab   = Change::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_change"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["change_status"] = exportArrayToDB($cycle);
      }

      $this->profileRight = array();
      foreach (ProfileRight::getAllPossibleRights() as $right => $default) {
         if (isset($input['_'.$right])) {
            if (!is_array($input['_'.$right])) {
               // TODO: what about changing password_update => UPDATE and show_group_hardware => READ ?
               // If there is only one element, then, the right is 0 or 1 (ie.: password_update, show_group_hardware)
               $input['_'.$right] = array('1' => $input['_'.$right]);
            }
            $newvalue = 0;
            foreach ($input['_'.$right] as $value => $valid) {
               if ($valid) {
                  if (($underscore_pos = strpos($value, '_')) !== false) {
                     $value = substr($value, 0, $underscore_pos);
                  }
                  $newvalue += $value;
               }
            }
            // Update rights only if changed
            if (!isset($this->fields[$right]) || ($this->fields[$right] != $newvalue)) {
               $this->profileRight[$right] = $newvalue;
            }
            unset($input['_'.$right]);
         }
      }

      // check if right if the last write profile on Profile object
      if (($this->fields['profile'] & UPDATE)
          && isset($input['profile']) && !($input['profile'] & UPDATE)
          && (countElementsInTable("glpi_profilerights",
                                   "`name` = 'profile' AND `rights` & ".UPDATE))) {
         Session::addMessageAfterRedirect(__("This profile is the last with write rights on profiles"),
         false, ERROR);
         Session::addMessageAfterRedirect(__("Deletion refused"), false, ERROR);
         unset($input["profile"]);
      }

      return $input;
   }


   /**
    * check right before delete
    *
    * @since version 0.85
    *
    * @return boolean
   **/
   function pre_deleteItem() {
      global $DB;

      if (($this->fields['profile'] & DELETE)
          && (countElementsInTable("glpi_profilerights",
                                   "`name` = 'profile' AND `rights` & ".DELETE))) {
          Session::addMessageAfterRedirect(__("This profile is the last with write rights on profiles"),
                                           false, ERROR);
          Session::addMessageAfterRedirect(__("Deletion refused"), false, ERROR);
          return false;
      }
      return true;
   }


   function prepareInputForAdd($input) {

      if (isset($input["helpdesk_item_type"])) {
         $input["helpdesk_item_type"] = exportArrayToDB($input["helpdesk_item_type"]);
      }

      $this->profileRight = array();
      foreach (ProfileRight::getAllPossibleRights() as $right => $default) {
         if (isset($input[$right])) {
            $this->profileRight[$right] = $input[$right];
            unset($input[$right]);
         }
      }

      return $input;
   }


   /**
    * Unset unused rights for helpdesk
   **/
   function cleanProfile() {

   /// TODO MoYo : do not understand why this (commented lines)?
   /// self::$helpdesk_rights is an array so == is not appropriate
//       if ((self::$helpdesk_rights == 'reservation')
//           && !ReservationItem::RESERVEANITEM) {
//          return false;
//       }
//       if ((self::$helpdesk_rights == 'ticket')
//           && !Session::haveRightsOr("ticket", array(CREATE, Ticket::READGROUP))) {
//          return false;
//       }
//       if ((self::$helpdesk_rights == 'followup')
//           && !Session::haveRightsOr('followup',
//                                     array(TicketFollowup::ADDMYTICKET, TicketFollowup::UPDATEMY,
//                                           TicketFollowup::SEEPUBLIC, TicketFollowup::ADDGROUPTICKET))) {
//          return false;
//       }
//       if ((self::$helpdesk_rights == 'task')
//          && !Session::haveRight('followup', TicketTask::SEEPUBLIC)) {
//          return false;
//       }
//       if ((self::$helpdesk_rights == 'ticketvalidation')
//             && !Session::haveRightsOr('ticketvalidation', array(TicketValidation::CREATEREQUEST,
//                                                           TicketValidation::CREATEINCIDENT,
//                                                           TicketValidation::VALIDATEREQUEST,
//                                                           TicketValidation::VALIDATEINCIDENT))) {
//          return false;
//       }


      if ($this->fields["interface"] == "helpdesk") {
         foreach ($this->fields as $key=>$val) {
            if (!in_array($key,self::$common_fields)
                && !in_array($key,self::$helpdesk_rights)) {
               unset($this->fields[$key]);
            }
         }
      }

      // decode array
      if (isset($this->fields["helpdesk_item_type"])
          && !is_array($this->fields["helpdesk_item_type"])) {

         $this->fields["helpdesk_item_type"] = importArrayFromDB($this->fields["helpdesk_item_type"]);
      }

      // Empty/NULL case
      if (!isset($this->fields["helpdesk_item_type"])
          || !is_array($this->fields["helpdesk_item_type"])) {

         $this->fields["helpdesk_item_type"] = array();
      }

      // Decode status array
      $fields_to_decode = array('ticket_status', 'problem_status', 'change_status');
      foreach ($fields_to_decode as $val) {
         if (isset($this->fields[$val]) && !is_array($this->fields[$val])) {
            $this->fields[$val] = importArrayFromDB($this->fields[$val]);
            // Need to be an array not a null value
            if (is_null($this->fields[$val])) {
               $this->fields[$val] = array();
            }
         }
      }
   }


   /**
    * Get SQL restrict request to determine profiles with less rights than the active one
    *
    * @param $separator string   Separator used at the beginning of the request (default 'AND')
    *
    * @return SQL restrict string
   **/
   static function getUnderActiveProfileRestrictRequest($separator="AND") {

      /// TODO : MoYo : je ne comprend pas du tout ces controles... self::$helpdesk_rights est un tableau le == n'a pas de sens
      if ((self::$helpdesk_rights == 'reservation')
          & !ReservationItem::RESERVEANITEM) {
         return false;
      }
      if ((self::$helpdesk_rights == 'ticket')
          & !Session::haveRightsOr("ticket", array(CREATE, Ticket::READGROUP))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'followup')
          && !Session::haveRightsOr('followup',
                                    array(TicketFollowup::ADDMYTICKET, TicketFollowup::UPDATEMY,
                                          TicketFollowup::SEEPUBLIC))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'task')
         && !Session::haveRight('task', TicketTask::SEEPUBLIC)) {
         return false;
      }
      if ((self::$helpdesk_rights == 'validation')
            && !Session::haveRightsOr('validation', array(TicketValidation::CREATEREQUEST,
                                                          TicketValidation::CREATEINCIDENT,
                                                          TicketValidation::VALIDATEREQUEST,
                                                          TicketValidation::VALIDATEINCIDENT))) {
         return false;
      }


      $query = $separator ." ";

      // Not logged -> no profile to see
      if (!isset($_SESSION['glpiactiveprofile'])) {
         return $query." 0 ";
      }

      // Profile right : may modify profile so can attach all profile
      if (Profile::canCreate()) {
         return $query." 1 ";
      }

      if ($_SESSION['glpiactiveprofile']['interface']=='central') {
         $query .= " (`glpi_profiles`.`interface` = 'helpdesk') " ;
      }

      $query .= " OR (`glpi_profiles`.`interface` = '" .
                $_SESSION['glpiactiveprofile']['interface'] . "' ";

      // First, get all possible rights
      $right_subqueries = array();
      foreach (ProfileRight::getAllPossibleRights() as $key => $default) {
         $val = $_SESSION['glpiactiveprofile'][$key];

         if (!is_array($val) // Do not include entities field added by login
             && (($_SESSION['glpiactiveprofile']['interface'] == 'central')
                 || in_array($key,self::$helpdesk_rights))) {

            $right_subqueries[] = "(`glpi_profilerights`.`name` = '$key'
                                   AND (`glpi_profilerights`.`rights` | $val) = $val)";
         }
      }
      $query .= " AND ".count($right_subqueries)." = (
                    SELECT count(*)
                    FROM `glpi_profilerights`
                    WHERE `glpi_profilerights`.`profiles_id` = `glpi_profiles`.`id`
                     AND (".implode(' OR ', $right_subqueries).")))";
      return $query;
   }


   /**
    * Is the current user have more right than all profiles in parameters
    *
    * @param $IDs array of profile ID to test
    *
    * @return boolean true if have more right
   **/
   static function currentUserHaveMoreRightThan($IDs=array()) {
      global $DB;

      if (count($IDs) == 0) {
         // Check all profiles (means more right than all possible profiles)
         return (countElementsInTable('glpi_profiles')
                     == countElementsInTable('glpi_profiles',
                                             self::getUnderActiveProfileRestrictRequest('')));
      }
      $under_profiles = array();
      $query          = "SELECT *
                         FROM `glpi_profiles` ".
                         self::getUnderActiveProfileRestrictRequest("WHERE");
      $result         = $DB->query($query);

      while ($data = $DB->fetch_assoc($result)) {
         $under_profiles[$data['id']] = $data['id'];
      }

      foreach ($IDs as $ID) {
         if (!isset($under_profiles[$ID])) {
            return false;
         }
      }
      return true;
   }


   function showLegend() {

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><td width='70' style='text-decoration:underline' class='b'>";
      echo __('Caption')."</td>";
      echo "<td class='tab_bg_4' width='15' style='border:1px solid black'></td>";
      echo "<td class='b'>".__('Global right')."</td></tr>\n";
      echo "<tr class='tab_bg_2'><td></td>";
      echo "<td class='tab_bg_2' width='15' style='border:1px solid black'></td>";
      echo "<td class='b'>".__('Entity right')."</td></tr>";
      echo "</table></div>\n";
   }


   function post_getEmpty() {

      $this->fields["interface"] = "helpdesk";
      $this->fields["name"]      = __('Without name');
      unset($_SESSION['glpi_all_possible_rights']);
      $this->fields = array_merge($this->fields, ProfileRight::getAllPossibleRights());
   }


   function post_getFromDB() {
      $this->fields = array_merge($this->fields, ProfileRight::getProfileRights($this->getID()));
   }

   /**
    * Print the profile form headers
    *
    * @param $ID        integer : Id of the item to print
    * @param $options   array of possible options
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    * @return boolean item found
    **/
   function showForm($ID, $options=array()) {

      $onfocus = "";
      $new     = false;
      $rowspan = 5;
      if ($ID > 0) {
         $rowspan++;
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, CREATE);
         $onfocus = "onfocus=\"if (this.value=='".$this->fields["name"]."') this.value='';\"";
         $new     = true;
      }

      $rand = mt_rand();

      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'><td>".__('Name')."</td>";
      echo "<td><input type='text' name='name' value=\"".$this->fields["name"]."\" $onfocus></td>";
      echo "<td rowspan='$rowspan' class='middle right'>".__('Comments')."</td>";
      echo "<td class='center middle' rowspan='$rowspan'>";
      echo "<textarea cols='45' rows='4' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".__('Default profile')."</td><td>";
      Html::showCheckbox(array('name'    => 'is_default',
                               'checked' => $this->fields['is_default']));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__("Profile's interface")."</td>";
      echo "<td>";
      Dropdown::showFromArray('interface', self::getInterfaces(),
                              array('value'=>$this->fields["interface"]));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__('Update password')."</td><td>";
      Html::showCheckbox(array('name'    => '_password_update',
                               'checked' => $this->fields['password_update']));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__('Ticket creation form on login')."</td><td>";
      Html::showCheckbox(array('name'    => 'create_ticket_on_login',
                               'checked' => $this->fields['create_ticket_on_login']));
      echo "</td></tr>\n";

      if ($ID > 0) {
         echo "<tr class='tab_bg_1'><td>".__('Last update')."</td>";
         echo "<td>";
         echo ($this->fields["date_mod"] ? Html::convDateTime($this->fields["date_mod"])
                                         : __('Never'));
         echo "</td></tr>";
      }

      $this->showFormButtons($options);

      return true;
   }


   /**
    * Print the helpdesk right form for the current profile
   **/
   function showFormHelpdesk() {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='spaced'>";
      if ($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE))) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $matrix_options = array('canedit'       => $canedit,
                              'default_class' => 'tab_bg_2');

      $rights = array(array('rights'     => Profile::getRightsFor('Ticket', 'helpdesk'),
                            'label'      => _n('Ticket', 'Tickets', 2),
                            'field'      => 'ticket'),
                      array('rights'     => Profile::getRightsFor('TicketFollowup', 'helpdesk'),
                            'label'      => _n('Followup', 'Followups', 2),
                            'field'      => 'followup'),
                      array('rights'     => Profile::getRightsFor('TicketTask', 'helpdesk'),
                            'label'      => _n('Task', 'Tasks', 2),
                            'field'      => 'task'),
                      array('rights'     => Profile::getRightsFor('TicketValidation', 'helpdesk'),
                            'label'      => _n('Validation', 'Validations', 2),
                            'field'      => 'validation'));

      $matrix_options['title'] = __('Assistance');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='tab_bg_1'><th colspan='4'>".__('Assistance')."</th></tr>\n";

      // TODO : switch to matrix
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Link with items for the creation of tickets')."</td>";
      echo "<td colspan='5'>";
      self::getLinearRightChoice(self::getHelpdeskHardwareTypes(true),
                                 array('field' => 'helpdesk_hardware',
                                       'value' => $this->fields['helpdesk_hardware']));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Associable items to a ticket')."</td>";
      echo "<td colspan='5'><input type='hidden' name='_helpdesk_item_types' value='1'>";
      self::dropdownHelpdeskItemtypes(array('values' => $this->fields["helpdesk_item_type"]));
      // Linear_HIT
//      self::getLinearRightChoice(self::getHelpdeskItemtypes(),
//                                 array('field'         => 'helpdesk_item_type',
//                                       'value'         => $this->fields['helpdesk_item_type'],
//                                      'check_all'     => true,
//                                       'zero_on_empty' => false,
//                                       'max_per_line'  => 4,
//                                       'check_method'  =>
//                                       function ($element, $field) {
//                                          return in_array($element,$field);
//                                       }));
      echo "</td>";
      echo "</tr>\n";


      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Default ticket template')."</td><td>";
      // Only root entity ones and recursive
      $options = array('value'     => $this->fields["tickettemplates_id"],
                       'entity'    => 0);
      if (Session::isMultiEntitiesMode()) {
         $options['condition'] = '`is_recursive`';
      }
      $entity = implode(",", $_SESSION['glpiactiveentities']);
      if ($entity != 0) {
         $options['addicon'] = false;
      }

      TicketTemplate::dropdown($options);
      echo "</td>";
      echo "<td colspan='2'>&nbsp;";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('See hardware of my groups')."</td><td>";
      Html::showCheckbox(array('name' => '_show_group_hardware',
                               'checked' => $this->fields['show_group_hardware']));
      echo "</td></tr>\n";

      echo "</table>\n";

      if (($this->fields["interface"] == "helpdesk")
          && ($this->fields["knowbase"] == KnowbaseItem::PUBLISHFAQ)) {
         $this->fields["knowbase"] = KnowbaseItem::READFAQ;
      }

      $rights = array(array('rights'    => Profile::getRightsFor('KnowbaseItem', 'helpdesk'),
                            'label'     => __('FAQ'),
                            'field'     => 'knowbase'),
                      array('rights'  => Profile::getRightsFor('ReservationItem', 'helpdesk'),
                            'label'     => _n('Reservation', 'Reservations', 2),
                            'field'     => 'reservation'),
                      array('rights'    => Profile::getRightsFor('Reminder', 'helpdesk'),
                            'label'     => _n('Public reminder', 'Public reminders', 2),
                            'field'     => 'reminder_public'),
                      array('rights'    => Profile::getRightsFor('RSSFeed', 'helpdesk'),
                            'label'     => _n('Public RSS feed', 'Public RSS feeds', 2),
                            'field'     => 'rssfeed_public'));

      $matrix_options['title'] = __('Tools');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      if ($canedit) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\"".__s('Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
   }



   /**
    * Print the Asset rights form for the current profile
    *
    * @since version 0.85
    *
    * @param $openform  boolean open the form (true by default)
    * @param $closeform boolean close the form (true by default)
    *
   **/
   function showFormAsset($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(UPDATE, CREATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $rights = array(array('itemtype'  => 'Computer',
                            'label'     => _n('Computer', 'Computers', 2),
                            'field'     => 'computer'),
                      array('itemtype'  => 'Monitor',
                            'label'     => _n('Monitor', 'Monitors', 2),
                            'field'     => 'monitor'),
                      array('itemtype'  => 'Software',
                            'label'     => _n('Software', 'Software', 2),
                            'field'     => 'software'),
                      array('itemtype'  => 'NetworkEquipment',
                            'label'     => _n('Network', 'Networks', 2),
                            'field'     => 'networking'),
                      array('itemtype'  => 'Printer',
                            'label'     => _n('Printer', 'Printers', 2),
                            'field'     => 'printer'),
                      array('itemtype'  => 'Cartridge',
                            'label'     => _n('Cartridge', 'Cartridges', 2),
                            'field'     => 'cartridge'),
                      array('itemtype'  => 'Consumable',
                            'label'     => _n('Consumable', 'Consumables', 2),
                            'field'     => 'consumable'),
                      array('itemtype'  => 'Phone',
                            'label'     => _n('Phone', 'Phones', 2),
                            'field'     => 'phone'),
                      array('itemtype'  => 'Peripheral',
                            'label'     => _n('Device', 'Devices', 2),
                            'field'     => 'peripheral'),
                      array('itemtype'  => 'NetworkName',
                            'label'     => __('Internet'),
                            'field'     => 'internet'));

      $this->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                      'default_class' => 'tab_bg_2',
                                                      'title'         => __('Assets')));

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }

      echo "</div>";
   }


   /**
    * Print the Management/Tools rights form for the current profile
    *
    * @param $openform  boolean open the form (true by default)
    * @param $closeform boolean close the form (true by default)
   **/
   function showFormInventory($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='spaced'>";

      if (($canedit = Session::haveRightsOr(self::$rightname, array(UPDATE, CREATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $matrix_options = array('canedit'       => $canedit,
                              'default_class' => 'tab_bg_2');


      $rights = array(array('itemtype'  => 'Contact',
                            'label'     => _n('Contacts', 'Contacts', 2)." / ".
                                           _n('Supplier', 'Suppliers', 2),
                            'field'     => 'contact_enterprise'),
                      array('itemtype'  => 'Document',
                            'label'     => _n('Document', 'Documents', 2),
                            'field'     => 'document'),
                      array('itemtype'  => 'Contract',
                            'label'     => _n('Contract', 'Contracts', 2),
                            'field'     => 'contract'),
                      array('itemtype'  => 'Infocom',
                            'label'     => __('Financial and administratives information'),
                            'field'     => 'infocom'),
                      array('itemtype'  => 'Budget',
                            'label'     => __('Budget'),
                            'field'     => 'budget'));
      $matrix_options['title'] = __('Management');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'  => 'Reminder',
                            'label'     => _n('Public reminder', 'Public reminders', 2),
                            'field'     => 'reminder_public'),
                      array('itemtype'  => 'RSSFeed',
                            'label'     => _n('Public RSS feed', 'Public RSS feeds', 2),
                            'field'     => 'rssfeed_public'),
                      array('itemtype'  => 'Bookmark',
                            'label'     => _n('Public bookmark', 'Public bookmarks', 2),
                            'field'     => 'bookmark_public'),
                      array('itemtype'  => 'Report',
                            'label'     => _n('Report', 'Reports', 2),
                            'field'     => 'reports'),
                      array('itemtype'  => 'KnowbaseItem',
                            'label'     => __('Knowledge base'),
                            'field'     => 'knowbase'),
                      array('itemtype'  => 'ReservationItem',
                            'label'     => __('Administration of reservations'),
                            'field'     => 'reservation'));
      $matrix_options['title'] = __('Tools');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }


   /**
    * Print the Tracking right form for the current profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormTracking($openform=true, $closeform=true) {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }


      // TODO: uniformize the class of forms ?
      echo "<div class='spaced'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<table class='tab_cadre_fixe'>";
      // Assistance / Tracking-helpdesk
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Assistance')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Ticket', 'Tickets', 2).': '.__('Default ticket template')."</td><td  width='30%'>";
      // Only root entity ones and recursive
      $options = array('value'     => $this->fields["tickettemplates_id"],
                       'entity'    => 0);
      if (Session::isMultiEntitiesMode()) {
         $options['condition'] = '`is_recursive`';
      }
      $entity = implode(",", $_SESSION['glpiactiveentities']);
      if ($entity != 0) {
         $options['addicon'] = false;
      }

      TicketTemplate::dropdown($options);
      echo "</td></tr>\n";

      echo "</table>";


      $matrix_options = array('canedit'       => $canedit,
                              'default_class' => 'tab_bg_2');

      $rights = array(array('itemtype'  => 'Ticket',
                            'label'     => _n('Ticket', 'Tickets', 2),
                            'field'     => 'ticket'),
                      array('itemtype'  => 'TicketCost',
                            'label'     => _n('Ticket cost', 'Ticket costs', 2),
                            'field'     => 'ticketcost'),
                      array('itemtype'  => 'TicketRecurrent',
                            'label'     => __('Recurrent tickets'),
                            'field'     => 'ticketrecurrent'),
                      array('itemtype'  => 'TicketTemplate',
                            'label'     => _n('Ticket template', 'Ticket templates', 2),
                            'field'     => 'tickettemplate'));
      $matrix_options['title'] = _n('Ticket', 'Tickets', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'  => 'TicketFollowup',
                            'label'     => _n('Followup', 'Followups', 2),
                            'field'     => 'followup'),
                      array('itemtype'  => 'TicketTask',
                            'label'     => _n('Task', 'Tasks', 2),
                            'field'     => 'task'));
      $matrix_options['title'] = _n('Followup', 'Followups', 2)." / "._n('Task', 'Tasks', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'  => 'TicketValidation',
                            'label'     => _n('Validation', 'Validations', 2),
                            'field'     => 'ticketvalidation'));
      $matrix_options['title'] = _n('Validation', 'Validations', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);


      echo "<table class='tab_cadre_fixe'>";

      echo "<tr class='tab_bg_5'><th colspan='6'>".__('Association')."</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('See hardware of my groups')."</td><td>";
      Html::showCheckbox(array('name'    => '_show_group_hardware',
                               'checked' => $this->fields['show_group_hardware']));
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Link with items for the creation of tickets')."</td>";
      echo "\n<td>";
      self::getLinearRightChoice(self::getHelpdeskHardwareTypes(true),
                                 array('field' => 'helpdesk_hardware',
                                       'value' => $this->fields['helpdesk_hardware']));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Associable items to a ticket')."</td>";
      echo "<td  colspan='5'><input type='hidden' name='_helpdesk_item_types' value='1'>";
      self::dropdownHelpdeskItemtypes(array('values' => $this->fields["helpdesk_item_type"]));
      // Linear_HIT
//      self::getLinearRightChoice(self::getHelpdeskItemtypes(),
//                                     array('field'         => 'helpdesk_item_type',
//                                           'value'         => $this->fields['helpdesk_item_type'],
//                                           'check_all'     => true,
//                                           'zero_on_empty' => false,
//                                           'max_per_line'  => 4,
//                                           'check_method'  =>
//                                           function ($element, $field) {
//                                              return in_array($element,$field);
//                                           }));
      echo "</td>";
      echo "</tr>\n";
      echo "</table>";


      $rights = array(array('itemtype'   => 'Stat',
                            'label'      => __('Statistics'),
                            'field'      => 'statistic'),
                      array('itemtype'   => 'Planning',
                            'label'      => __('Planning'),
                            'field'      => 'planning'));
      $matrix_options['title'] = __('Visibility');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'   => 'Problem',
                            'label'      => _n('Problem', 'Problems', 2),
                            'field'      => 'problem'));
      $matrix_options['title'] = _n('Problem', 'Problems', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'   => 'Change',
                            'label'      => _n('Change', 'Changes', 2),
                            'field'      => 'change'),
                      array('itemtype'  => 'ChangeValidation',
                            'label'     => _n('Validation', 'Validations', 2),
                            'field'     => 'changevalidation'));
      $matrix_options['title'] = _n('Change', 'Changes', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'   => 'Project',
                            'label'      => _n('Project', 'Projects', 2),
                            'field'      => 'project'));
      $matrix_options['title'] = _n('Project', 'Projects', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);
      

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }


   /**
    * Display the matrix of the elements lifecycle of the elements
    *
    * @since version 0.85
    *
    * @param $title          the kind of lifecycle
    * @param $html_field     field that is sent to _POST
    * @param $db_field       field inside the DB (to get current state)
    * @param $statuses       all available statuses for the given cycle (obj::getAllStatusArray())
    * @param $canedit        can we edit the elements ?
    *
    * @return nothing
   **/
   function displayLifeCycleMatrix($title, $html_field, $db_field, $statuses, $canedit) {

      $columns  = array();
      $rows     = array();

      foreach ($statuses as $index_1 => $status_1) {
         $columns[$index_1] = $status_1;
         $row               = array('label'      => $status_1,
                                    'columns'    => array());

         foreach ($statuses as $index_2 => $status_2) {
            $content = array('checked' => true);
            if (isset($this->fields[$db_field][$index_1][$index_2])) {
               $content['checked'] = $this->fields[$db_field][$index_1][$index_2];
            }
            if (($index_1 == $index_2) || (!$canedit)) {
               $content['readonly'] = true;
            }
            $row['columns'][$index_2] = $content;
         }
         $rows[$html_field."[$index_1]"] = $row;
      }
      Html::showCheckboxMatrix($columns, $rows,
                               array('title'      => $title,
                                     'first_cell' => '<b>'.__("From \ To").'</b>'));
   }


   /**
   * Print the Life Cycles form for the current profile
   *
   * @param $openform   boolean  open the form (true by default)
   * @param $closeform  boolean  close the form (true by default)
   **/
   function showFormLifeCycle($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='spaced'>";

      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $this->displayLifeCycleMatrix(__('Life cycle of tickets'), '_cycle_ticket', 'ticket_status',
                                    Ticket::getAllStatusArray(), $canedit);

      $this->displayLifeCycleMatrix(__('Life cycle of problems'), '_cycle_problem',
                                    'problem_status', Problem::getAllStatusArray(), $canedit);

      $this->displayLifeCycleMatrix(__('Life cycle of changes'), '_cycle_change', 'change_status',
                                    Change::getAllStatusArray(), $canedit);

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }


   /**
    * Print the central form for a profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormAdmin($openform=true, $closeform=true) {
      global $DB;

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='firstbloc'>";

      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $matrix_options = array('canedit'       => $canedit,
                              'default_class' => 'tab_bg_4');

      $rights = array(array('itemtype'  => 'User',
                            'label'     => _n('User', 'Users', 2),
                            'field'     => 'user',
                            'row_class' => 'tab_bg_2'),
                      array('itemtype'  => 'Entity',
                            'label'     => _n('Entity', 'Entities', 2),
                            'field'     => 'entity'),
                      array('itemtype'  => 'Group',
                            'label'     => _n('Group', 'Groups', 2),
                            'field'     => 'group'),
                      array('itemtype'  => 'Profile',
                            'label'     => _n('Profile', 'Profiles', 2),
                            'field'     => 'profile'),
                      array('itemtype'  => 'QueuedMail',
                            'label'     => __('Mail queue'),
                            'field'     => 'queuedmail'),
                      array('itemtype'  => 'Backup',
                            'label'     => __('Maintenance'),
                            'field'     => 'backup'),
                      array('itemtype'  => 'Log',
                            'label'     => _n('Log', 'Logs', 2),
                            'field'     => 'logs'),
                      array('itemtype'  => 'Transfer',
                            'label'     => __('Transfer'),
                            'field'     => 'transfer'));
      $matrix_options['title'] = __('Administration');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'  => 'Rule',
                            'label'     => __('Authorizations assignment rules'),
                            'field'     => 'rule_ldap'),
                      array('itemtype'  => 'RuleImportComputer',
                            'label'     => __('Rules for assigning a computer to an entity'),
                            'field'     => 'rule_import'),
                      array('itemtype'  => 'RuleMailCollector',
                            'label'     => __('Rules for assigning a ticket created through a mails receiver'),
                            'field'     => 'rule_mailcollector'),
                      array('itemtype'  => 'RuleSoftwareCategory',
                            'label'     => __('Rules for assigning a category to a software'),
                            'field'     => 'rule_softwarecategories'),
                      array('itemtype'  => 'RuleTicket',
                            'label'     => __('Business rules for tickets (entity)'),
                            'field'     => 'rule_ticket',
                            'row_class' => 'tab_bg_2'));
      $matrix_options['title'] = _n('Rule', 'Rules', 2);
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      $rights = array(array('itemtype'  => 'RuleDictionnaryDropdown',
                            'label'     => __('Dropdowns dictionary'),
                            'field'     => 'rule_dictionnary_dropdown'),
                      array('itemtype'  => 'RuleDictionnarySoftware',
                            'label'     => __('Software dictionary'),
                            'field'     => 'rule_dictionnary_software'),
                      array('itemtype'  => 'RuleDictionnaryPrinter',
                            'label'     => __('Printers dictionnary'),
                            'field'     => 'rule_dictionnary_printer'));
      $matrix_options['title'] = __('Dropdowns dictionary');
      $this->displayRightsChoiceMatrix($rights, $matrix_options);

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      $this->showLegend();
   }

   /**
    * Print the central form for a profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormSetup($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      // TODO: uniformize the class of forms ?
      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      $dropdown_rights = CommonDBTM::getRights();
      unset($dropdown_rights[DELETE]);

      $rights = array(array('itemtype'  => 'Config',
                            'label'     => __('General setup'),
                            'field'     => 'config'),
                      array('itemtype'  => 'DisplayPreference',
                            'label'     => __('Search result display'),
                            'field'     => 'search_config'),
                      array('itemtype'  => 'Item_Devices',
                            'label'     => _n('Component', 'Components', 2),
                            'field'     => 'device'),
                      array('rights'    => $dropdown_rights,
                            'label'     => _n('Global dropdown', 'Global dropdowns', 2),
                            'field'     => 'dropdown'),
                      __('Entity dropdowns'),
                      array('itemtype'  => 'Domain',
                            'label'     => _n('Domain', 'Domains', 2),
                            'field'     => 'domain'),
                      array('itemtype'  => 'Location',
                            'label'     => _n('Location', 'Locations', 2),
                            'field'     => 'location'),
                      array('itemtype'  => 'ITILCategory',
                            'label'     => _n('Category of ticket', 'Categories of tickets', 2),
                            'field'     => 'itilcategory'),
                      array('itemtype'  => 'KnowbaseItemCategory',
                            'label'     => _n('Knowledge base category', 'Knowledge base categories', 2),
                            'field'     => 'knowbasecategory'),
                      array('itemtype'  => 'Netpoint',
                            'label'     => _n('Network outlet', 'Network outlets', 2),
                            'field'     => 'netpoint'),
                      array('itemtype'  => 'TaskCategory',
                            'label'     => _n('Tasks category','Tasks categories', 2),
                            'field'     => 'taskcategory'),
                      array('itemtype'  => 'State',
                            'label'     => _n('Status of items', 'Statuses of items', 2),
                            'field'     => 'state'),
                      array('itemtype'  => 'SolutionTemplate',
                            'label'     => _n('Solution template', 'Solution templates', 2),
                            'field'     => 'solutiontemplate'),
                      array('itemtype'  => 'Calendar',
                            'label'     => _n('Calendar', 'Calendars', 2),
                            'field'     => 'calendar'),
                      array('itemtype'  => 'DocumentType',
                            'label'     => __('Document type'),
                            'field'     => 'typedoc'),
                      array('itemtype'  => 'Link',
                            'label'     => _n('External link', 'External links',2),
                            'field'     => 'link'),
                      array('itemtype'  => 'Notification',
                            'label'     => _n('Notification', 'Notifications',2),
                            'field'     => 'notification'),
                      array('itemtype'  => 'SLA',
                            'label'     => __('SLA'),
                            'field'     => 'sla'));

      $this->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                      'default_class' => 'tab_bg_2',
                                                      'title'         => __('Setup')));

      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      $this->showLegend();
   }


   function getSearchOptions() {

      $tab                       = array();
      $tab['common']             = __('Characteristics');

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['massiveaction']   = false;

      $tab[19]['table']          = $this->getTable();
      $tab[19]['field']          = 'date_mod';
      $tab[19]['name']           = __('Last update');
      $tab[19]['datatype']       = 'datetime';
      $tab[19]['massiveaction']  = false;

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'interface';
      $tab[2]['name']            = __("Profile's interface");
      $tab[2]['massiveaction']   = false;
      $tab[2]['datatype']        = 'specific';

      $tab[3]['table']           = $this->getTable();
      $tab[3]['field']           = 'is_default';
      $tab[3]['name']            = __('Default profile');
      $tab[3]['datatype']        = 'bool';
      $tab[3]['massiveaction']   = false;

      $tab[118]['table']         = $this->getTable();
      $tab[118]['field']         = 'create_ticket_on_login';
      $tab[118]['name']          = __('Ticket creation form on login');
      $tab[118]['datatype']      = 'bool';

      $tab[16]['table']          = $this->getTable();
      $tab[16]['field']          = 'comment';
      $tab[16]['name']           = __('Comments');
      $tab[16]['datatype']       = 'text';

      $tab['inventory']          = __('Assets');

      $tab[20]['table']          = 'glpi_profilerights';
      $tab[20]['field']          = 'rights';
      $tab[20]['name']           = _n('Computer', 'Computers', 2);
      $tab[20]['datatype']       = 'right';
      $tab[20]['rightclass']     = 'Computer';
      $tab[20]['rightname']      = 'computer';
      $tab[20]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'computer'");

      $tab[21]['table']          = 'glpi_profilerights';
      $tab[21]['field']          = 'rights';
      $tab[21]['name']           = _n('Monitor', 'Monitors', 2);
      $tab[21]['datatype']       = 'right';
      $tab[21]['rightclass']     = 'Monitor';
      $tab[21]['rightname']      = 'monitor';
      $tab[21]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'monitor'");

      $tab[22]['table']          = 'glpi_profilerights';
      $tab[22]['field']          = 'rights';
      $tab[22]['name']           = _n('Software', 'Software', 2);
      $tab[22]['datatype']       = 'right';
      $tab[22]['rightclass']     = 'Software';
      $tab[22]['rightname']      = 'software';
      $tab[22]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'software'");

      $tab[23]['table']          = 'glpi_profilerights';
      $tab[23]['field']          = 'rights';
      $tab[23]['name']           = _n('Network', 'Networks', 2);
      $tab[23]['datatype']       = 'right';
      $tab[23]['rightclass']     = 'Networking';
      $tab[23]['rightname']      = 'networking';
      $tab[23]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'networking'");

      $tab[24]['table']          = 'glpi_profilerights';
      $tab[24]['field']          = 'rights';
      $tab[24]['name']           = _n('Printer', 'Printers',2);
      $tab[24]['datatype']       = 'right';
      $tab[24]['rightclass']     = 'Printer';
      $tab[24]['rightname']      = 'printer';
      $tab[24]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'printer'");

      $tab[25]['table']          = 'glpi_profilerights';
      $tab[25]['field']          = 'rights';
      $tab[25]['name']           = _n('Device', 'Devices', 2);
      $tab[25]['datatype']       = 'right';
      $tab[25]['rightclass']     = 'Peripheral';
      $tab[25]['rightname']      = 'peripheral';
      $tab[25]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'peripheral'");

      $tab[26]['table']          = 'glpi_profilerights';
      $tab[26]['field']          = 'rights';
      $tab[26]['name']           = _n('Cartridge', 'Cartridges', 2);
      $tab[26]['datatype']       = 'right';
      $tab[26]['rightclass']     = 'Cartridge';
      $tab[26]['rightname']      = 'cartridge';
      $tab[26]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'cartridge'");

      $tab[27]['table']          = 'glpi_profilerights';
      $tab[27]['field']          = 'rights';
      $tab[27]['name']           = _n('Consumable', 'Consumables', 2);
      $tab[27]['datatype']       = 'right';
      $tab[27]['rightclass']     = 'Consumable';
      $tab[27]['rightname']      = 'consumable';
      $tab[27]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'consumable'");

      $tab[28]['table']          = 'glpi_profilerights';
      $tab[28]['field']          = 'rights';
      $tab[28]['name']           = _n('Phone', 'Phones', 2);
      $tab[28]['datatype']       = 'right';
      $tab[28]['rightclass']     = 'Phone';
      $tab[28]['rightname']      = 'phone';
      $tab[28]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'phone'");

      $tab[129]['table']         = 'glpi_profilerights';
      $tab[129]['field']         = 'rights';
      $tab[129]['name']          = __('Internet');
      $tab[129]['datatype']      = 'right';
      $tab[129]['rightclass']    = 'NetworkName';
      $tab[129]['rightname']     = 'internet';
      $tab[129]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'internet'");

      $tab['management']         = __('Management');

      $tab[30]['table']          = 'glpi_profilerights';
      $tab[30]['field']          = 'rights';
      $tab[30]['name']           = __('Contact')." / ".__('Supplier');
      $tab[30]['datatype']       = 'right';
      $tab[30]['rightclass']     = 'Contact';
      $tab[30]['rightname']      = 'contact_entreprise';
      $tab[30]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'contact_enterprise'");

      $tab[31]['table']          = 'glpi_profilerights';
      $tab[31]['field']          = 'rights';
      $tab[31]['name']           = _n('Document', 'Documents', 2);
      $tab[31]['datatype']       = 'right';
      $tab[31]['rightclass']     = 'Document';
      $tab[31]['rightname']      = 'document';
      $tab[31]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'document'");

      $tab[32]['table']          ='glpi_profilerights';
      $tab[32]['field']          = 'rights';
      $tab[32]['name']           = _n('Contract', 'Contracts', 2);
      $tab[32]['datatype']       = 'right';
      $tab[32]['rightclass']     = 'Contract';
      $tab[32]['rightname']      = 'contract';
      $tab[32]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'contract'");

      $tab[33]['table']          = 'glpi_profilerights';
      $tab[33]['field']          = 'rights';
      $tab[33]['name']           = __('Financial and administratives information');
      $tab[33]['datatype']       = 'right';
      $tab[33]['rightclass']     = 'Infocom';
      $tab[33]['rightname']      = 'infocom';
      $tab[33]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'infocom'");

      $tab[101]['table']         = 'glpi_profilerights';
      $tab[101]['field']         = 'rights';
      $tab[101]['name']          = __('Budget');
      $tab[101]['datatype']      = 'right';
      $tab[101]['rightclass']    = 'Budget';
      $tab[101]['rightname']     = 'budget';
      $tab[101]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'budget'");

      $tab['tools']              = __('Tools');

      $tab[34]['table']          = 'glpi_profilerights';
      $tab[34]['field']          = 'rights';
      $tab[34]['name']           = __('Knowledge base');
      $tab[34]['datatype']       = 'right';
      $tab[34]['rightclass']     = 'KnowbaseItem';
      $tab[34]['rightname']      = 'knowbase';
      $tab[34]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'knowbase'");

      $tab[36]['table']          = 'glpi_profilerights';
      $tab[36]['field']          = 'rights';
      $tab[36]['name']           = _n('Reservation', 'Reservations', 2);
      $tab[36]['datatype']       = 'right';
      $tab[36]['rightclass']     = 'ReservationItem';
      $tab[36]['rightname']      = 'reservation';
      $tab[36]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reservation'");

      $tab[38]['table']          = 'glpi_profilerights';
      $tab[38]['field']          = 'rights';
      $tab[38]['name']           = _n('Report', 'Reports', 2);
      $tab[38]['datatype']       = 'right';
      $tab[38]['rightclass']     = 'Report';
      $tab[38]['rightname']      = 'reports';
      $tab[38]['nowrite']        = true;
      $tab[38]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reports'");

      $tab['config']             = __('Setup');

      $tab[42]['table']          = 'glpi_profilerights';
      $tab[42]['field']          = 'rights';
      $tab[42]['name']           = _n('Dropdown', 'Dropdowns', 2);
      $tab[42]['datatype']       = 'right';
      $tab[42]['rightclass']     = 'DropdownTranslation';
      $tab[42]['rightname']      = 'dropdown';
      $tab[42]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'dropdown'");

      $tab[44]['table']          = 'glpi_profilerights';
      $tab[44]['field']          = 'rights';
      $tab[44]['name']           = _n('Component', 'Components', 2);
      $tab[44]['datatype']       = 'right';
      $tab[44]['rightclass']     = 'Item_Devices';
      $tab[44]['rightname']      = 'device';
      $tab[44]['noread']         = true;
      $tab[44]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'device'");

      $tab[106]['table']         = 'glpi_profilerights';
      $tab[106]['field']         = 'rights';
      $tab[106]['name']          = _n('Notification', 'Notifications',2);
      $tab[106]['datatype']      = 'right';
      $tab[106]['rightclass']    = 'Notification';
      $tab[106]['rightname']     = 'notification';
      $tab[106]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'notification'");

      $tab[45]['table']          = 'glpi_profilerights';
      $tab[45]['field']          = 'rights';
      $tab[45]['name']           = __('Document type');
      $tab[45]['datatype']       = 'right';
      $tab[45]['rightclass']     = 'DocumentType';
      $tab[45]['rightname']      = 'typedoc';
      $tab[45]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'typedoc'");

      $tab[46]['table']          = 'glpi_profilerights';
      $tab[46]['field']          = 'rights';
      $tab[46]['name']           = _n('External link', 'External links',2);
      $tab[46]['datatype']       = 'right';
      $tab[46]['rightclass']     = 'Link';
      $tab[46]['rightname']      = 'link';
      $tab[46]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'link'");

      $tab[47]['table']          = 'glpi_profilerights';
      $tab[47]['field']          = 'rights';
      $tab[47]['name']           = __('General setup');
      $tab[47]['datatype']       = 'right';
      $tab[47]['rightclass']     = 'Config';
      $tab[47]['rightname']      = 'config';
      $tab[47]['noread']         = true;
      $tab[47]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'config'");

      $tab[52]['table']          = 'glpi_profilerights';
      $tab[52]['field']          = 'rights';
      $tab[52]['name']           = __('Search result user display');
      $tab[52]['datatype']       = 'right';
      $tab[52]['rightclass']     = 'DisplayPreference';
      $tab[52]['rightname']      = 'search_config';
      $tab[52]['noread']         = true;
      $tab[52]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'search_config'");

      $tab[107]['table']         = 'glpi_profilerights';
      $tab[107]['field']         = 'rights';
      $tab[107]['name']          = _n('Calendar', 'Calendars', 2);
      $tab[107]['datatype']      = 'right';
      $tab[107]['rightclass']    = 'Calendar';
      $tab[107]['rightname']     = 'calendar';
      $tab[107]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'calendar'");

      $tab['admin']              = __('Administration');

      $tab[48]['table']          = 'glpi_profilerights';
      $tab[48]['field']          = 'rights';
      $tab[48]['name']           = __('Business rules for tickets');
      $tab[48]['datatype']       = 'right';
      $tab[48]['rightclass']     = 'RuleTicket';
      $tab[48]['rightname']      = 'rule_ticket';
      $tab[48]['nowrite']        = true;
      $tab[48]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_ticket'");

      $tab[105]['table']         = 'glpi_profilerights';
      $tab[105]['field']         = 'rights';
      $tab[105]['name']          = __('Rules for assigning a ticket created through a mails receiver');
      $tab[105]['datatype']      = 'right';
      $tab[105]['rightclass']    = 'RuleMailCollector';
      $tab[105]['rightname']     = 'rule_mailcollector';
      $tab[105]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_mailcollector'");

      $tab[49]['table']          = 'glpi_profilerights';
      $tab[49]['field']          = 'rights';
      $tab[49]['name']           = __('Rules for assigning a computer to an entity');
      $tab[49]['datatype']       = 'right';
      $tab[49]['rightclass']     = 'RuleImportComputer';
      $tab[49]['rightname']      = 'rule_import';
      $tab[49]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_import'");

      $tab[50]['table']          = 'glpi_profilerights';
      $tab[50]['field']          = 'rights';
      $tab[50]['name']           = __('Authorizations assignment rules');
      $tab[50]['datatype']       = 'right';
      $tab[50]['rightclass']     = 'Rule';
      $tab[50]['rightname']      = 'rule_ldap';
      $tab[50]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_ldap'");

      $tab[51]['table']          = 'glpi_profilerights';
      $tab[51]['field']          = 'rights';
      $tab[51]['name']           = __('Rules for assigning a category to a software');
      $tab[51]['datatype']       = 'right';
      $tab[51]['rightclass']     = 'RuleSoftwareCategory';
      $tab[51]['rightname']      = 'rule_softwarecategories';
      $tab[51]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_softwarecategories'");

      $tab[90]['table']          = 'glpi_profilerights';
      $tab[90]['field']          = 'rights';
      $tab[90]['name']           = __('Software dictionary');
      $tab[90]['datatype']       = 'right';
      $tab[90]['rightclass']     = 'RuleDictionnarySoftware';
      $tab[90]['rightname']      = 'rule_dictionnary_software';
      $tab[90]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_dictionnary_software'");

      $tab[91]['table']          = 'glpi_profilerights';
      $tab[91]['field']          = 'rights';
      $tab[91]['name']           =__('Dropdowns dictionary');
      $tab[91]['datatype']       = 'right';
      $tab[91]['rightclass']     = 'RuleDictionnaryDropdown';
      $tab[91]['rightname']      = 'rule_dictionnary_dropdown';
      $tab[91]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_dictionnary_dropdown'");

      $tab[55]['table']          = 'glpi_profilerights';
      $tab[55]['field']          = 'rights';
      $tab[55]['name']           = self::getTypeName(2);
      $tab[55]['datatype']       = 'right';
      $tab[55]['rightclass']     = 'Profile';
      $tab[55]['rightname']      = 'profile';
      $tab[55]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'profile'");

      $tab[56]['table']          = 'glpi_profilerights';
      $tab[56]['field']          = 'rights';
      $tab[56]['name']           = _n('User', 'Users', 2);
      $tab[56]['datatype']       = 'right';
      $tab[56]['rightclass']     = 'User';
      $tab[56]['rightname']      = 'user';
      $tab[56]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'user'");

      $tab[58]['table']          = 'glpi_profilerights';
      $tab[58]['field']          = 'rights';
      $tab[58]['name']           = _n('Group', 'Groups', 2);
      $tab[58]['datatype']       = 'right';
      $tab[58]['rightclass']     = 'Group';
      $tab[58]['rightname']      = 'group';
      $tab[58]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'group'");

      $tab[59]['table']          = 'glpi_profilerights';
      $tab[59]['field']          = 'rights';
      $tab[59]['name']           = _n('Entity', 'Entities', 2);
      $tab[59]['datatype']       = 'right';
      $tab[59]['rightclass']     = 'Entity';
      $tab[59]['rightname']      = 'entity';
      $tab[59]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'entity'");

      $tab[60]['table']          = 'glpi_profilerights';
      $tab[60]['field']          = 'rights';
      $tab[60]['name']           = __('Transfer');
      $tab[60]['datatype']       = 'right';
      $tab[60]['rightclass']     = 'Transfer';
      $tab[60]['rightname']      = 'transfer';
      $tab[60]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'transfer'");

      $tab[61]['table']          = 'glpi_profilerights';
      $tab[61]['field']          = 'rights';
      $tab[61]['name']           = _n('Log', 'Logs', 2);
      $tab[61]['datatype']       = 'right';
      $tab[61]['rightclass']     = 'Log';
      $tab[61]['rightname']      = 'logs';
      $tab[61]['nowrite']        = true;
      $tab[61]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'logs'");

      $tab[62]['table']          = 'glpi_profilerights';
      $tab[62]['field']          = 'rights';
      $tab[62]['name']           = __('Maintenance');
      $tab[62]['datatype']       = 'right';
      $tab[62]['rightclass']     = 'Backup';
      $tab[62]['rightname']      = 'backup';
      $tab[62]['noread']         = true;
      $tab[62]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'backup'");

      $tab['ticket']             = __('Assistance');

      $tab[102]['table']         = 'glpi_profilerights';
      $tab[102]['field']         = 'rights';
      $tab[102]['name']          = __('Create a ticket');
      $tab[102]['datatype']      = 'right';
      $tab[102]['rightclass']    = 'Ticket';
      $tab[102]['rightname']     = 'ticket';
      $tab[102]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'ticket'");

      $tab[108]['table']         = 'glpi_tickettemplates';
      $tab[108]['field']         = 'name';
      $tab[108]['name']          = __('Default ticket template');
      $tab[108]['datatype']      = 'dropdown';
      if (Session::isMultiEntitiesMode()) {
         $tab[108]['condition']     = '`entities_id` = 0 AND `is_recursive`';
      } else {
         $tab[108]['condition']     = '`entities_id` = 0';
      }

      $tab[103]['table']         = 'glpi_profilerights';
      $tab[103]['field']         = 'rights';
      $tab[103]['name']          = _n('Ticket template', 'Ticket templates', 2);
      $tab[103]['datatype']      = 'right';
      $tab[103]['rightclass']    = 'TicketTemplate';
      $tab[103]['rightname']     = 'tickettemplate';
      $tab[103]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'tickettemplate'");

      $tab[79]['table']          = 'glpi_profilerights';
      $tab[79]['field']          = 'rights';
      $tab[79]['name']           = __('Plannings');
      $tab[79]['datatype']       = 'right';
      $tab[79]['rightclass']     = 'Planning';
      $tab[79]['rightname']      = 'planning';
      $tab[79]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'planning'");

      $tab[85]['table']          = 'glpi_profilerights';
      $tab[85]['field']          = 'rights';
      $tab[85]['name']           = __('Statistics');
      $tab[85]['datatype']       = 'right';
      $tab[85]['rightclass']     = 'Stat';
      $tab[85]['rightname']      = 'statistic';
      $tab[85]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'statistic'");

      $tab[119]['table']         = 'glpi_profilerights';
      $tab[119]['field']         = 'rights';
      $tab[119]['name']          = _n('Ticket cost', 'Ticket costs', 2);
      $tab[119]['datatype']      = 'right';
      $tab[119]['rightclass']    = 'TicketCost';
      $tab[119]['rightname']     = 'ticketcost';
      $tab[119]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'ticketcost'");

      $tab[86]['table']          = $this->getTable();
      $tab[86]['field']          = 'helpdesk_hardware';
      $tab[86]['name']           = __('Link with items for the creation of tickets');
      $tab[86]['massiveaction']  = false;
      $tab[86]['datatype']       = 'specific';

      $tab[87]['table']          = $this->getTable();
      $tab[87]['field']          = 'helpdesk_item_type';
      $tab[87]['name']           = __('Associable items to a ticket');
      $tab[87]['massiveaction']  = false;
      $tab[87]['datatype']       = 'specific';

      $tab[89]['table']          = 'glpi_profilerights';
      $tab[89]['field']          = 'rights';
      $tab[89]['name']           = __('See hardware of my groups');
      $tab[89]['datatype']       = 'bool';
      $tab[89]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'show_group_hardware'");

      $tab[100]['table']         = $this->getTable();
      $tab[100]['field']         = 'ticket_status';
      $tab[100]['name']          = __('Life cycle of tickets');
      $tab[100]['nosearch']      = true;
      $tab[100]['datatype']      = 'text';
      $tab[100]['massiveaction'] = false;

      $tab[110]['table']         = $this->getTable();
      $tab[110]['field']         = 'problem_status';
      $tab[110]['name']          = __('Life cycle of problems');
      $tab[110]['nosearch']      = true;
      $tab[110]['datatype']      = 'text';
      $tab[110]['massiveaction'] = false;

      $tab[112]['table']         = 'glpi_profilerights';
      $tab[112]['field']         = 'right';
      $tab[112]['name']          = _n('Problem', 'Problems', 2);
      $tab[112]['datatype']      = 'right';
      $tab[112]['rightclass']    = 'Problem';
      $tab[112]['rightname']     = 'problem';
      $tab[112]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'problem'");


      $tab[111]['table']         = $this->getTable();
      $tab[111]['field']         = 'change_status';
      $tab[111]['name']          = __('Life cycle of changes');
      $tab[111]['nosearch']      = true;
      $tab[111]['datatype']      = 'text';
      $tab[111]['massiveaction'] = false;

      $tab[115]['table']         = 'glpi_profilerights';
      $tab[115]['field']         = 'right';
      $tab[115]['name']          =_n('Change', 'Changes', 2);
      $tab[115]['datatype']      = 'right';
      $tab[115]['rightclass']    = 'Change';
      $tab[115]['rightname']     = 'change';
      $tab[115]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'change'");


      $tab['other']              = __('Other');

      $tab[4]['table']           = 'glpi_profilerights';
      $tab[4]['field']           = 'right';
      $tab[4]['name']            = __('Update password');
      $tab[4]['datatype']        = 'bool';
      $tab[4]['joinparams']     = array('jointype' => 'child',
                                        'condition' => "AND `NEWTABLE`.`name`= 'password_update'");

      $tab[63]['table']          = 'glpi_profilerights';
      $tab[63]['field']          = 'rights';
      $tab[63]['name']           = _n('Public reminder', 'Public reminders', 2);
      $tab[63]['datatype']       = 'right';
      $tab[63]['rightclass']     = 'Reminder';
      $tab[63]['rightname']      = 'reminder_public';
      $tab[63]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reminder_public'");

      $tab[64]['table']          = 'glpi_profilerights';
      $tab[64]['field']          = 'rights';
      $tab[64]['name']           = _n('Public bookmark', 'Public bookmarks', 2);
      $tab[64]['datatype']       = 'right';
      $tab[64]['rightclass']     = 'Bookmark';
      $tab[64]['rightname']      = 'bookmark_public';
      $tab[64]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'bookmark_public'");

      $tab[120]['table']          = 'glpi_profilerights';
      $tab[120]['field']          = 'rights';
      $tab[120]['name']           = _n('Public RSS feed', 'Public RSS feeds', 2);
      $tab[120]['datatype']       = 'right';
      $tab[120]['rightclass']     = 'RSSFeed';
      $tab[120]['rightname']      = 'rssfeed_public';
      $tab[120]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rssfeed_public'");

      return $tab;
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
    **/
   static function getSpecificValueToDisplay($field, $values, array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'interface':
            return self::getInterfaceName($values[$field]);

         case 'helpdesk_hardware':
            return self::getHelpdeskHardwareTypeName($values[$field]);

         case "helpdesk_item_type":
            $types = explode(',', $values[$field]);
            $message = array();
            foreach ($types as $type) {
               if ($item = getItemForItemtype($type)) {
                  $message[] = $item->getTypeName();
               }
            }
            return implode(', ',$message);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name               (default '')
    * @param $values             (default '')
    * @param $options      array
   **/
   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;
      switch ($field) {
         case 'interface' :
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getInterfaces(), $options);

         case 'helpdesk_hardware' :
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getHelpdeskHardwareTypes(), $options);

         case "helpdesk_item_type":
            $options['values'] = explode(',', $values[$field]);
            $options['name']   = $name;
            return self::dropdownHelpdeskItemtypes($options);
            // Linear_HIT
            // TODO: check if it is working ! I didn't find where it is used ...
//            return self::getLinearRightChoice(self::getHelpdeskItemtypes(),
//                                              array('field'         => $name,
//                                                    'value'         => explode(',', $values[$field]),
//                                                    'check_all'     => true,
//                                                    'zero_on_empty' => false,
//                                                    'check_method'  =>
//                                                    function ($element, $field) {
//                                                       return in_array($element,$field);
//                                                    }));
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }


   /**
    * Make a select box for a None Read Write choice
    *
    * @param $name      select name
    * @param $value     preselected value.
    * @param $none      display none choice ? (default 1)
    * @param $read      display read choice ? (default 1)
    * @param $write     display write choice ? (default 1)
    *
    * @return nothing (print out an HTML select box)
    * \deprecated since version 0.84 use dropdownRight instead
   **/
   static function dropdownNoneReadWrite($name, $value, $none=1, $read=1, $write=1) {

      return self::dropdownRight($name, array('value'     => $value,
                                              'nonone'  => !$none,
                                              'noread'  => !$read,
                                              'nowrite' => !$write));
   }


   /**
    * Make a select box for rights
    *
    * @since version 0.85
    *
    * @param $values    array    of values to display
    * @param $name      integer  name of the dropdown
    * @param $current   integer  value in database (sum of rights)
   **/
   static function dropdownRights(array $values, $name, $current, $options=array()) {

      foreach ($values as $key => $value) {
         if (is_array($value)) {
            $values[$key] = $value['long'];
         }
      }

      $param['multiple']= true;
      $param['display'] = true;
      $param['size']    = count($values);
      $tabselect = array();
      foreach ($values as $k => $v) {
         if ($current & $k) {
            $tabselect[] = $k;
         }
      }
      $param['values'] =  $tabselect;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      // To allow dropdown with no value to be in prepareInputForUpdate
      // without this, you can't have an empty dropdown
      // done to avoid define NORIGHT value
      if ($param['multiple']) {
         echo "<input type='hidden' name='".$name."[]' value='0'>";
      }
      return Dropdown::showFromArray($name, $values, $param);
   }



   /**
    * Make a select box for a None Read Write choice
    *
    * @since version 0.84
    *
    * @param $name          select name
    * @param $options array of possible options:
    *       - value   : preselected value.
    *       - nonone  : hide none choice ? (default false)
    *       - noread  : hide read choice ? (default false)
    *       - nowrite : hide write choice ? (default false)
    *       - display : display or get string (default true)
    *       - rand    : specific rand (default is generated one)
    *
    * @return nothing (print out an HTML select box)
   **/
   static function dropdownRight($name, $options=array()) {

      $param['value']   = '';
      $param['display'] = true;
      $param['nonone']  = false;
      $param['noread']  = false;
      $param['nowrite'] = false;
      $param['rand']    = mt_rand();

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      $values = array();
      if (!$param['nonone']) {
         $values[0] = __('No access');
      }
      if (!$param['noread']) {
         $values[READ] = __('Read');
      }
      if (!$param['nowrite']) {
         $values[CREATE] = __('Write');
      }
      return Dropdown::showFromArray($name, $values,
                                     array('value'   => $param['value'],
                                           'rand'    => $param['rand'],
                                           'display' => $param['display']));
   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - value : integer / preselected value (default 0)
    *
   **/
   static function dropdownUnder($options=array()) {
      global $DB;

      $p['name']  = 'profiles_id';
      $p['value'] = '';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $profiles[0] = Dropdown::EMPTY_VALUE;

      $query = "SELECT *
                FROM `glpi_profiles` ".
                self::getUnderActiveProfileRestrictRequest("WHERE")."
                ORDER BY `name`";
      $res = $DB->query($query);

      //New rule -> get the next free ranking
      if ($DB->numrows($res)) {
         while ($data = $DB->fetch_assoc($res)) {
            $profiles[$data['id']] = $data['name'];
         }
      }
      Dropdown::showFromArray($p['name'], $profiles, array('value' => $p['value']));
   }


   /**
    * Get the default Profile for new user
    *
    * @return integer profiles_id
   **/
   static function getDefault() {
      global $DB;

      foreach ($DB->request('glpi_profiles', array('is_default'=>1)) as $data) {
         return $data['id'];
      }
      return 0;
   }


   /**
    * @since version 0.84
   **/
   static function getInterfaces() {

     return array('central'  => __('Standard interface'),
                  'helpdesk' => __('Simplified interface'));
   }


   /**
    * @param $value
   **/
   static function getInterfaceName($value) {

      $tab = self::getInterfaces();
      if (isset($tab[$value])) {
         return $tab[$value];
      }
      return NOT_AVAILABLE;
   }


   /**
    * @since version 0.84
    *
    * @param $rights   boolean   (false by default)
   **/
   static function getHelpdeskHardwareTypes($rights=false) {

      if ($rights) {
         return array(pow(2, Ticket::HELPDESK_MY_HARDWARE)     => __('My devices'),
                      pow(2, Ticket::HELPDESK_ALL_HARDWARE)    => __('All items'));
      }

      return array(0                                        => Dropdown::EMPTY_VALUE,
                   pow(2, Ticket::HELPDESK_MY_HARDWARE)     => __('My devices'),
                   pow(2, Ticket::HELPDESK_ALL_HARDWARE)    => __('All items'),
                   pow(2, Ticket::HELPDESK_MY_HARDWARE)
                    + pow(2, Ticket::HELPDESK_ALL_HARDWARE) => __('My devices and all items'));
   }


   /**
    * @since version 0.84
    *
    * @param $value
   **/
   static function getHelpdeskHardwareTypeName($value) {

      $tab = self::getHelpdeskHardwareTypes();
      if (isset($tab[$value])) {
         return $tab[$value];
      }
      return NOT_AVAILABLE;
   }


   /**
    * @since version 0.85
   **/
   static function getHelpdeskItemtypes() {
      global $CFG_GLPI;

      $values = array();
      foreach ($CFG_GLPI["ticket_types"] as $key => $itemtype) {
         if ($item = getItemForItemtype($itemtype)) {
            if (!isPluginItemType($itemtype)) { // No Plugin for the moment
               $values[$itemtype] = $item->getTypeName();
            }
         } else {
            unset($CFG_GLPI["ticket_types"][$key]);
         }
      }
      return $values;
   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @since version 0.84
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - values : array of values
   **/
   static function dropdownHelpdeskItemtypes($options) {
      global $CFG_GLPI;

      $p['name']    = 'helpdesk_item_type';
      $p['values']  = array();
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $values = self::getHelpdeskItemtypes();

      $p['multiple'] = true;
      $p['size']     = 3;
      return Dropdown::showFromArray($p['name'], $values, $p);
   }


   /**
    * function to check one right of a user
    *
    * @since version 0.84
    *
    * @param $user       integer                id of the user to check rights
    * @param $right      string                 right to check
    * @param $valright   integer/string/array   value of the rights searched
    * @param $entity     integer                id of the entity
    *
    * @return boolean
    */
   static function haveUserRight($user, $right, $valright, $entity) {
      global $DB;

      $query = "SELECT $right
                FROM `glpi_profiles`
                INNER JOIN `glpi_profiles_users`
                   ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`)
                WHERE `glpi_profiles_users`.`users_id` = '$user'
                      AND $right IN ('$valright') ".
                      getEntitiesRestrictRequest(" AND ", "glpi_profiles_users", '', $entity, true);

      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            return true;
         }
      }
      return false;
   }


   /**
    * Get rights for an itemtype
    *
    * @since version 0.85
    *
    * @param $itemtype   string   itemtype
    * @param $interface  string   (default 'central')
    *
    * @return rights
   **/
   static function getRightsFor($itemtype, $interface='central') {

      if (class_exists($itemtype)) {
         $item = new $itemtype();
         return $item->getRights($interface);
      }
      return false;
   }


   /**
    * Display rights choice matrix
    *
    * @since version 0.85
    *
    * @param $rights array    possible:
    *             'itemtype'   => the type of the item to check (as passed to self::getRightsFor())
    *             'rights'     => when use of self::getRightsFor() is impossible
    *             'label'      => the label for the right
    *             'field'      => the name of the field inside the DB and HTML form (prefixed by '_')
    *             'html_field' => when $html_field != '_'.$field
    * @param $options array   possible:
    *             'title'         the title of the matrix
    *             'canedit'
    *             'default_class' the default CSS class used for the row
    *
    * @return random value used to generate the ids
   **/
   function displayRightsChoiceMatrix(array $rights, array $options=array()) {

      $param                  = array();
      $param['title']         = '';
      $param['canedit']       = true;
      $param['default_class'] = '';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      $column_labels = array();
      $columns       = array();
      $rows          = array();

      foreach ($rights as $info) {

         if (is_string($info)) {
            $rows[] = $info;
            continue;
         }
         if (is_array($info)
             && ((!empty($info['itemtype'])) || (!empty($info['rights'])))
             && (!empty($info['label']))
             && (!empty($info['field']))) {

            $row = array('label'   => $info['label'],
                         'columns' => array());
            if (!empty($info['row_class'])) {
               $row['class'] = $info['row_class'];
            } else {
               $row['class'] = $param['default_class'];
            }
            $profile_right = $this->fields[$info['field']];
            if (isset($info['rights'])) {
               $rights = $info['rights'];
            } else {
               $rights = self::getRightsFor($info['itemtype']);
            }

            foreach ($rights as $right => $label) {
               if (!isset($column_labels[$right])) {
                  $column_labels[$right] = array();
               }
               if (is_array($label)) {
                  $long_label = $label['long'];
               } else {
                  $long_label = $label;
               }
               if (!isset($column_labels[$right][$long_label])) {
                  $column_labels[$right][$long_label] = count($column_labels[$right]);
               }
               $right_value                  = $right.'_'.$column_labels[$right][$long_label];

               $columns[$right_value]        = $label;

               $checked                      = ((($profile_right & $right) == $right) ? 1 : 0);
               $row['columns'][$right_value] = array('checked' => $checked);
               if (!$param['canedit']) {
                  $row['columns'][$right_value]['readonly'] = true;
               }
            }
            if (!empty($info['html_field'])) {
               $rows[$info['html_field']] = $row;
            } else {
               $rows['_'.$info['field']] = $row;
            }
         }
      }

      uksort($columns, function ($a, $b) {
                           $a = explode('_', $a);
                           $b = explode('_', $b);

                           // For standard rights sort by right
                           if (($a[0] < 1024)
                               || ($b[0] < 1024)) {
                              if ($a[0] > $b[0]) {
                                 return true;
                              }
                              if ($a[0] < $b[0]) {
                                 return false;
                              }
                              return ($a[1] > $b[1]);
                              // For extra right sort by type
                           }
                           return ($a[1] > $b[1]);
                        });

      return Html::showCheckboxMatrix($columns, $rows,
                                      array('title'                => $param['title'],
                                            'row_check_all'        => count($columns) > 1,
                                            'col_check_all'        => count($rows) > 1,
                                            'rotate_column_titles' => false));
   }


   /**
    * Get right linear right choice.
    *
    * @since version 0.85
    *
    * @param $elements  array   all pair identifier => label
    * @param $options   array   possible:
    *             'canedit'
    *             'field'         name of the HTML field
    *             'value'         the value inside the database
    *             'max_per_line'  maximum number of elements per line
    *             'check_all'     add a checkbox to check or uncheck every checkbox
    *             'rand'          random value used to generate the ids
    *             'zero_on_empty' do we send 0 when checkbox is not checked ?
    *             'display'
    *             'check_method'  method used to check the right
    *
    * @return content if !display
   **/
   static function getLinearRightChoice(array $elements, array $options = array()) {

      $param                  = array();
      $param['canedit']       = true;
      $param['field']         = '';
      $param['value']         = '';
      $param['max_per_line']  = 10;
      $param['check_all']     = false;
      $param['rand']          = mt_rand();
      $param['zero_on_empty'] = true;
      $param['display']       = true;
      $param['check_method']  = function ($element, $field) {
         return (($field & $element) == $element);
      };

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      if (empty($param['field'])) {
         return;
      }

      $nb_cbs      = count($elements);
      $cb_options  = array('readonly' => !$param['canedit']);
      if ($param['check_all']) {
         $nb_cbs ++;
         $massive_tag                = 'checkall_'.$param['field'].'_'.$param['rand'];
         $cb_options['massive_tags'] = $massive_tag;
      }

      $nb_lines         = ceil($nb_cbs / $param['max_per_line']);
      $nb_item_per_line = ceil($nb_cbs / $nb_lines);

      $out              = '';

      $count            = 0;
      $nb_checked       = 0;
      foreach ($elements as $element => $label) {
         if ($count != 0) {
            if (($count % $nb_item_per_line) == 0) {
               $out .= "<br>\n";
            } else {
               $out .= "&nbsp;-\n\t\t&nbsp;";
            }
         } else {
            $out .= "\n\t\t";
         }
         $out                        .= $label.'&nbsp;';
         $cb_options['name']          = $param['field'].'['.$element.']';
         $cb_options['id']            = Html::cleanId('checkbox_linear_'.$cb_options['name'].
                                                      '_'.$param['rand']);
         $cb_options['zero_on_empty'] = $param['zero_on_empty'];

         $cb_options['checked']       = $param['check_method']($element,
                                                               $param['value']);

         $out                        .= Html::getCheckbox($cb_options);
         $count ++;
         if ($cb_options['checked']) {
            $nb_checked ++;
         }
      }

      if ($param['check_all']) {
         $cb_options = array('criterion' => array('tag_for_massive' => $massive_tag),
                             'id'        => Html::cleanId('checkbox_linear_'.$param['rand']));
         if ($nb_checked > (count($elements) / 2)) {
            $cb_options['checked'] = true;
         }
         $out .= "&nbsp;-&nbsp;<i><b>".__('Select/unselect all')."</b></i>&nbsp;".
                  Html::getCheckbox($cb_options);
      }

      if (!$param['display']) {
         return $out;
      }

      echo $out;
   }

}
?>
