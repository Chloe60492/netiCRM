<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id: Email.php 30684 2010-11-11 08:37:12Z yashodha $
 *
 */

require_once 'CRM/Contribute/Form/Task.php';
require_once 'CRM/Contact/Form/Task/EmailCommon.php';

/**
 * This class provides the functionality to send SMS a group of
 * contacts.
 */
class CRM_Contribute_Form_Task_SMS extends CRM_Contribute_Form_Task {

  /**
   * Are we operating in "single mode", i.e. sending email to one
   * specific contact?
   *
   * @var boolean
   */
  public $_single = FALSE;

  /**
   * all the existing templates in the system
   *
   * @var array
   */
  public $_templates = NULL;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    parent::preProcess();

    // we have all the contribution ids, so now we get the contact ids
    parent::setContactIDs();

    $this->assign('single', $this->_single);
  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  public function buildQuickForm() {
    CRM_Contact_Form_Task_SMSCommon::buildQuickForm($this);
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    CRM_Contact_Form_Task_SMSCommon::postProcess($this);
  }
}

