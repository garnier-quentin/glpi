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

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 *  Contract class
 */
class Contract extends CommonDBTM {

   // From CommonDBTM
   public $dohistory = true;
   protected $forward_entity_to = array('ContractCost');

   static function getTypeName($nb=0) {
      return _n('Contract', 'Contracts', $nb);
   }


   function canCreate() {
      return Session::haveRight('contract', 'w');
   }


   function canView() {
      return Session::haveRight('contract', 'r');
   }


   function post_getEmpty() {

      $this->fields["alert"] = Entity::getUsedConfig("use_contracts_alert",
                                                     $this->fields["entities_id"],
                                                     "default_contract_alert", 0);
   }


   function cleanDBonPurge() {

      $cs = new Contract_Supplier();
      $cs->cleanDBonItemDelete($this->getType(), $this->fields['id']);

      $cs = new ContractCost();
      $cs->cleanDBonItemDelete($this->getType(), $this->fields['id']);
      
      $ci = new Contract_Item();
      $ci->cleanDBonItemDelete($this->getType(), $this->fields['id']);
   }


   function defineTabs($options=array()) {

      $ong = array();
      $this->addStandardTab('ContractCost', $ong, $options);
      $this->addStandardTab('Contract_Supplier', $ong, $options);
      $this->addStandardTab('Contract_Item', $ong, $options);
      $this->addStandardTab('Document', $ong, $options);
      $this->addStandardTab('Link', $ong, $options);
      $this->addStandardTab('Note', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   function prepareInputForAdd($input) {

      if (isset($input["id"]) && $input["id"]>0) {
         $input["_oldID"] = $input["id"];
      }
      unset($input['id']);
      unset($input['withtemplate']);

      return $input;
   }
   function post_addItem() {
      global $DB;

      // Manage add from template
      if (isset($this->input["_oldID"])) {
         // ADD Devices
         ContractCost::cloneContract($this->input["_oldID"], $this->fields['id']);

      }
   }
   
   function pre_updateInDB() {

      // Clean end alert if begin_date is after old one
      // Or if duration is greater than old one
      if ((isset($this->oldvalues['begin_date'])
           && ($this->oldvalues['begin_date'] < $this->fields['begin_date']))
          || (isset($this->oldvalues['duration'])
              && ($this->oldvalues['duration'] < $this->fields['duration']))) {

         $alert = new Alert();
         $alert->clear($this->getType(), $this->fields['id'], Alert::END);
      }

      // Clean notice alert if begin_date is after old one
      // Or if duration is greater than old one
      // Or if notice is lesser than old one
      if ((isset($this->oldvalues['begin_date'])
           && ($this->oldvalues['begin_date'] < $this->fields['begin_date']))
          || (isset($this->oldvalues['duration'])
              && ($this->oldvalues['duration'] < $this->fields['duration']))
          || (isset($this->oldvalues['notice'])
              && ($this->oldvalues['notice'] > $this->fields['notice']))) {

         $alert = new Alert();
         $alert->clear($this->getType(), $this->fields['id'], Alert::NOTICE);
      }
   }


   /**
    * Print the contract form
    *
    * @param $ID        integer ID of the item
    * @param $options   array
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    *@return boolean item found
   **/
   function showForm($ID,$options=array()) {

      $this->initForm($ID, $options);

      $can_edit = $this->can($ID,'w');

      $this->showTabs($options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Name')."</td><td>";
      Html::autocompletionTextField($this, "name");
      echo "</td>";
      echo "<td>".__('Contract type')."</td><td >";
      ContractType::dropdown(array('value' => $this->fields["contracttypes_id"]));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>"._x('Phone', 'Number')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "num");
      echo "</td>";
      echo "<td colspan='2'></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Start date')."</td>";
      echo "<td>";
      Html::showDateFormItem("begin_date", $this->fields["begin_date"]);
      echo "</td>";
      echo "<td>".__('Initial contract period')."</td><td>";
      Dropdown::showInteger("duration", $this->fields["duration"], 1, 120, 1,
                            array(0 => Dropdown::EMPTY_VALUE), array('unit' => 'month'));
      if (!empty($this->fields["begin_date"])) {
         echo " -> ".Infocom::getWarrantyExpir($this->fields["begin_date"],
                                               $this->fields["duration"]);
      }
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Notice')."</td><td>";
      Dropdown::showInteger("notice", $this->fields["notice"], 0, 120, 1,
                            array(), array('unit' => 'month'));
      if (!empty($this->fields["begin_date"])
          && ($this->fields["notice"] > 0)) {
         echo " -> ".Infocom::getWarrantyExpir($this->fields["begin_date"],
                                               $this->fields["duration"], $this->fields["notice"]);
      }
      echo "</td>";
      echo "<td>".__('Account number')."</td><td>";
      Html::autocompletionTextField($this, "accounting_number");
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Contract renewal period')."</td><td>";
      Dropdown::showInteger("periodicity", $this->fields["periodicity"], 12, 60, 12,
                            array(0 => Dropdown::EMPTY_VALUE,
                                  1 => sprintf(_n('%d month', '%d months', 1), 1),
                                  2 => sprintf(_n('%d month', '%d months', 2), 2),
                                  3 => sprintf(_n('%d month', '%d months', 3), 3),
                                  6 => sprintf(_n('%d month', '%d months', 6), 6)),
                            array('unit' => 'month'));
      echo "</td>";
      echo "<td>".__('Invoice period')."</td>";
      echo "<td>";
      Dropdown::showInteger("billing", $this->fields["billing"], 12, 60, 12,
                            array(0 => Dropdown::EMPTY_VALUE,
                                  1 => sprintf(_n('%d month', '%d months', 1), 1),
                                  2 => sprintf(_n('%d month', '%d months', 2), 2),
                                  3 => sprintf(_n('%d month', '%d months', 3), 3),
                                  6 => sprintf(_n('%d month', '%d months', 6), 6)),
                            array('unit' => 'month'));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'><td>".__('Renewal')."</td><td>";
      self::dropdownContractRenewal("renewal", $this->fields["renewal"]);
      echo "</td>";
      echo "<td>".__('Max number of items')."</td><td>";
      Dropdown::showInteger("max_links_allowed", $this->fields["max_links_allowed"], 1, 200, 1,
                            array(0 => __('Unlimited')));
      echo "</td>";
      echo "</tr>";


      if (Entity::getUsedConfig("use_contracts_alert", $this->fields["entities_id"])) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Email alarms')."</td>";
         echo "<td>";

         self::dropdownAlert(array('name'  => "alert",
                                   'value' => $this->fields["alert"]));
         Alert::displayLastAlert(__CLASS__, $ID);
         echo "</td>";
         echo "<td colspan='2'>&nbsp;</td>";
         echo "</tr>";
      }
      echo "<tr class='tab_bg_1'><td class='top'>".__('Comments')."</td>";
      echo "<td class='center' colspan='3'>";
      echo "<textarea cols='50' rows='4' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'><td>".__('Support hours')."</td>";
      echo "<td colspan='3'>&nbsp;</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('on week')."</td>";
      echo "<td colspan='3'>". __('Start')."&nbsp;";
      Dropdown::showHours("week_begin_hour", $this->fields["week_begin_hour"]);
      echo "<span class='small_space'>".__('End')."</span>&nbsp;";
      Dropdown::showHours("week_end_hour", $this->fields["week_end_hour"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('on Saturday')."</td>";
      echo "<td colspan='3'>";
      Dropdown::showYesNo("use_saturday", $this->fields["use_saturday"]);
      echo "<span class='small_space'>".__('Start')."</span>&nbsp;";
      Dropdown::showHours("saturday_begin_hour", $this->fields["saturday_begin_hour"]);
      echo "<span class='small_space'>".__('End')."</span>&nbsp;";
      Dropdown::showHours("saturday_end_hour", $this->fields["saturday_end_hour"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Sundays and holidays')."</td>";
      echo "<td colspan='3'>";
      Dropdown::showYesNo("use_monday", $this->fields["use_monday"]);
      echo "<span class='small_space'>".__('Start')."</span>&nbsp;";
      Dropdown::showHours("monday_begin_hour", $this->fields["monday_begin_hour"]);
      echo "<span class='small_space'>".__('End')."</span>&nbsp;";
      Dropdown::showHours("monday_end_hour", $this->fields["monday_end_hour"]);
      echo "</td></tr>";

      $this->showFormButtons($options);
      $this->addDivForTabs();

      return true;
   }


   static function getSearchOptionsToAdd() {

      $tab                       = array();
      $tab['contract']           = self::getTypeName(2);

      $joinparams                = array('beforejoin'
                                          => array('table'      => 'glpi_contracts_items',
                                                   'joinparams' => array('jointype'
                                                                            => 'itemtype_item')));

      $tab[139]['table']         = 'glpi_contracts_items';
      $tab[139]['field']         = 'count';
      $tab[139]['name']          = __('Number of contracts');
      $tab[139]['forcegroupby']  = true;
      $tab[139]['usehaving']     = true;
      $tab[139]['datatype']      = 'number';
      $tab[139]['massiveaction'] = false;
      $tab[139]['joinparams']    = array('jointype' => 'itemtype_item');

      $tab[29]['table']          = 'glpi_contracts';
      $tab[29]['field']          = 'name';
      $tab[29]['name']           = self::getTypeName(1);
      $tab[29]['forcegroupby']   = true;
      $tab[29]['datatype']       = 'itemlink';
      $tab[29]['itemlink_type']  = 'Contract';
      $tab[29]['massiveaction']  = false;
      $tab[29]['joinparams']     = $joinparams;

      $tab[30]['table']          = 'glpi_contracts';
      $tab[30]['field']          = 'num';
      $tab[30]['name']           = __('Contract number');
      $tab[30]['forcegroupby']   = true;
      $tab[30]['massiveaction']  = false;
      $tab[30]['joinparams']     = $joinparams;
      $tab[30]['datatype']       = 'string';

      $tab[130]['table']         = 'glpi_contracts';
      $tab[130]['field']         = 'duration';
      $tab[130]['name']          = sprintf(__('%1$s %2$s'), __('Contract'),__('Duration'));
      $tab[130]['forcegroupby']  = true;
      $tab[130]['massiveaction'] = false;
      $tab[130]['joinparams']    = $joinparams;

      $tab[131]['table']         = 'glpi_contracts';
      $tab[131]['field']         = 'periodicity';
                                    //TRANS: %1$s is Contract, %2$s is field name
      $tab[131]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Periodicity'));
      $tab[131]['forcegroupby']  = true;
      $tab[131]['massiveaction'] = false;
      $tab[131]['joinparams']    = $joinparams;

      $tab[132]['table']         = 'glpi_contracts';
      $tab[132]['field']         = 'begin_date';
      $tab[132]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Start date'));
      $tab[132]['forcegroupby']  = true;
      $tab[132]['datatype']      = 'date';
      $tab[132]['massiveaction'] = false;
      $tab[132]['joinparams']    = $joinparams;

      $tab[133]['table']         = 'glpi_contracts';
      $tab[133]['field']         = 'accounting_number';
      $tab[133]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Account number'));
      $tab[133]['forcegroupby']  = true;
      $tab[133]['massiveaction'] = false;
      $tab[133]['joinparams']    = $joinparams;

      $tab[134]['table']         = 'glpi_contracts';
      $tab[134]['field']         = 'end_date';
      $tab[134]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('End date'));
      $tab[134]['forcegroupby']  = true;
      $tab[134]['datatype']      = 'date_delay';
      $tab[134]['datafields'][1] = 'begin_date';
      $tab[134]['datafields'][2] = 'duration';
      $tab[134]['searchunit']    = 'MONTH';
      $tab[134]['delayunit']     = 'MONTH';
      $tab[134]['massiveaction'] = false;
      $tab[134]['joinparams']    = $joinparams;

      $tab[135]['table']         = 'glpi_contracts';
      $tab[135]['field']         = 'notice';
      $tab[135]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Notice'));
      $tab[135]['forcegroupby']  = true;
      $tab[135]['massiveaction'] = false;
      $tab[135]['joinparams']    = $joinparams;

//       $tab[136]['table']         = 'glpi_contracts';
//       $tab[136]['field']         = 'cost';
//       $tab[136]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Cost'));
//       $tab[136]['forcegroupby']  = true;
//       $tab[136]['datatype']      = 'decimal';
//       $tab[136]['massiveaction'] = false;
//       $tab[136]['joinparams']    = $joinparams;

      $tab[137]['table']         = 'glpi_contracts';
      $tab[137]['field']         = 'billing';
      $tab[137]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Invoice period'));
      $tab[137]['forcegroupby']  = true;
      $tab[137]['massiveaction'] = false;
      $tab[137]['joinparams']    = $joinparams;

      $tab[138]['table']         = 'glpi_contracts';
      $tab[138]['field']         = 'renewal';
      $tab[138]['name']          = sprintf(__('%1$s %2$s'), __('Contract'), __('Renewal'));
      $tab[138]['forcegroupby']  = true;
      $tab[138]['massiveaction'] = false;
      $tab[138]['joinparams']    = $joinparams;

      return $tab;
   }


   function getSearchOptions() {

      $tab                       = array();
      $tab['common']             = __('Characteristics');

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();
      $tab[1]['massiveaction']   = false;

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'id';
      $tab[2]['name']            = __('ID');
      $tab[2]['massiveaction']   = false;

      $tab[3]['table']           = $this->getTable();
      $tab[3]['field']           = 'num';
      $tab[3]['name']            = _x('Phone', 'Number');
      $tab[3]['datatype']        = 'string';

      $tab[4]['table']           = 'glpi_contracttypes';
      $tab[4]['field']           = 'name';
      $tab[4]['name']            = __('Type');

      $tab[5]['table']           = $this->getTable();
      $tab[5]['field']           = 'begin_date';
      $tab[5]['name']            = __('Start date');
      $tab[5]['datatype']        = 'date';
      $tab[5]['maybefuture']     = true;

      $tab[6]['table']           = $this->getTable();
      $tab[6]['field']           = 'duration';
      $tab[6]['name']            = __('Duration');

      $tab[20]['table']          = $this->getTable();
      $tab[20]['field']          = 'end_date';
      $tab[20]['name']           = __('End date');
      $tab[20]['datatype']       = 'date_delay';
      $tab[20]['datafields'][1]  = 'begin_date';
      $tab[20]['datafields'][2]  = 'duration';
      $tab[20]['searchunit']     = 'MONTH';
      $tab[20]['delayunit']      = 'MONTH';
      $tab[20]['maybefuture']    = true;
      $tab[20]['massiveaction']  = false;

      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'notice';
      $tab[7]['name']            = __('Notice');

      /// TODO create cost section with others data
      $tab[11]['table']          = 'glpi_contractcosts';
      $tab[11]['field']          = 'cost';
      $tab[11]['name']           = __('Total cost');
      $tab[11]['datatype']       = 'decimal';
      $tab[11]['forcegroupby']   = true;
      $tab[11]['massiveaction']  = false;
      $tab[11]['joinparams']     = array('jointype' => 'child');

      $tab[21]['table']          = $this->getTable();
      $tab[21]['field']          = 'periodicity';
      $tab[21]['name']           = __('Periodicity');
      $tab[21]['massiveaction']  = false;

      $tab[22]['table']          = $this->getTable();
      $tab[22]['field']          = 'billing';
      $tab[22]['name']           = __('Invoice period');
      $tab[22]['massiveaction']  = false;

      $tab[10]['table']          = $this->getTable();
      $tab[10]['field']          = 'accounting_number';
      $tab[10]['name']           = __('Account number');
      $tab[10]['datatype']       = 'string';

      $tab[23]['table']          = $this->getTable();
      $tab[23]['field']          = 'renewal';
      $tab[23]['name']           = __('Renewal');
      $tab[23]['massiveaction']  = false;

      $tab[12]['table']          = $this->getTable();
      $tab[12]['field']          = 'expire';
      $tab[12]['name']           = __('Expiration');
      $tab[12]['datatype']       = 'date_delay';
      $tab[12]['datafields'][1]  = 'begin_date';
      $tab[12]['datafields'][2]  = 'duration';
      $tab[12]['searchunit']     = 'DAY';
      $tab[12]['delayunit']      = 'MONTH';
      $tab[12]['maybefuture']    = true;
      $tab[12]['massiveaction']  = false;

      $tab[13]['table']          = $this->getTable();
      $tab[13]['field']          = 'expire_notice';
      $tab[13]['name']           = __('Expiration date + notice');
      $tab[13]['datatype']       = 'date_delay';
      $tab[13]['datafields'][1]  = 'begin_date';
      $tab[13]['datafields'][2]  = 'duration';
      $tab[13]['datafields'][3]  = 'notice';
      $tab[13]['searchunit']     = 'DAY';
      $tab[13]['delayunit']      = 'MONTH';
      $tab[13]['maybefuture']    = true;
      $tab[13]['massiveaction']  = false;

      $tab[16]['table']          = $this->getTable();
      $tab[16]['field']          = 'comment';
      $tab[16]['name']           = __('Comments');
      $tab[16]['datatype']       = 'text';

      $tab[90]['table']          = $this->getTable();
      $tab[90]['field']          = 'notepad';
      $tab[90]['name']           = __('Notes');
      $tab[90]['massiveaction']  = false;

      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['massiveaction']  = false;

      $tab[59]['table']          = $this->getTable();
      $tab[59]['field']          = 'alert';
      $tab[59]['name']           = __('Email alarms');

      $tab[86]['table']          = $this->getTable();
      $tab[86]['field']          = 'is_recursive';
      $tab[86]['name']           = __('Child entities');
      $tab[86]['datatype']       = 'bool';

      $tab[72]['table']          = 'glpi_contracts_items';
      $tab[72]['field']          = 'count';
      $tab[72]['name']           = _x('Quantity', 'Number of items');
      $tab[72]['forcegroupby']   = true;
      $tab[72]['usehaving']      = true;
      $tab[72]['datatype']       = 'number';
      $tab[72]['massiveaction']  = false;
      $tab[72]['joinparams']     = array('jointype' => 'child');

      $tab[29]['table']         = 'glpi_suppliers';
      $tab[29]['field']         = 'name';
      $tab[29]['name']          = _n('Associated supplier', 'Associated suppliers', 2);
      $tab[29]['forcegroupby']  = true;
      $tab[29]['datatype']      = 'itemlink';
      $tab[29]['itemlink_type'] = 'Supplier';
      $tab[29]['massiveaction'] = false;
      $tab[29]['joinparams']    = array('beforejoin'
                                       => array('table'      => 'glpi_contracts_suppliers',
                                                'joinparams' => array('jointype' => 'child')));
      return $tab;
   }


   /**
    * Show central contract resume
    * HTML array
    *
    * @return Nothing (display)
    **/
   static function showCentral() {
      global $DB,$CFG_GLPI;

      if (!Session::haveRight("contract", "r")) {
         return false;
      }

      // No recursive contract, not in local management
      // contrats echus depuis moins de 30j
      $query = "SELECT COUNT(*)
                FROM `glpi_contracts`
                WHERE `glpi_contracts`.`is_deleted`='0' ".
                      getEntitiesRestrictRequest("AND","glpi_contracts")."
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )>-30
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )<'0'";
      $result    = $DB->query($query);
      $contract0 = $DB->result($result,0,0);

      // contrats  echeance j-7
      $query = "SELECT COUNT(*)
                FROM `glpi_contracts`
                WHERE `glpi_contracts`.`is_deleted`='0' ".
                      getEntitiesRestrictRequest("AND","glpi_contracts")."
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )>'0'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )<='7'";
      $result    = $DB->query($query);
      $contract7 = $DB->result($result, 0, 0);

      // contrats echeance j -30
      $query = "SELECT COUNT(*)
                FROM `glpi_contracts`
                WHERE `glpi_contracts`.`is_deleted`='0' ".
                      getEntitiesRestrictRequest("AND","glpi_contracts")."
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )>'7'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           `glpi_contracts`.`duration` MONTH),CURDATE() )<'30'";
      $result     = $DB->query($query);
      $contract30 = $DB->result($result,0,0);

      // contrats avec préavis echeance j-7
      $query = "SELECT COUNT(*)
                FROM `glpi_contracts`
                WHERE `glpi_contracts`.`is_deleted`='0' ".
                      getEntitiesRestrictRequest("AND","glpi_contracts")."
                      AND `glpi_contracts`.`notice`<>'0'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           (`glpi_contracts`.`duration`-`glpi_contracts`.`notice`)
                                           MONTH),CURDATE() )>'0'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           (`glpi_contracts`.`duration`-`glpi_contracts`.`notice`)
                                           MONTH),CURDATE() )<='7'";
      $result       = $DB->query($query);
      $contractpre7 = $DB->result($result,0,0);

      // contrats avec préavis echeance j -30
      $query = "SELECT COUNT(*)
                FROM `glpi_contracts`
                WHERE `glpi_contracts`.`is_deleted`='0'".
                      getEntitiesRestrictRequest("AND","glpi_contracts")."
                      AND `glpi_contracts`.`notice`<>'0'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           (`glpi_contracts`.`duration`-`glpi_contracts`.`notice`)
                                           MONTH),CURDATE() )>'7'
                      AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`, INTERVAL
                                           (`glpi_contracts`.`duration`-`glpi_contracts`.`notice`)
                                           MONTH),CURDATE() )<'30'";
      $result        = $DB->query($query);
      $contractpre30 = $DB->result($result,0,0);

      echo "<table class='tab_cadrehov'>";
      echo "<tr><th colspan='2'>";
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset\">".
             self::getTypeName(1)."</a></th></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td><a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset&amp;".
                 "glpisearchcount=2&amp;sort=12&amp;order=DESC&amp;start=0&amp;field[0]=12&amp;".
                 "field[1]=12&amp;link[1]=AND&amp;contains[0]=%3C0&amp;contains[1]=%3E-30".
                  "&amp;searchtype[0]=contains&amp;searchtype[1]=contains\">".
                 __('Contracts expired in the last 30 days')."</a> </td>";
      echo "<td class='numeric'>".$contract0."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td><a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset&amp;".
                 "glpisearchcount=2&amp;contains%5B0%5D=%3E0&amp;field%5B0%5D=12&amp;link%5B1%5D=AND&amp;".
                 "contains%5B1%5D=%3C7&amp;field%5B1%5D=12&amp;sort=12&amp;is_deleted=0&amp;start=0".
                  "&amp;searchtype[0]=contains&amp;searchtype[1]=contains\">".
                 __('Contracts expiring in less than 7 days')."</a></td>";
      echo "<td class='numeric'>".$contract7."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td><a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset&amp;".
                 "glpisearchcount=2&amp;contains%5B0%5D=%3E6&amp;field%5B0%5D=12&amp;link%5B1%5D=AND&amp;".
                 "contains%5B1%5D=%3C30&amp;field%5B1%5D=12&amp;sort=12&amp;is_deleted=0".
                  "&amp;searchtype[0]=contains&amp;searchtype[1]=contains&amp;start=0\">".
                 __('Contracts expiring in less than 30 days')."</a></td>";
      echo "<td class='numeric'>".$contract30."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td><a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset&amp;".
                 "glpisearchcount=2&amp;contains%5B0%5D=%3E0&amp;field%5B0%5D=13&amp;link%5B1%5D=AND&amp;".
                 "contains%5B1%5D=%3C7&amp;field%5B1%5D=13&amp;sort=12&amp;is_deleted=0".
                  "&amp;searchtype[0]=contains&amp;searchtype[1]=contains&amp;start=0\">".
                 __('Contracts where notice begins in less than 7 days')."</a></td>";
      echo "<td class='numeric'>".$contractpre7."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td><a href=\"".$CFG_GLPI["root_doc"]."/front/contract.php?reset=reset&amp;".
                 "glpisearchcount=2&amp;sort=13&amp;order=DESC&amp;start=0&amp;field[0]=13&amp;".
                 "field[1]=13&amp;link[1]=AND&amp;contains[0]=%3E6&amp;contains[1]=%3C30".
                  "&amp;searchtype[0]=contains&amp;searchtype[1]=contains\">".
                 __('Contracts where notice begins in less than 30 days')."</a></td>";
      echo "<td class='numeric'>".$contractpre30."</td></tr>";
      echo "</table>";
   }


   /**
    * Print the HTML array of suppliers for this contract
    *
    *@return Nothing (HTML display)
    **/
   function showSuppliers() {
      global $DB, $CFG_GLPI;

      $instID = $this->fields['id'];

      if (!$this->can($instID,'r')
          || !Session::haveRight("contact_enterprise","r")) {
         return false;
      }
      $canedit = $this->can($instID,'w');

      $query = "SELECT `glpi_contracts_suppliers`.`id`,
                       `glpi_suppliers`.`id` AS entID,
                       `glpi_suppliers`.`name` AS name,
                       `glpi_suppliers`.`website` AS website,
                       `glpi_suppliers`.`phonenumber` AS phone,
                       `glpi_suppliers`.`suppliertypes_id` AS type,
                       `glpi_entities`.`id` AS entity
                FROM `glpi_contracts_suppliers`,
                     `glpi_suppliers`
                LEFT JOIN `glpi_entities` ON (`glpi_entities`.`id`=`glpi_suppliers`.`entities_id`)
                WHERE `glpi_contracts_suppliers`.`contracts_id` = '$instID'
                      AND `glpi_contracts_suppliers`.`suppliers_id`=`glpi_suppliers`.`id`".
                      getEntitiesRestrictRequest(" AND","glpi_suppliers",'','',true). "
                ORDER BY `glpi_entities`.`completename`, `name`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);
      $i      = 0;

      echo "<form method='post' action=\"".$CFG_GLPI["root_doc"]."/front/contract.form.php\">";
      echo "<div class='spaced'><table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='6'>";
      if ($DB->numrows($result) == 0) {
         _e('No associated supplier');
      } else {
         echo _n('Associated supplier', 'Associated suppliers', $DB->numrows($result));
      }
      echo "</th></tr>";
      echo "<tr><th>".__('Supplier')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Third party type')."</th>";
      echo "<th>".__('Phone')."</th>";
      echo "<th>".__('Website')."</th>";
      echo "<th>&nbsp;</th></tr>";

      $used = array();
      while ($i < $number) {
         $ID      = $DB->result($result, $i, "id");
         $website = $DB->result($result, $i, "glpi_suppliers.website");
         if (!empty($website)) {
            $website = $DB->result($result, $i, "website");
            if (!preg_match("?https*://?",$website)) {
               $website = "http://".$website;
            }
            $website = "<a target=_blank href='$website'>".$DB->result($result, $i, "website")."</a>";
         }
         $entID         = $DB->result($result, $i, "entID");
         $entity        = $DB->result($result, $i, "entity");
         $used[$entID]  = $entID;
         $entname       = Dropdown::getDropdownName("glpi_suppliers", $entID);
         echo "<tr class='tab_bg_1'><td class='center'>";
         if ($_SESSION["glpiis_ids_visible"]
             || empty($entname)) {
            $entname = " (".$entID.")";
         }
         echo "<a href='".$CFG_GLPI["root_doc"]."/front/supplier.form.php?id=$entID'>".$entname;
         echo "</a></td>";
         echo "<td class='center'>".Dropdown::getDropdownName("glpi_entities",$entity)."</td>";
         echo "<td class='center'>";
         echo Dropdown::getDropdownName("glpi_suppliertypes", $DB->result($result,$i,"type"))."</td>";
         echo "<td class='center'>".$DB->result($result, $i, "phone")."</td>";
         echo "<td class='center'>".$website."</td>";
         echo "<td class='tab_bg_2 center'>";
         if ($canedit) {
            echo "<a href='".$CFG_GLPI["root_doc"].
                  "/front/contract.form.php?deletecontractsupplier=1&amp;id=$ID&amp;contracts_id=".
                  $instID."'><img src='".$CFG_GLPI["root_doc"]."/pics/delete.png' alt='".
                  __s('Delete')."'></a>";
         } else {
            echo "&nbsp;";
         }
         echo "</td></tr>";
         $i++;
      }
      if ($canedit) {
         if ($this->fields["is_recursive"]) {
            $nb = countElementsInTableForEntity("glpi_suppliers",
                                                getSonsOf("glpi_entities",
                                                          $this->fields["entities_id"]));
         } else {
            $nb = countElementsInTableForEntity("glpi_suppliers", $this->fields["entities_id"]);
         }
         if ($nb > count($used)) {
            echo "<tr class='tab_bg_1'><td class='right' colspan='2'>";
            echo "<input type='hidden' name='contracts_id' value='$instID'>";
            Supplier::dropdown(array('used'         => $used,
                                     'entity'       => $this->fields["entities_id"],
                                     'entity_sons'  => $this->fields["is_recursive"]));
            echo "</td><td class='center'>";
            echo "<input type='submit' name='addcontractsupplier' value=\""._sx('Button', 'Add')."\"
                   class='submit'>";
            echo "</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
            echo "</tr>";
         }
         }
      echo "</table></div></form>";
   }


   /**
    * Print the HTML array for Items linked to current contract
    *
    *@return Nothing (display)
    **/
   function showItems() {
      global $DB, $CFG_GLPI;

      $instID = $this->fields['id'];

      if (!$this->can($instID,'r')) {
         return false;
      }
      $canedit = $this->can($instID,'w');
      $rand    = mt_rand();

      $query = "SELECT DISTINCT `itemtype`
                FROM `glpi_contracts_items`
                WHERE `glpi_contracts_items`.`contracts_id` = '$instID'
                ORDER BY `itemtype`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);

      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='5'>";
      if ($DB->numrows($result) == 0) {
         _e('No associated item');
      } else {
         echo _n('Associated item', 'Associated items', $DB->numrows($result));
      }
      echo "</th></tr>";
      if ($canedit) {
         echo "</table></div>";

         echo "<form method='post' name='contract_form$rand' id='contract_form$rand' action=\"".
                $CFG_GLPI["root_doc"]."/front/contract.form.php\">";
         echo "<div class='spaced'>";
         echo "<table class='tab_cadre_fixe'>";
         // massive action checkbox
         echo "<tr><th>&nbsp;</th>";
      } else {
         echo "<tr>";
      }
      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Name')."</th>";
      echo "<th>".__('Serial number')."</th>";
      echo "<th>".__('Inventory number')."</th>";
      echo "<th>".__('Status')."</th>";
      echo "</tr>";

      $totalnb = 0;
      for ($i=0 ; $i<$number ; $i++) {
         $itemtype = $DB->result($result, $i, "itemtype");
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }
         if ($item->canView()) {
            $itemtable = getTableForItemType($itemtype);
            $query     = "SELECT `$itemtable`.*,
                                 `glpi_contracts_items`.`id` AS IDD,
                                 `glpi_entities`.`id` AS entity
                          FROM `glpi_contracts_items`,
                               `$itemtable`";
            if ($itemtype != 'Entity') {
               $query .= " LEFT JOIN `glpi_entities`
                                 ON (`$itemtable`.`entities_id`=`glpi_entities`.`id`) ";
            }
            $query .= " WHERE `$itemtable`.`id` = `glpi_contracts_items`.`items_id`
                              AND `glpi_contracts_items`.`itemtype` = '$itemtype'
                              AND `glpi_contracts_items`.`contracts_id` = '$instID'";

            if ($item->maybeTemplate()) {
               $query .= " AND `$itemtable`.`is_template` = '0'";
            }
            $query .= getEntitiesRestrictRequest(" AND",$itemtable, '', '',
                                                 $item->maybeRecursive())."
                      ORDER BY `glpi_entities`.`completename`, `$itemtable`.`name`";

            $result_linked = $DB->query($query);
            $nb            = $DB->numrows($result_linked);

            if ($nb > $_SESSION['glpilist_limit']) {
               echo "<tr class='tab_bg_1'>";
               if ($canedit) {
                  echo "<td>&nbsp;</td>";
               }
               //TRANS: %1$s is a type name, %2$s is a number
               echo "<td class='center'>".sprintf(__('%1$s: %2$s'), $item->getTypeName($nb), $nb).
                    "</td>";
               echo "<td class='center' colspan='2'>";
               echo "<a href='". Toolbox::getItemTypeSearchURL($itemtype) . "?" .
                     rawurlencode("contains[0]") . "=" . rawurlencode('$$$$'.$instID) . "&amp;" .
                     rawurlencode("field[0]") . "=29&amp;sort=80&amp;order=ASC&amp;is_deleted=0".
                     "&amp;start=0". "'>" . __('Device list')."</a></td>";
               echo "<td class='center'>-</td><td class='center'>-</td></tr>";

            } else if ($nb > 0) {
               for ($prem=true ; $data=$DB->fetch_assoc($result_linked) ; $prem=false) {
                  $name = $data["name"];
                  if ($_SESSION["glpiis_ids_visible"]
                      || empty($data["name"])) {
                     $name = " (".$data["id"].")";
                  }
                  $link = Toolbox::getItemTypeFormURL($itemtype);
                  $name = "<a href=\"".$link."?id=".$data["id"]."\">".$name."</a>";

                  echo "<tr class='tab_bg_1'>";
                  if ($canedit) {
                     $sel = "";
                     if (isset($_GET["select"]) && ($_GET["select"] == "all")) {
                        $sel = "checked";
                     }
                     echo "<td width='10'>";
                     echo "<input type='checkbox' name='item[".$data["IDD"]."]' value='1' $sel></td>";
                  }
                  if ($prem) {
                     $typename = $item->getTypeName($nb);
                     echo "<td class='center top' rowspan='$nb'>".
                            ($nb  >1 ? sprintf(__('%1$s: %2$s'), $typename, $nb): $typename)."</td>";
                  }
                  echo "<td class='center'>";
                  echo Dropdown::getDropdownName("glpi_entities",$data['entity'])."</td>";
                  echo "<td class='center".
                         (isset($data['is_deleted']) && $data['is_deleted'] ? " tab_bg_2_2'" : "'");
                  echo ">".$name."</td>";
                  echo "<td class='center'>".
                         (isset($data["serial"])? "".$data["serial"]."" :"-")."</td>";
                  echo "<td class='center'>".
                         (isset($data["otherserial"])? "".$data["otherserial"]."" :"-")."</td>";
                  echo "<td class='center'>";
                  if (isset($data["states_id"])) {
                     echo Dropdown::getDropdownName("glpi_states", $data['states_id']);
                  } else {
                     echo '&nbsp;';
                  }
                  echo "</td></tr>";

               }
            }
            $totalnb += $nb;
         }
      }
      echo "<tr class='tab_bg_2'>";
      echo "<td class='center' colspan='2'>".
            ($totalnb > 0 ? sprintf(__('%1$s = %2$s'), __('Total'), $totalnb) : "&nbsp;");
      echo "</td><td colspan='5'>&nbsp;</td></tr> ";

      if ($canedit) {
         if (($this->fields['max_links_allowed'] == 0)
             || ($this->fields['max_links_allowed'] > $totalnb)) {

            echo "<tr class='tab_bg_1'><td colspan='5' class='right'>";
            Dropdown::showAllItems("items_id", 0, 0,
                                   ($this->fields['is_recursive']?-1:$this->fields['entities_id']),
                                   $CFG_GLPI["contract_types"], false, true);
            echo "</td><td class='center'>";
            echo "<input type='submit' name='additem' value=\""._sx('Button', 'Add')."\"
                   class='submit'>";
            echo "</td><td>&nbsp;</td></tr>";
         }
         echo "</table>";

         Html::openArrowMassives("contract_form$rand", true);
         echo "<input type='hidden' name='contracts_id' value='$instID'>";
         Html::closeArrowMassives(array('deleteitem' => __('Delete')));

      } else {
         echo "</table>";
      }
      echo "</div></form>";
   }


   /**
    * Get the entreprise name  for the contract
    *
    *@return string of names (HTML)
   **/
   function getSuppliersNames() {
      global $DB;

      $query = "SELECT `glpi_suppliers`.`id`
                FROM `glpi_contracts_suppliers`,
                     `glpi_suppliers`
                WHERE `glpi_contracts_suppliers`.`suppliers_id` = `glpi_suppliers`.`id`
                      AND `glpi_contracts_suppliers`.`contracts_id` = '".$this->fields['id']."'";
      $result = $DB->query($query);
      $out    = "";
      while ($data = $DB->fetch_assoc($result)) {
         $out .= Dropdown::getDropdownName("glpi_suppliers", $data['id'])."<br>";
      }
      return $out;
   }


   /**
    * Print an HTML array of contract associated to an object
    *
    * @param $item            CommonDBTM object wanted
    * @param $withtemplate     not used (to be deleted) (default '')
    *
    * @return Nothing (display)
   **/
   static function showAssociated(CommonDBTM $item, $withtemplate='') {
      global $DB, $CFG_GLPI;

      $itemtype = $item->getType();
      $ID       = $item->fields['id'];

      if (!Session::haveRight("contract","r") || !$item->can($ID,"r")) {
         return false;
      }

      $canedit = $item->can($ID,"w");

      $query = "SELECT `glpi_contracts_items`.*
                FROM `glpi_contracts_items`,
                     `glpi_contracts`
                LEFT JOIN `glpi_entities` ON (`glpi_contracts`.`entities_id`=`glpi_entities`.`id`)
                WHERE `glpi_contracts`.`id`=`glpi_contracts_items`.`contracts_id`
                      AND `glpi_contracts_items`.`items_id` = '$ID'
                      AND `glpi_contracts_items`.`itemtype` = '$itemtype'".
                      getEntitiesRestrictRequest(" AND","glpi_contracts",'','',true)."
                ORDER BY `glpi_contracts`.`name`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);
      $i      = 0;

      echo "<div class='spaced'>";
      if ($withtemplate!=2) {
         echo "<form method='post' action=\"".$CFG_GLPI["root_doc"]."/front/contract.form.php\">";
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='8'>";

      if ($number == 0) {
         _e('No associated contract');
      } else if ($number == 1) {
         echo _n ('Associated contract', 'Associated contracts', 1);
      } else {
         echo _n ('Associated contract', 'Associated contracts', 2);
      }
      echo "</th></tr>";

      echo "<tr><th>".__('Name')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>"._x('Phone', 'Number')."</th>";
      echo "<th>".__('Contract type')."</th>";
      echo "<th>".__('Supplier')."</th>";
      echo "<th>".__('Start date')."</th>";
      echo "<th>".__('Initial contract period')."</th>";
      if ($withtemplate != 2) {
         echo "<th>&nbsp;</th>";
      }
      echo "</tr>";

      if ($number > 0) {
         Session::initNavigateListItems(__CLASS__,
                              //TRANS : %1$s is the itemtype name,
                              //         %2$s is the name of the item (used for headings of a list)
                                        sprintf(__('%1$s = %2$s'),
                                                $item->getTypeName(1), $item->getName()));
      }
      $contracts = array();
      while ($i < $number) {
         $cID         = $DB->result($result, $i, "contracts_id");
         Session::addToNavigateListItems(__CLASS__,$cID);
         $contracts[] = $cID;
         $assocID     = $DB->result($result, $i, "id");
         $con         = new self();
         $con->getFromDB($cID);
         echo "<tr class='tab_bg_1".($con->fields["is_deleted"]?"_2":"")."'>";
         echo "<td class='center b'>";
         $name = $con->fields["name"];
         if ($_SESSION["glpiis_ids_visible"]
             || empty($con->fields["name"])) {
            $name = " (".$con->fields["id"].")";
         }
         echo "<a href='".$CFG_GLPI["root_doc"]."/front/contract.form.php?id=$cID'>".$name;
         echo "</a></td>";
         echo "<td class='center'>";
         echo Dropdown::getDropdownName("glpi_entities", $con->fields["entities_id"])."</td>";
         echo "<td class='center'>".$con->fields["num"]."</td>";
         echo "<td class='center'>";
         echo Dropdown::getDropdownName("glpi_contracttypes", $con->fields["contracttypes_id"]).
              "</td>";
         echo "<td class='center'>".$con->getSuppliersNames()."</td>";
         echo "<td class='center'>".Html::convDate($con->fields["begin_date"])."</td>";

         echo "<td class='center'>".$con->fields["duration"]." "._n('month', 'months',
                                                                    $con->fields["duration"]);
         if (($con->fields["begin_date"] != '')
             && !empty($con->fields["begin_date"])) {
            echo " -> ".Infocom::getWarrantyExpir($con->fields["begin_date"],
                                                  $con->fields["duration"]);
         }
         echo "</td>";

         if ($withtemplate != 2) {
            echo "<td class='tab_bg_2 center'>";
            if ($canedit) {
               echo "<a href='".$CFG_GLPI["root_doc"].
                     "/front/contract.form.php?deleteitem=deleteitem&amp;id=$assocID&amp;contracts_id=$cID'>";
               echo "<img src='".$CFG_GLPI["root_doc"]."/pics/delete.png' alt='".__s('Delete')."'>".
                    "</a>";
            } else {
               echo "&nbsp;";
            }
            echo "</td>";
         }
         echo "</tr>";
         $i++;
      }
      $q = "SELECT *
            FROM `glpi_contracts`
            WHERE `is_deleted` = '0' ".
                  getEntitiesRestrictRequest("AND", "glpi_contracts", "entities_id",
                                             $item->getEntityID(), true);
      $result = $DB->query($q);
      $nb     = $DB->numrows($result);

      if ($canedit) {
         if (($withtemplate != 2)
             && ($nb > count($contracts))) {
            echo "<tr class='tab_bg_1'><td class='right' colspan='3'>";
            echo "<input type='hidden' name='items_id' value='$ID'>";
            echo "<input type='hidden' name='itemtype' value='$itemtype'>";
            self::dropdown(array('entity' => $item->getEntityID(),
                                 'used'   => $contracts));
            echo "</td><td class='center'>";
            echo "<input type='submit' name='additem' value=\""._sx('Button', 'Add')."\"
                   class='submit'>";
            echo "</td>";
            echo "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
         }
      }
      echo "</table>";

      if ($withtemplate != 2) {
         echo "</form>";
      }
      echo "</div>";
   }


   static function cronInfo($name) {
      return array('description' => __('Send alarms on contracts'));
   }


   /**
    * Cron action on contracts : alert depending of the config : on notice and expire
    *
    * @param $task for log, if NULL display (default NULL)
   **/
   static function cronContract($task=NULL) {
      global $DB, $CFG_GLPI;

      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }

      $message       = array();
      $items_notice  = array();
      $items_end     = array();
      $cron_status   = 0;


      $contract_infos[Alert::END]    = array();
      $contract_infos[Alert::NOTICE] = array();
      $contract_messages             = array();

      foreach (Entity::getEntitiesToNotify('use_contracts_alert') as $entity => $value) {
         $before       = Entity::getUsedConfig('send_contracts_alert_before_delay', $entity);
         $query_notice = "SELECT `glpi_contracts`.*
                          FROM `glpi_contracts`
                          LEFT JOIN `glpi_alerts`
                              ON (`glpi_contracts`.`id` = `glpi_alerts`.`items_id`
                                  AND `glpi_alerts`.`itemtype` = 'Contract'
                                  AND `glpi_alerts`.`type`='".Alert::NOTICE."')
                          WHERE (`glpi_contracts`.`alert` & ".pow(2,Alert::NOTICE).") >'0'
                                AND `glpi_contracts`.`is_deleted` = '0'
                                AND `glpi_contracts`.`begin_date` IS NOT NULL
                                AND `glpi_contracts`.`duration` <> '0'
                                AND `glpi_contracts`.`notice` <> '0'
                                AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`,
                                                     INTERVAL `glpi_contracts`.`duration` MONTH),
                                             CURDATE()) > '0'
                                AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`,
                                                     INTERVAL (`glpi_contracts`.`duration`
                                                                -`glpi_contracts`.`notice`) MONTH),
                                             CURDATE()) < '$before'
                                AND `glpi_alerts`.`date` IS NULL
                                AND `glpi_contracts`.`entities_id` = '".$entity."'";

         $query_end = "SELECT `glpi_contracts`.*
                       FROM `glpi_contracts`
                       LEFT JOIN `glpi_alerts`
                           ON (`glpi_contracts`.`id` = `glpi_alerts`.`items_id`
                               AND `glpi_alerts`.`itemtype` = 'Contract'
                               AND `glpi_alerts`.`type`='".Alert::END."')
                       WHERE (`glpi_contracts`.`alert` & ".pow(2,Alert::END).") > '0'
                             AND `glpi_contracts`.`is_deleted` = '0'
                             AND `glpi_contracts`.`begin_date` IS NOT NULL
                             AND `glpi_contracts`.`duration` <> '0'
                             AND DATEDIFF(ADDDATE(`glpi_contracts`.`begin_date`,
                                                  INTERVAL (`glpi_contracts`.`duration`) MONTH),
                                          CURDATE()) < '$before'
                             AND `glpi_alerts`.`date` IS NULL
                             AND `glpi_contracts`.`entities_id` = '".$entity."'";

         $querys = array('notice' => $query_notice,
                         'end'    => $query_end);

         foreach ($querys as $type => $query) {
            foreach ($DB->request($query) as $data) {
               $entity  = $data['entities_id'];

               $message = sprintf(__('%1$s: %2$s')."<br>\n", $data["name"],
                                  Infocom::getWarrantyExpir($data["begin_date"],
                                                            $data["duration"], $data["notice"]));
               $contract_infos[$type][$entity][$data['id']] = $data;

               if (!isset($contract_messages[$type][$entity])) {
                  switch ($type) {
                     case 'notice' :
                        $contract_messages[$type][$entity] = __('Contract entered in notice time').
                                                             "<br>";
                        break;

                     case 'end' :
                        $contract_messages[$type][$entity] = __('Contract ended')."<br>";
                        break;
                  }
               }
               $contract_messages[$type][$entity] .= $message;
            }
         }

         // Get contrats with periodicity alerts
         $query_periodicity = "SELECT `glpi_contracts`.*
                               FROM `glpi_contracts`
                               WHERE `glpi_contracts`.`alert` & ".pow(2,Alert::PERIODICITY)." > '0'
                                     AND `glpi_contracts`.`entities_id` = '".$entity."' ";

         // Foreach ones :
         foreach ($DB->request($query_periodicity) as $data) {
            $entity    = $data['entities_id'];
            // Compute end date + 12 month : do not send alerts after
            $end_alert = date('Y-m-d',
                              strtotime($data['begin_date']." +".($data['duration']+12)." month"));
            if (!empty($data['begin_date'])
                && $data['periodicity']
                && ($end_alert > date('Y-m-d'))) {
               $todo = array('periodicity' => Alert::PERIODICITY);
               if ($data['alert']&pow(2,Alert::NOTICE)) {
                  $todo['periodicitynotice'] = Alert::NOTICE;
               }

               // Get previous alerts
               foreach ($todo as $type => $event) {
                  $previous_alerts[$type] = Alert::getAlertDate(__CLASS__, $data['id'], $event);
               }
               // compute next alert date based on already send alerts (or not)
               foreach ($todo as $type => $event) {
                  $next_alerts[$type] = date('Y-m-d',
                                             strtotime($data['begin_date']." -".($before)." day"));
                  if ($type == Alert::NOTICE) {
                     $next_alerts[$type]
                           = date('Y-m-d',
                                  strtotime($next_alerts[$type]." -".($data['notice'])." month"));
                  }

                  $today_limit = date('Y-m-d',
                                      strtotime(date('Y-m-d')." -" .($data['periodicity'])." month"));

                  // Init previous by begin date if not set
                  if (empty($previous_alerts[$type])) {
                     $previous_alerts[$type] = $today_limit;
                  }

                  while (($next_alerts[$type] < $previous_alerts[$type])
                         && ($next_alerts[$type] < $end_alert)) {
                     $next_alerts[$type]
                        = date('Y-m-d',
                               strtotime($next_alerts[$type]." +".($data['periodicity'])." month"));
                  }

                  // If this date is passed : clean alerts and send again
                  if ($next_alerts[$type] <= date('Y-m-d')) {
                     $alert              = new Alert();
                     $alert->clear(__CLASS__, $data['id'], $event);
                     $real_alert_date    = date('Y-m-d',
                                                strtotime($next_alerts[$type]." +".($before)." day"));
                     $message            = sprintf(__('%1$s: %2$s')."<br>\n",
                                                 $data["name"], Html::convDate($real_alert_date));
                     $data['alert_date'] = $real_alert_date;
                     $contract_infos[$type][$entity][$data['id']] = $data;

                     switch ($type) {
                        case 'periodicitynotice' :
                           $contract_messages[$type][$entity]
                                 = __('Contract entered in notice time for period')."<br>";
                           break;

                        case 'periodicity' :
                           $contract_messages[$type][$entity] = __('Contract period ended')."<br>";
                           break;
                     }
                     $contract_messages[$type][$entity] .= $message;
                  }
               }
            }
         }
      }

      foreach (array('notice'            => Alert::NOTICE,
                     'end'               => Alert::END,
                     'periodicity'       => Alert::PERIODICITY,
                     'periodicitynotice' => Alert::NOTICE) as $event => $type ) {
         if (isset($contract_infos[$event]) && count($contract_infos[$event])) {
            foreach ($contract_infos[$event] as $entity => $contracts) {
               if (NotificationEvent::raiseEvent($event, new self(),
                                                 array('entities_id' => $entity,
                                                       'items'       => $contracts))) {
                  $message     = $contract_messages[$event][$entity];
                  $cron_status = 1;
                  $entityname  = Dropdown::getDropdownName("glpi_entities", $entity);
                  if ($task) {
                     $task->log(sprintf(__('%1$s: %2$s')."\n", $entityname, $message));
                     $task->addVolume(1);
                  } else {
                     Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'),
                                                              $entityname, $message));
                  }

                  $alert               = new Alert();
                  $input["itemtype"]   = __CLASS__;
                  $input["type"]       = $type;
                  foreach ($contracts as $id => $contract) {
                     $input["items_id"] = $id;

                     $alert->add($input);
                     unset($alert->fields['id']);
                  }

               } else {
                  $entityname = Dropdown::getDropdownName('glpi_entities', $entity);
                  //TRANS: %1$s is entity name, %2$s is the message
                  $msg = sprintf(__('%1$s: %2$s'), $entityname, __('send contract alert failed'));
                  if ($task) {
                     $task->log($msg);
                  } else {
                     Session::addMessageAfterRedirect($msg, false, ERROR);
                  }
               }
            }
         }
      }

      return $cron_status;
   }


   /**
    * Print a select with contracts
    *
    * Print a select named $name with contracts options and selected value $value
    * @param $options   array of possible options:
    *    - name : string / name of the select (default is contracts_id)
    *    - value : integer / preselected value (default 0)
    *    - entity : integer or array / restrict to a defined entity or array of entities
    *                   (default -1 : no restriction)
    *    - entity_sons : boolean / if entity restrict specified auto select its sons
    *                   only available if entity is a single value not an array (default false)
    *    - used : array / Already used items ID: not to display in dropdown (default empty)
    *    - nochecklimit : boolean / disable limit for nomber of device (for supplier, default false)
    *
    * @return Nothing (display)
   **/
   static function dropdown($options=array()) {
      global $DB;

      //$name,$entity_restrict=-1,$alreadyused=array(),$nochecklimit=false
      $p['name']           = 'contracts_id';
      $p['value']          = '';
      $p['entity']         = '';
      $p['entity_sons']    = false;
      $p['used']           = array();
      $p['nochecklimit']   = false;
      $p['on_change']      = '';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      if (!($p['entity'] < 0)
          && $p['entity_sons']) {
         if (is_array($p['entity'])) {
            // no translation needed (only for dev)
            echo "entity_sons options is not available with array of entity";
         } else {
            $p['entity'] = getSonsOf('glpi_entities',$p['entity']);
         }
      }

      $entrest = "";
      $idrest = "";
      if ($p['entity'] >= 0) {
         $entrest = getEntitiesRestrictRequest("AND", "glpi_contracts", "entities_id",
                                               $p['entity'], true);
      }
      if (count($p['used'])) {
         $idrest = " AND `glpi_contracts`.`id` NOT IN('".implode("','",$p['used'])."') ";
      }
      $query = "SELECT `glpi_contracts`.*
                FROM `glpi_contracts`
                LEFT JOIN `glpi_entities` ON (`glpi_contracts`.`entities_id` = `glpi_entities`.`id`)
                WHERE `glpi_contracts`.`is_deleted` = '0' $entrest $idrest
                ORDER BY `glpi_entities`.`completename`,
                         `glpi_contracts`.`name` ASC,
                         `glpi_contracts`.`begin_date` DESC";
      $result = $DB->query($query);
      echo "<select name='".$p['name']."'";
      if (!empty($p["on_change"])) {
         echo " onChange='".$p["on_change"]."'";
      }
      echo '>';

      if ($p['value'] > 0) {
         $output = Dropdown::getDropdownName('glpi_contracts', $p['value']);
         if ($_SESSION["glpiis_ids_visible"]) {
            $output = sprintf(__('%1$s (%2$s)'), $output, $p['value']);
         }
         echo "<option selected value='".$p['value']."'>".$output."</option>";
      } else {
         echo "<option value='-1'>".Dropdown::EMPTY_VALUE."</option>";
      }
      $prev = -1;
      while ($data = $DB->fetch_assoc($result)) {
         if ($p['nochecklimit']
             || ($data["max_links_allowed"] == 0)
             || ($data["max_links_allowed"] > countElementsInTable("glpi_contracts_items",
                                                                   "contracts_id
                                                                     = '".$data['id']."'" ))) {
            if ($data["entities_id"] != $prev) {
               if ($prev >= 0) {
                  echo "</optgroup>";
               }
               $prev = $data["entities_id"];
               echo "<optgroup label=\"". Dropdown::getDropdownName("glpi_entities", $prev) ."\">";
            }

            $name = $data["name"];
            if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
               $name = " (".$data["id"].")";
            }

            echo "<option  value='".$data["id"]."'>";
            $tmp = sprintf(__('%1$s - %2$s'), $name, $data["num"]);
            $tmp = sprintf(__('%1$s - %2$s'), $tmp, Html::convDateTime($data["begin_date"]));
            echo Toolbox::substr($tmp, 0, $_SESSION["glpidropdown_chars_limit"]);
            echo "</option>";
         }
      }
      if ($prev >= 0) {
         echo "</optgroup>";
      }
      echo "</select>";
   }


   /**
    * Print a select with contract renewal
    *
    * Print a select named $name with contract renewal options and selected value $value
    *
    * @param $name   string   HTML select name
    * @param $value  integer  HTML select selected value (default = 0)
    *
    * @return Nothing (display)
   **/
   static function dropdownContractRenewal($name, $value=0) {

      $tmp[0] = __('Never');
      $tmp[1] = __('Tacit');
      $tmp[2] = __('Express');
      Dropdown::showFromArray($name, $tmp, array('value' => $value));
   }


   /**
    * Get the renewal type name
    *
    * @param $value integer   HTML select selected value
    *
    * @return string
   **/
   static function getContractRenewalName($value) {

      switch ($value) {
         case 1 :
            return __('Tacit');

         case 2 :
            return __('Express');

         default :
            return "";
      }
   }


   /**
    * Get renewal ID by name
    *
    * @param $value the name of the renewal
    *
    * @return the ID of the renewal
   **/
   static function getContractRenewalIDByName($value) {

      if (stristr($value, __('Tacit'))) {
         return 1;
      }
      if (stristr($value, __('Express'))) {
         return 2;
      }
      return 0;
   }


   /**
    * @param $options array
   **/
   static function dropdownAlert(array $options) {

      if (!isset($options['value'])) {
         $value = 0;
      } else {
         $value = $options['value'];
      }

      $tab = array();
      if (isset($options['inherit_parent']) && $options['inherit_parent']) {
         $tab[Entity::CONFIG_PARENT] = __('Inheritance of the parent entity');
      }

      $tab += self::getAlertName();

      Dropdown::showFromArray($options['name'], $tab, array('value' => $value));
   }


   /**
    * Get the possible value for contract alert
    *
    * @since version 0.83
    *
    * @param $val if not set, ask for all values, else for 1 value (default NULL)
    *
    * @return array or string
   **/
   static function getAlertName($val=NULL) {

      $tmp[0]                                                  = Dropdown::EMPTY_VALUE;
      $tmp[pow(2, Alert::END)]                                 = __('End');
      $tmp[pow(2, Alert::NOTICE)]                              = __('Notice');
      $tmp[(pow(2, Alert::END) + pow(2, Alert::NOTICE))]       = __('End + Notice');
      $tmp[pow(2, Alert::PERIODICITY)]                         = __('Period end');
      $tmp[pow(2, Alert::PERIODICITY) + pow(2, Alert::NOTICE)] = __('Period end + Notice');

      if (is_null($val)) {
         return $tmp;
      }
      if (isset($tmp[$val])) {
         return $tmp[$val];
      }
      return NOT_AVAILABLE;
   }


   /**
    * Display debug information for current object
   **/
   function showDebug() {

      $options['entities_id'] = $this->getEntityID();
      $options['contracts']   = array();
      NotificationEvent::debugEvent($this, $options);
   }


   function getUnallowedFieldsForUnicity() {

      return array_merge(parent::getUnallowedFieldsForUnicity(),
                         array('begin_date', 'duration', 'entities_id', 'monday_begin_hour',
                               'monday_end_hour', 'saturday_begin_hour', 'saturday_end_hour',
                               'week_begin_hour', 'week_end_hour'));
   }

}
?>
