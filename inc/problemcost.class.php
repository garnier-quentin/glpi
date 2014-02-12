<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.

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
 * ProblemCost Class
 *
 * @since version 0.85
**/
class ProblemCost extends CommonITILCost {

   // From CommonDBChild
   static public $itemtype  = 'Problem';
   static public $items_id  = 'problems_id';


   static function canCreate() {
      return Session::haveRight('problem', UPDATE);
   }


   static function canView() {
      return Session::haveRightsOr('problem', array(Problem::READALL, Problem::READMY));
   }


   static function canUpdate() {
      return Session::haveRight('problem', UPDATE);
   }

}
?>
