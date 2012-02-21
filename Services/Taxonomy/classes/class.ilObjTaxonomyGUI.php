<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Object/classes/class.ilObject2GUI.php";
require_once "./Services/Taxonomy/classes/class.ilObjTaxonomy.php";

/**
 * Taxonomy GUI class
 *
 * @author Alex Killing alex.killing@gmx.de 
 * @version $Id$
 *
 * @ilCtrl_Calls ilObjTaxonomyGUI:
 *
 * @ingroup ServicesTaxonomy
 */
class ilObjTaxonomyGUI extends ilObject2GUI
{
	
	/**
	 * Execute command
	 */
	function __construct($a_id = 0)
	{
		global $ilCtrl;
		
		parent::__construct($a_id, ilObject2GUI::OBJECT_ID);
		
		$ilCtrl->saveParameter($this, "tax_node");
	}
	
	/**
	 * Get type
	 *
	 * @return string type
	 */
	function getType()
	{
		return "tax";
	}

	/**
	 * Set assigned object
	 *
	 * @param int $a_val object id	
	 */
	function setAssignedObject($a_val)
	{
		$this->assigned_object_id = $a_val;
	}
	
	/**
	 * Get assigned object
	 *
	 * @return int object id
	 */
	function getAssignedObject()
	{
		return $this->assigned_object_id;
	}
	
	
	/**
	 * Execute command
	 */
	function executeCommand()
	{
		global $ilCtrl, $ilUser, $ilTabs;
		
		$next_class = $ilCtrl->getNextClass();
		$cmd = $ilCtrl->getCmd();

		switch ($next_class)
		{
			default:
				$this->$cmd();
				break;
		}
	}
	
	/**
	 * Init creation forms
	 */
	protected function initCreationForms()
	{
		$forms = array();
		
		$forms = array(
			self::CFORM_NEW => $this->initCreateForm("tax")
			);
		
		return $forms;
	}

	
	////
	//// Features that work on the base of an assigend object (AO)
	////
	
	/**
	 * 
	 *
	 * @param
	 * @return
	 */
	function editAOTaxonomySettings()
	{
		global $ilToolbar, $ilCtrl, $lng;
		
		$tax_ids = ilObjTaxonomy::getUsageOfObject($this->getAssignedObject());
		if (count($tax_ids) == 0)
		{
			$ilToolbar->addButton($lng->txt("tax_add_taxonomy"),
				$ilCtrl->getLinkTarget($this, "createAssignedTaxonomy"));
		}
		else
		{
			$this->listItems();
		}
		
		// currently we support only one taxonomy, otherwise we may need to provide
		// a list here
		
	}
	
	/**
	 * Determine current taxonomy (of assigned object)
	 *
	 * @param
	 * @return
	 */
	function determineAOCurrentTaxonomy()
	{
		// get taxonomy
		$tax_ids = ilObjTaxonomy::getUsageOfObject($this->getAssignedObject());
		$tax = new ilObjTaxonomy(current($tax_ids));
		return $tax;
	}
	
	
	/**
	 * List items
	 *
	 * @param
	 * @return
	 */
	function listItems()
	{
		global $tpl, $ilToolbar, $lng, $ilCtrl;
		
		$tax = $this->determineAOCurrentTaxonomy();
		
		// show toolbar
		$ilToolbar->addButton($lng->txt("tax_create_node"),
			$ilCtrl->getLinkTarget($this, "createTaxNode"));
		
		// show tree
		$this->showTree($tax->getTree());
		
		// show subitems
		include_once("./Services/Taxonomy/classes/class.ilTaxonomyTableGUI.php");
		$table = new ilTaxonomyTableGUI($this, "listItems", $tax->getTree(),
			(int) $_GET["tax_node"]);

		$tpl->setContent($table->getHTML());
	}
	
	
	/**
	 * Create assigned taxonomy
	 *
	 * @param
	 * @return
	 */
	function createAssignedTaxonomy()
	{
		$this->create();
	}
	
	
	/**
	 * If we run under an assigned object, the permission should be checked on
	 * the upper level
	 */
	protected function checkPermissionBool($a_perm, $a_cmd = "", $a_type = "", $a_node_id = null)
	{
		if ($this->getAssignedObject() > 0)
		{
			return true;
		}
		else
		{
			return parent::checkPermissionBool($a_perm, $a_cmd, $a_type, $a_node_id);
		}
	}
	
	/**
	 * Cancel creation
	 *
	 * @param
	 * @return
	 */
	function cancel()
	{
		global $ilCtrl;
		
		if ($this->getAssignedObject() > 0)
		{
			$ilCtrl->redirect($this, "editAOTaxonomySettings");
		}
		
		return parent::cancel();
	}
	
	/**
	 * Save taxonomy
	 *
	 * @param
	 * @return
	 */
	function save()
	{
		global $ilCtrl;
		
		if ($this->getAssignedObject() > 0)
		{
			$_REQUEST["new_type"] = "tax";
		}
		
		parent::saveObject();
	}
	
	/**
	 * After saving, 
	 *
	 * @param
	 * @return
	 */
	protected function afterSave(ilObject $a_new_object)
	{
		global $ilCtrl;

		if ($this->getAssignedObject() > 0)
		{
			ilObjTaxonomy::saveUsage($a_new_object->getId(),
				$this->getAssignedObject());
			$ilCtrl->redirect($this, "editAOTaxonomySettings");
		}
	}

	/**
	 * Show Editing Tree
	 */
	function showTree($a_tax_tree)
	{
		global $ilUser, $tpl, $ilCtrl, $lng;

		require_once ("./Services/Taxonomy/classes/class.ilTaxonomyExplorer.php");

		$exp = new ilTaxonomyExplorer($ilCtrl->getLinkTarget($this, "listItems"), $a_tax_tree);
		$exp->setTargetGet("tax_node");
		
		$exp->setExpandTarget($ilCtrl->getLinkTarget($this, "listItems"));
		
		if ($_GET["txexpand"] == "")
		{
			$expanded = $a_tax_tree->readRootId();
		}
		else
		{
			$expanded = $_GET["txexpand"];
		}

		if ($_GET["tax_node"] > 0)
		{
			$path = $a_tax_tree->getPathId($_GET["tax_node"]);
			$exp->setForceOpenPath($path);
			$exp->highlightNode($_GET["tax_node"]);
		}
		else
		{
			$exp->highlightNode($a_tax_tree->readRootId());
		}
		$exp->setExpand($expanded);
		// build html-output
		$exp->setOutput(0);
		$output = $exp->getOutput();

		// asynchronous output
		if ($ilCtrl->isAsynch())
		{
			echo $output; exit;
		}
		
		$tpl->setLeftContent($output);
	}

	/**
	 * Create tax node
	 *
	 * @param
	 * @return
	 */
	function createTaxNode()
	{
		global $tpl;
		
		$this->initTaxNodeForm("create");
		$tpl->setContent($this->form->getHTML());
	}
	
	
	/**
	 * Init tax node form
	 *
	 * @param        int        $a_mode        Edit Mode
	 */
	public function initTaxNodeForm($a_mode = "edit")
	{
		global $lng, $ilCtrl;
	
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();

		// title
		$ti = new ilTextInputGUI($this->lng->txt("title"), "title");
		$this->form->addItem($ti);
		
		if ($a_mode == "edit")
		{
			$node = new ilTaxonomyNode((int) $_GET["tax_node"]);
			$ti->setValue($node->getTitle());
		}
		
		// save and cancel commands
		if ($a_mode == "create")
		{
			$this->form->addCommandButton("saveTaxNode", $lng->txt("save"));
			$this->form->addCommandButton("listItems", $lng->txt("cancel"));
			$this->form->setTitle($lng->txt("tax_new_tax_node"));
		}
		else
		{
			$this->form->addCommandButton("updateTaxNode", $lng->txt("save"));
			$this->form->addCommandButton("listItems", $lng->txt("cancel"));
			$this->form->setTitle($lng->txt("tax_edit_tax_node"));
		}
	                
		$this->form->setFormAction($ilCtrl->getFormAction($this));
	 
	}
	
	/**
	 * Save tax node form
	 *
	 */
	public function saveTaxNode()
	{
		global $tpl, $lng, $ilCtrl;
	
		$this->initTaxNodeForm("create");
		if ($this->form->checkInput())
		{
			$tax = $this->determineAOCurrentTaxonomy();
			
			// create node
			include_once("./Services/Taxonomy/classes/class.ilTaxonomyNode.php");
			$node = new ilTaxonomyNode();
			$node->setTitle($this->form->getInput("title"));
			$node->setTaxonomyId($tax->getId());
			$node->create();
			
			// put in tree
			ilTaxonomyNode::putInTree($tax->getId(), $node, (int) $_GET["tax_node"]);
			
			ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
			$ilCtrl->redirect($this, "listItems");
		}
		else
		{
			$this->form->setValuesByPost();
			$tpl->setContent($this->form->getHtml());
		}
	}
	
	
	/**
	 * Update tax node
	 */
	function updateTaxNode()
	{
		global $lng, $ilCtrl, $tpl;
		
		$this->initTaxNodeForm("edit");
		if ($this->form->checkInput())
		{
			// create node
			$node = new ilTaxonomyNode($_GET["tax_node"]);
			$node->setTitle($this->form->getInput("title"));
			$node->update();

			ilUtil::sendInfo($lng->txt("msg_obj_modified"), true);
			$ilCtrl->redirect($this, "");
		}
		else
		{
			$this->form->setValuesByPost();
			$tpl->setContent($this->form->getHtml());
		}
	}
	
	/**
	 * Confirm deletion screen for items
	 */
	function deleteItems()
	{
		global $lng, $tpl, $ilCtrl, $ilTabs;

		if(!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

//		$ilTabs->clearTargets();
		
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$confirmation_gui = new ilConfirmationGUI();

		$confirmation_gui->setFormAction($ilCtrl->getFormAction($this));
		$confirmation_gui->setHeaderText($this->lng->txt("info_delete_sure"));

		// Add items to delete
		include_once("./Services/Taxonomy/classes/class.ilTaxonomyNode.php");
		foreach($_POST["id"] as $id)
		{
			$confirmation_gui->addItem("id[]", $id,
				ilTaxonomyNode::_lookupTitle($id));
		}

		$confirmation_gui->setCancel($lng->txt("cancel"), "listItems");
		$confirmation_gui->setConfirm($lng->txt("confirm"), "confirmedDelete");

		$tpl->setContent($confirmation_gui->getHTML());
	}

	/**
	 * Delete taxonomy nodes
	 */
	function confirmedDelete()
	{
		global $ilCtrl;
		
		include_once("./Services/Taxonomy/classes/class.ilTaxonomyNode.php");

		// delete all selected objects
		foreach ($_POST["id"] as $id)
		{
			$node = new ilTaxonomyNode($id);
			$tax = new ilObjTaxonomy($node->getTaxonomyId());
			$tax_tree = $tax->getTree();
			$node_data = $tax_tree->getNodeData($id);
			if (is_object($node))
			{
				$node->delete();
			}
			if($tax_tree->isInTree($id))
			{
				$tax_tree->deleteTree($node_data);
			}
		}

		// feedback
		ilUtil::sendInfo($this->lng->txt("info_deleted"),true);
		
		$ilCtrl->redirect($this, "listItems");
	}

}
?>