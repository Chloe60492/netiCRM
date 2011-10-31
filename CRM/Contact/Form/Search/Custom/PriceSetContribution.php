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
 * $Id$
 *
 */

require_once 'CRM/Contact/Form/Search/Custom/Base.php';

class CRM_Contact_Form_Search_Custom_PriceSetContribution
   extends    CRM_Contact_Form_Search_Custom_Base
   implements CRM_Contact_Form_Search_Interface {

    protected $_price_set_id = null;

    protected $_tableName = null;

    function __construct( &$formValues ) {
        parent::__construct( $formValues );

        $this->_price_set_id= CRM_Utils_Array::value( 'price_set_id', $this->_formValues );

        $this->setColumns( );

        if ( $this->_price_set_id ) {
            $this->buildTempTable( );
        
            $this->fillTable( );
        }

    }

    function __destruct( ) {
        /*
        if ( $this->_eventID ) {
            $sql = "DROP TEMPORARY TABLE {$this->_tableName}";
            CRM_Core_DAO::executeQuery( $sql,
                                        CRM_Core_DAO::$_nullArray ) ;
        }
        */
    }

    function buildTempTable( ) {
        $randomNum = md5( uniqid( ) );
        $this->_tableName = "civicrm_temp_custom_{$randomNum}";
        $sql = "
CREATE TEMPORARY TABLE {$this->_tableName} (
  id int unsigned NOT NULL AUTO_INCREMENT,
  entity_table varchar(64) NOT NULL,
  entity_id int unsigned NOT NULL,
";

        foreach ( $this->_columns as $dontCare => $fieldName ) {
            if ( in_array( $fieldName, array( 'eneity_table',
                                              'entity_id') ) ) {
                continue;
            }
            $sql .= "{$fieldName} int default 0,\n";
        }
        
        $sql .= "
PRIMARY KEY ( id ),
UNIQUE INDEX unique_entity ( entity_table, entity_id )
) ENGINE=HEAP
";
        
        CRM_Core_DAO::executeQuery( $sql, CRM_Core_DAO::$_nullArray ) ;
    }

    function fillTable( ) {
        $sql = "
SELECT l.price_field_value_id as price_field_value_id, 
       l.qty,
       l.entity_table,
       l.entity_id
FROM   civicrm_line_item l, civicrm_price_set_entity e
WHERE e.price_set_id = $this->_price_set_id AND
      l.entity_table = e.entity_table AND
      l.entity_id = e.entity_id
ORDER BY l.entity_table, l.entity_id ASC
";

        $dao = CRM_Core_DAO::executeQuery( $sql, CRM_Core_DAO::$_nullArray );

        // first store all the information by option value id
        $rows = array( );
        while ( $dao->fetch( ) ) {
            $uniq = $dao->entity_table. "-". $dao->entity_id;
            $rows[$uniq][] = "price_field_{$dao->price_field_value_id} = {$dao->qty}";
        }

        foreach ( array_keys( $rows ) as $entity ) {
            if(is_array($rows[$entity])){
              $values = implode(',', $rows[$entity] );
              list($entity_table, $entity_id) = explode('-', $entity);
            }
            $values .= ", entity_table = '{$entity_table}', entity_id = $entity_id";
            $sql = "REPLACE INTO {$this->_tableName} SET $values";
            CRM_Core_DAO::executeQuery( $sql, CRM_Core_DAO::$_nullArray );
        }
        $dao = CRM_Core_DAO::executeQuery("SELECT * FROM $this->_tableName");
        while($dao->fetch()){
            $sql = "SELECT contact_id FROM $dao->entity_table WHERE id = $dao->entity_id";
            $contact_id = CRM_Core_DAO::singleValueQuery($sql);
            $sql = "UPDATE {$this->_tableName} SET contact_id = $contact_id WHERE entity_id = $dao->entity_id AND entity_table = '$dao->entity_table'";
            CRM_Core_DAO::executeQuery( $sql, CRM_Core_DAO::$_nullArray );
        }
    }

    function priceSetDAO( $price_set_id = null ) {

        // get all the events that have a price set associated with it
        $sql = "
SELECT e.id    as id,
       e.title as title,
       p.price_set_id as price_set_id
FROM   civicrm_price_set      e,
       civicrm_price_set_entity  p

WHERE  p.price_set_id = e.id
";

        $params = array( );
        if ( $price_set_id ) {
            $params[1] = array( $price_set_id, 'Integer' );
            $sql .= " AND e.id = $price_set_id";
        }

        $dao = CRM_Core_DAO::executeQuery( $sql,
                                           $params );
        return $dao;
    }

    function buildForm( &$form ) {
        CRM_Core_OptionValue::getValues(array('name' => 'custom_search'), &$custom_search);
        foreach($custom_search as $c){
          if($c['value'] == $_GET['csid']){
            $this->setTitle($c['description']);
            break;
          }
        }
        $dao = $this->priceSetDAO( );

        $price_set = array( );
        while ( $dao->fetch( ) ) {
            $price_set[$dao->id] = $dao->title;
        }

        if ( empty( $price_set) ) {
            CRM_Core_Error::fatal( ts( 'There are no Price Sets' ) );
        }

        $form->add( 'select',
                    'price_set_id',
                    ts( 'Price Set' ),
                    $price_set,
                    true );

        /**
         * You can define a custom title for the search form
         */
         $this->setTitle('Price Set Export');
         
         /**
         * if you are using the standard template, this array tells the template what elements
         * are part of the search criteria
         */
         $form->assign( 'elements', array( 'price_set_id' ) );
    }

    function setColumns( ) {
        $this->_columns = array( ts('Contact Id')      => 'contact_id'    ,
                                 ts('Name')            => 'display_name'  );

        if ( ! $this->_price_set_id ) {
            return;
        }

        // for the selected event, find the price set and all the columns associated with it.
        // create a column for each field and option group within it
        $dao = $this->priceSetDAO( $this->_formValues['price_set_id'] );

        if ( $dao->fetch( ) &&
             ! $dao->price_set_id ) {
            CRM_Core_Error::fatal( ts( 'There are no events with Price Sets' ) );
        }

        // get all the fields and all the option values associated with it
        require_once 'CRM/Price/BAO/Set.php';
        $priceSet = CRM_Price_BAO_Set::getSetDetail( $dao->price_set_id );
        if ( is_array( $priceSet[$dao->price_set_id] ) ) {
            foreach ( $priceSet[$dao->price_set_id]['fields'] as $key => $value ) {
                if ( is_array( $value['options'] ) ) {
                    foreach ( $value['options'] as $oKey => $oValue ) {
                        $columnHeader = CRM_Utils_Array::value( 'label', $value );
                        if ( CRM_Utils_Array::value( 'html_type', $value) != 'Text' ) $columnHeader .= ' - '. $oValue['label'];
                            
                        $this->_columns[$columnHeader] = "price_field_{$oValue['id']}";
                    }
                }
            }
        }
    }

    function summary( ) {
        return null;
    }

    function all( $offset = 0, $rowcount = 0, $sort = null, $includeContactIDs = false ) {
        $selectClause = "
contact_a.id             as contact_id  ,
contact_a.display_name   as display_name";

        foreach ( $this->_columns as $dontCare => $fieldName ) {
            if ( in_array( $fieldName, array( 'contact_id',
                                              'display_name' ) ) ) {
                continue;
            }
            $selectClause .= ",\ntempTable.{$fieldName} as {$fieldName}";
        }
        
        return $this->sql( $selectClause,
                           $offset, $rowcount, $sort,
                           $includeContactIDs, null );

    }
    
    function from( ) {
        return "FROM civicrm_contact contact_a INNER JOIN {$this->_tableName} tempTable ON contact_a.id = tempTable.contact_id";
    }

    function where( $includeContactIDs = false ) {
        return ' ( 1 ) ';
    }

    function templateFile( ) {
        return 'CRM/Contact/Form/Search/Custom.tpl';
    }

    function setDefaultValues( ) {
        return array( );
    }

    function alterRow( &$row ) {
    }
    
    function setTitle( $title ) {
        if ( $title ) {
            CRM_Utils_System::setTitle( $title );
        } else {
            CRM_Utils_System::setTitle(ts('Export Price Set Info for an Event'));
        }
    }
}


