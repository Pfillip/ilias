<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Object/classes/class.ilObjectGUI.php";

/**
* Class ilObjMediaCastGUI
*
* @author Alex Killing <alex.killing@gmx.de> 
* @version $Id$
* 
* @ilCtrl_Calls ilObjMediaCastGUI: ilPermissionGUI, ilInfoScreenGUI, ilExportGUI
* @ilCtrl_Calls ilObjMediaCastGUI: ilCommonActionDispatcherGUI
* @ilCtrl_IsCalledBy ilObjMediaCastGUI: ilRepositoryGUI, ilAdministrationGUI
*/
class ilObjMediaCastGUI extends ilObjectGUI
{
    
    private $additionalPurposes = array ("VideoPortable", "AudioPortable");
    private $purposeSuffixes = array ();
    private $mimeTypes = array();
        
	/**
	* Constructor
	* @access public
	*/
	function ilObjMediaCastGUI($a_data, $a_id, $a_call_by_reference, $a_prepare_output = true)
	{
		global $ilCtrl, $lng;
		
		$this->type = "mcst";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference,$a_prepare_output);
		$lng->loadLanguageModule("mcst");
		$lng->loadLanguageModule("news");
		
		$ilCtrl->saveParameter($this, "item_id");
		
		include_once ("./Modules/MediaCast/classes/class.ilMediaCastSettings.php");
		$settings = ilMediaCastSettings::_getInstance();
		$this->purposeSuffixes = $settings->getPurposeSuffixes();
		$this->mimeTypes = array();
		$mime_types = $settings->getMimeTypes();
		foreach ($mime_types as $mt)
		{
			$this->mimeTypes[$mt] = $mt;
		}
		
		include_once("./Services/Utilities/classes/class.ilMimeTypeUtil.php");
		foreach (ilMimeTypeUtil::getExt2MimeMap() as $mt)
		{
			$this->mimeTypes[$mt] = $mt;
		}
		asort($this->mimeTypes);
	}
	
	function executeCommand()
	{
  		global $ilUser, $ilTabs;
  
		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		$this->prepareOutput();
  
  		switch($next_class)
		{
			case "ilinfoscreengui":
				$this->checkPermission("visible");
				$this->infoScreen();	// forwards command
				break;

			case "ilexportgui":
//				$this->prepareOutput();
				$ilTabs->activateTab("export");
				include_once("./Services/Export/classes/class.ilExportGUI.php");
				$exp_gui = new ilExportGUI($this);
				$exp_gui->addFormat("xml");
				$ret = $this->ctrl->forwardCommand($exp_gui);
//				$this->tpl->show();
				break;

			case 'ilpermissiongui':
				$ilTabs->activateTab("id_permissions");
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui =& new ilPermissionGUI($this);
				$ret =& $this->ctrl->forwardCommand($perm_gui);
				break;
			
			case "ilcommonactiondispatchergui":
				include_once("Services/Object/classes/class.ilCommonActionDispatcherGUI.php");
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;
		
			default:
				if(!$cmd)
				{
					$cmd = "infoScreen";
				}
				$cmd .= "Object";
				if ($cmd != "infoScreenObject")
				{
					$this->checkPermission("read");
				}
				else
				{
					$this->checkPermission("visible");
				}
				$this->$cmd();
	
			break;
		}
		
		$this->addHeaderAction();
  
  		return true;
	}

	protected function initCreationForms($a_new_type)
	{
		$forms = array(self::CFORM_NEW => $this->initCreateForm($a_new_type),
			self::CFORM_IMPORT => $this->initImportForm($a_new_type));

		return $forms;
	}

	/**
	 * save object
	 * @access	public
	 */
	function afterSave($newObj)
	{
		// always send a message
		ilUtil::sendSuccess($this->lng->txt("object_added"),true);
		ilUtil::redirect("ilias.php?baseClass=ilMediaCastHandlerGUI&ref_id=".$newObj->getRefId()."&cmd=editSettings");
	}

	/**
	* List items of media cast.
	*/
	function listItemsObject($a_presentation_mode = false)
	{
		global $tpl, $lng, $ilAccess, $ilTabs, $ilToolbar;
		
		$this->checkPermission("read");
		
		if ($a_presentation_mode)
		{
			$this->addContentSubTabs("content");
		}
		else
		{
			$this->addContentSubTabs("manage");
		}
		
		$med_items = $this->object->getSortedItemsArray();

		include_once("./Modules/MediaCast/classes/class.ilMediaCastTableGUI.php");
		if ($a_presentation_mode)
		{
			$table_gui = new ilMediaCastTableGUI($this, "showContent", false, true);
		}
		else
		{
			$table_gui = new ilMediaCastTableGUI($this, "listItems");
		}

		$table_gui->setData($med_items);
		
		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]) && !$a_presentation_mode)
		{			
			$ilToolbar->addButton($lng->txt("add"), $this->ctrl->getLinkTarget($this, "addCastItem"));
			
			$table_gui->addMultiCommand("confirmDeletionItems", $lng->txt("delete"));
			$table_gui->setSelectAllCheckbox("item_id");
		}

		$feed_icon_html = $this->getFeedIconsHTML();
		if ($feed_icon_html != "")
		{
			$table_gui->setHeaderHTML($feed_icon_html);
		}
		
		$tpl->setContent($table_gui->getHTML());

		$tpl->setPermanentLink($this->object->getType(), $this->object->getRefId());
	}
	
	/**
	 * Get feed icons HTML
	 *
	 * @param
	 * @return
	 */
	function getFeedIconsHTML()
	{
		global $lng;
		
		$html = "";
		
		include_once("./Services/Block/classes/class.ilBlockSetting.php");
		$public_feed = ilBlockSetting::_lookup("news", "public_feed",
			0, $this->object->getId());
			
		// rss icon/link
		if ($public_feed)
		{
			$news_set = new ilSetting("news");
			$enable_internal_rss = $news_set->get("enable_rss_for_internal");

			if ($enable_internal_rss)
			{
    			// create dummy object in db (we need an id)
			    $items = $this->object->getItemsArray();
    			include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
    			$html = "";
			    foreach (ilObjMediaCast::$purposes as $purpose) 			        
			    {
			        foreach ($items as  $id => $item)
    				{
    			        $mob = new ilObjMediaObject($item["mob_id"]);
    			        $mob->read();
    				    if ($mob->hasPurposeItem($purpose))
        				{        			        
        				    if ($html == "")
        				    {
								$html = " ";
        				    }
        				    $url = ILIAS_HTTP_PATH."/feed.php?client_id=".rawurlencode(CLIENT_ID)."&"."ref_id=".$_GET["ref_id"]."&purpose=$purpose";
        				    $title = $lng->txt("news_feed_url");
        				    $icon = ilUtil::getImagePath("rss_icon_".strtolower($purpose).".png");
        				    $target = "_blank";

							$row1 .= "<A href='$url' target='$target'><img src='$icon' alt='$title'/></A>";
            				if ($this->object->getPublicFiles())
            				{
            				    $url = preg_replace("/https?/i","itpc",$url);
            				    $title = $lng->txt("news_feed_url");
            				    $icon = ilUtil::getImagePath("itunes_icon.png");
								$row2 .= "<A href='$url' target='$target'><img src='$icon' alt='$title'/></A>";
            				}
            				break;
        				}        				
        				
    				}
			    }
			    if ($html != "")
			    {
					$html .= $row1;
				    if ($row2 != "")
					{
						$html .= "&nbsp;&nbsp;".$row2;
					}
				}
			}
		}
		return $html;
	}
	
	
	/**
	* Add media cast item
	*/
	function addCastItemObject()
	{
		global $tpl;
		
		$this->checkPermission("write");
		
		$this->initAddCastItemForm();
		$tpl->setContent($this->form_gui->getHTML());
	}

	/**
	* Edit media cast item
	*/
	function editCastItemObject()
	{
		global $tpl, $ilToolbar, $ilCtrl;
		
		$this->checkPermission("write");
		
		// conversion toolbar
		include_once("./Services/MediaObjects/classes/class.ilFFmpeg.php");
		if (ilFFmpeg::enabled())
		{
			$this->mcst_item = new ilNewsItem($_GET["item_id"]);
			include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
			$mob = new ilObjMediaObject($this->mcst_item->getMobId());

			$conv_cnt = 0;
			// we had other purposes as source as well, but
			// currently only "Standard" is implemented in the convertFile method
		    foreach (array("Standard") as $p)
		    {
		    	/*
		    	$med = $mob->getMediaItem($p);
		    	if (is_object($med))
		    	{
					$options = ilFFmpeg::getPossibleTargetMimeTypes($med->getFormat());
					if (count($options) > 0)
					{
						if ($conv_cnt > 0)
						{
							$ilToolbar->addSeparator();
						}
						
						include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
						$si = new ilSelectInputGUI($this->lng->txt("mcst_conv_".
							strtolower($p)."_to"), "target_format");
						$si->setOptions($options);
						$ilToolbar->addInputItem($si, true);
						
						$si = new ilSelectInputGUI(", ".$this->lng->txt("mcst_target").": ",
							"target_purpose");
						$si->setOptions(array("Standard" => $this->lng->txt("mcst_purpose_standard"),
							"VideoAlternative" => $this->lng->txt("mcst_purpose_videoalternative")
							));
						$si->setValue($p);
						$ilToolbar->addInputItem($si, true);
						
						$ilToolbar->addFormButton($this->lng->txt("mcst_convert"), "convertFile");
						
						$conv_cnt++;
					}
				}
				*/
				
		    	$med = $mob->getMediaItem($p);
		    	if (is_object($med))
		    	{
					if (ilFFmpeg::supportsImageExtraction($med->getFormat()))
					{
						// second
						include_once("./Services/Form/classes/class.ilTextInputGUI.php");
						$ni = new ilTextInputGUI($this->lng->txt("mcst_second"), "sec");
						$ni->setMaxLength(4);
						$ni->setSize(4);
						$ni->setValue(1);
						$ilToolbar->addInputItem($ni, true);
						
						$ilToolbar->addFormButton($this->lng->txt("mcst_extract_preview_image"), "extractPreviewImage");
						$ilToolbar->setFormAction($ilCtrl->getFormAction($this));
					}
				}
				
				/*if ($conv_cnt > 0)
				{
					$ilToolbar->setFormAction($ilCtrl->getFormAction($this));
				}*/
		    }
		}
		
		$this->initAddCastItemForm("edit");
		$this->getCastItemValues();
		$tpl->setContent($this->form_gui->getHTML());
	}
	
	/**
	* Init add cast item form.
	*/
	function initAddCastItemForm($a_mode = "create")
	{
		global $lng, $ilCtrl, $ilTabs;
		
		$this->checkPermission("write");
		$ilTabs->activateTab("edit_content");
		
		$lng->loadLanguageModule("mcst");
		
		$news_set = new ilSetting("news");
		$enable_internal_rss = $news_set->get("enable_rss_for_internal");

		include("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form_gui = new ilPropertyFormGUI();
		$this->form_gui->setMultipart(true);
		
		// Property Title
		$text_input = new ilTextInputGUI($lng->txt("title"), "title");
		$text_input->setMaxLength(200);
		$this->form_gui->addItem($text_input);
		
		// Property Content
		$text_area = new ilTextAreaInputGUI($lng->txt("description"), "description");
		$text_area->setRequired(false);
		$this->form_gui->addItem($text_area);
		
		// Property Visibility
		if ($enable_internal_rss)
		{
			$radio_group = new ilRadioGroupInputGUI($lng->txt("access_scope"), "visibility");
			$radio_option = new ilRadioOption($lng->txt("access_users"), "users");
			$radio_group->addOption($radio_option);
			$radio_option = new ilRadioOption($lng->txt("access_public"), "public");
			$radio_group->addOption($radio_option);
			$radio_group->setInfo($lng->txt("mcst_visibility_info"));
			$radio_group->setRequired(true);
			$radio_group->setValue($this->object->getDefaultAccess() == 0 ? "users" : "public");
			$this->form_gui->addItem($radio_group);
		}
		
		// Duration
		$dur = new ilDurationInputGUI($lng->txt("mcst_duration"), "duration");
		$dur->setInfo($lng->txt("mcst_duration_info"));
		$dur->setShowDays(false);
		$dur->setShowHours(true);
		$dur->setShowSeconds(true);
		$this->form_gui->addItem($dur);
		
		foreach (ilObjMediaCast::$purposes as $purpose)
		{
			if ($purpose == "VideoAlternative" &&
				$a_mode == "create")
			{
				continue;
			}
			
    		$section = new ilFormSectionHeaderGUI();    		
    		$section->setTitle($lng->txt("mcst_".strtolower($purpose)."_title"));
    		$this->form_gui->addItem($section);
    		if ($a_mode != "create")
    		{
    		    $value = new ilHiddenInputGUI("value_".$purpose);
    		    $label = new ilNonEditableValueGUI($lng->txt("value"));
    		    $label->setPostVar("label_value_".$purpose);	
    		    $label->setInfo($lng->txt("mcst_current_value_info"));
    		    $this->form_gui->addItem($label);
    		    $this->form_gui->addItem($value);

    		}
    		$file = new ilFileInputGUI($lng->txt("file"), "file_".$purpose);		
    		$file->setSuffixes($this->purposeSuffixes[$purpose]);
    		$this->form_gui->addItem($file);
    		$text_input = new ilRegExpInputGUI($lng->txt("url"), "url_".$purpose);
    		$text_input->setPattern("/https?\:\/\/.+/i");
    		$text_input->setInfo($lng->txt("mcst_reference_info"));
    		$this->form_gui->addItem($text_input);
    		if ($purpose != "Standard")
    		{
        		$clearCheckBox = new ilCheckboxInputGUI();
        		$clearCheckBox->setPostVar("delete_".$purpose);
        		$clearCheckBox->setTitle($lng->txt("mcst_clear_purpose_title"));
        		$this->form_gui->addItem($clearCheckBox);
    		} else {
    			
    			// mime type selection
    			$mimeTypeSelection = new ilSelectInputGUI();
    			$mimeTypeSelection->setPostVar("mimetype_".$purpose);
    			$mimeTypeSelection->setTitle($lng->txt("mcst_mimetype"));
    			$mimeTypeSelection->setInfo($lng->txt("mcst_mimetype_info")); 
    			$options = array("" => $lng->txt("mcst_automatic_detection"));
    			$options = array_merge($options, $this->mimeTypes);
    			$mimeTypeSelection->setOptions($options);    			
    			$this->form_gui->addItem($mimeTypeSelection);
    			
    			// preview picure
    			$pp = new ilImageFileInputGUI($lng->txt("mcst_preview_picture"), "preview_pic");
    			$pp->setSuffixes(array("png", "jpeg", "jpg"));
    			$pp->setInfo($lng->txt("mcst_preview_picture_info")." mp4, mp3, png, jp(e)g, gif");
    			$this->form_gui->addItem($pp);
    			
    		}
    		
		}
		
		// save/cancel button
		if ($a_mode == "create")
		{
		    $this->form_gui->setTitle($lng->txt("mcst_add_new_item"));		    
		    $this->form_gui->addCommandButton("saveCastItem", $lng->txt("save"));
		}
		else
		{
		    $this->form_gui->setTitle($lng->txt("mcst_edit_item"));		    
			$this->form_gui->addCommandButton("updateCastItem", $lng->txt("save"));
		}
		$this->form_gui->addCommandButton("listItems", $lng->txt("cancel"));	
		$this->form_gui->setFormAction($ilCtrl->getFormAction($this, "saveCastItem"));
		
	}
	
	/**
	* Get cast item values into form.
	*/
	public function getCastItemValues()
	{
		global $lng;
		
		// get mob
		$this->mcst_item = new ilNewsItem($_GET["item_id"]);
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
		$mob = new ilObjMediaObject($this->mcst_item->getMobId());
		
		// preview
		$ppic = $mob->getVideoPreviewPic();
		if ($ppic != "")
		{
			$i = $this->form_gui->getItemByPostVar("preview_pic");
			$i->setImage($ppic);
		}
		
		
	    $values = array();
		$mediaItems = $this->getMediaItems($_GET["item_id"]);
		if (count ($mediaItems) > 0)
		{
		    foreach ($mediaItems as $med) 
		    {
		        if (!isset ($values["title"]))
		        {
		            // first item, so set title, description, ...
		            $values["title"] = $this->mcst_item->getTitle();
		            $values["description"] = $this->mcst_item->getContent();
		            $values["visibility"] = $this->mcst_item->getVisibility();
		            $length = explode(":", $this->mcst_item->getPlaytime());
		            $values["duration"] = array("hh" => $length[0], "mm" => $length[1], "ss" => $length[2]);		            		           
		        }
		        
		        $values["value_".$med->getPurpose()] = (strlen($med->getLocation())> 100) ? "...".substr($med->getLocation(), strlen($med->getLocation()) - 100) : $med->getLocation(); 		        
		        $values["label_value_".$med->getPurpose()] = (strlen($med->getLocation())> 100) ? "...".substr($med->getLocation(), strlen($med->getLocation()) - 100) : $med->getLocation();
	        	$values["mimetype_".$med->getPurpose()] = $med->getFormat();
		    }
		}
		foreach (ilObjMediaCast::$purposes as $purpose) {
		    if (!isset ($values["value_".$purpose]))
		    {
		        $values["label_value_".$purpose] = $lng->txt("none");
		        $values["value_".$purpose] = $lng->txt("none");
		    }
		}
		$this->form_gui->setValuesByArray($values);
	}
	
	/**
	* Save new cast item
	*/
	function saveCastItemObject()
	{
		global $tpl, $ilCtrl, $ilUser, $lng, $ilTabs;

		$this->checkPermission("write");
		$ilTabs->activateTab("edit_content");
		
		$this->initAddCastItemForm();
		
		if ($_POST["url_Standard"] == "" && !$_FILES['file_Standard']['tmp_name']) {
			ilUtil::sendFailure($lng->txt("msg_input_either_file_or_url"));
			$this->populateFormFromPost();
		}
		else if ($this->form_gui->checkInput())
		{
			// create dummy object in db (we need an id)
			include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
			$mob = new ilObjMediaObject();
			$mob->create();

			//handle standard purpose
			$file = $this->createMediaItemForPurpose($mob, "Standard");						

			// set title and description
			// set title to basename of file if left empty
			$title = $this->form_gui->getInput("title") != "" ? $this->form_gui->getInput("title") : basename($file);
			$description = $this->form_gui->getInput("description");
			$mob->setTitle($title);
			$mob->setDescription($description);

			// save preview pic
			$prevpic = $this->form_gui->getInput("preview_pic");
			if ($prevpic["size"] > 0)
			{
				$mob->uploadVideoPreviewPic($prevpic);
			}
			
			// determine duration for standard purpose			
			$duration = $this->getDuration($file);						
			
			// handle other purposes
			foreach ($this->additionalPurposes as $purpose) 
			{
			    // check if some purpose has been uploaded
			    $file_gui = $this->form_gui->getInput("file_".$purpose);
			    $url_gui = $this->form_gui->getInput("url_".$purpose);
			    if ($url_gui || $file_gui["size"] > 0) 
			    {
			        $this->createMediaItemForPurpose ($mob, $purpose);
			    }
			}

			$mob->update();

			if ($prevpic["size"] == 0)
			{
				// re-read media object
				$mob = new ilObjMediaObject($mob->getId());
	        	$mob->generatePreviewPic(320, 240);
			}
			
			//
			// @todo: save usage
			//
			
			$news_set = new ilSetting("news");
			$enable_internal_rss = $news_set->get("enable_rss_for_internal");
			
			// create new media cast item
			include_once("./Services/News/classes/class.ilNewsItem.php");
			$mc_item = new ilNewsItem();
			$mc_item->setMobId($mob->getId());
			$mc_item->setContentType(NEWS_AUDIO);
			$mc_item->setContextObjId($this->object->getId());
			$mc_item->setContextObjType($this->object->getType());
			$mc_item->setUserId($ilUser->getId());
			$mc_item->setPlaytime($duration);
			$mc_item->setTitle($title);
			$mc_item->setContent($description);
			$mc_item->setLimitation(false);
			if ($enable_internal_rss)
			{
				$mc_item->setVisibility($this->form_gui->getInput("visibility"));
			}
			else
			{
				$mc_item->setVisibility("users");
			}
			$mc_item->create();
			
			$ilCtrl->redirect($this, "listItems");
		}
		else
		{
			$this->populateFormFromPost();
		}
	}
	
	/**
	 * get duration from form or from file analyzer 
	 *
	 * @param unknown_type $file
	 * @return unknown
	 */
	private function getDuration($file)
	{
	    $duration = isset($this->form_gui) ? $this->form_gui->getInput("duration") : "";
	    if ($duration["hh"] == 0 && $duration["mm"] == 0 && $duration["ss"] == 0 && is_file($file))
	    {
	        include_once("./Services/MediaObjects/classes/class.ilMediaAnalyzer.php");
	        $ana = new ilMediaAnalyzer();
	        $ana->setFile($file);
	        $ana->analyzeFile();
	        $dur = $ana->getPlaytimeString();
	        $dur = explode(":", $dur);
	        $duration["mm"] = $dur[0];
	        $duration["ss"] = $dur[1];
	    }
	    $duration = str_pad($duration["hh"], 2 , "0", STR_PAD_LEFT).":".
	                str_pad($duration["mm"], 2 , "0", STR_PAD_LEFT).":".
	                str_pad($duration["ss"], 2 , "0", STR_PAD_LEFT);
	    return $duration;
	}
	
	/**
	 * handle media item for given purpose
	 *
	 * @param ilMediaObject $mob
	 * @param string file
	 */
	private function createMediaItemForPurpose ($mob, $purpose) 	   
	{
	    $mediaItem = new ilMediaItem();
		$mob->addMediaItem($mediaItem);
		$mediaItem->setPurpose($purpose);		
		return $this->updateMediaItem($mob, $mediaItem);
	}
	
	/**
	 * update media item from form
	 *
	 * @param IlObjectMediaObject $mob
	 * @param IlMediaItem $mediaItem
	 * @return string file
	 */
	private function updateMediaItem ($mob, & $mediaItem)
	{
	    $purpose = $mediaItem->getPurpose();
	    $url_gui = $this->form_gui->getInput ("url_".$purpose);
	    $file_gui = $this->form_gui->getInput ("file_".$purpose);
	    if ($url_gui)
	    {
	        // http
	        $file = $this->form_gui->getInput ("url_".$purpose);
	        $title = basename ($file);
	        $location = $this->form_gui->getInput ("url_".$purpose);
	        $locationType = "Reference";
	    } elseif ($file_gui["size"] > 0){
	        // lokal
	        // determine and create mob directory, move uploaded file to directory
	        $mob_dir = ilObjMediaObject::_getDirectory($mob->getId());
	        if (!is_dir($mob_dir))
	            $mob->createDirectory();
	        
	        $file_name = ilUtil::getASCIIFilename($_FILES['file_'.$purpose]['name']);
	        $file_name = str_replace(" ", "_", $file_name);

	        $file = $mob_dir."/".$file_name;
	        $title = $file_name;
	        $locationType = "LocalFile";
	        $location = $title;
	        ilUtil::moveUploadedFile($_FILES['file_'.$purpose]['tmp_name'], $file_name, $file);
	        ilUtil::renameExecutables($mob_dir);
	        
	    }
	    
	    // check if not automatic mimetype detection
	    if ($_POST["mimetype_".$purpose] != "")
	    {
        	$mediaItem->setFormat($_POST["mimetype_".$purpose]);
	    }
	    elseif ($mediaItem->getLocation () != "")
	    {
	    	$format = ilObjMediaObject::getMimeType($mediaItem->getLocation());
	    	$mediaItem->setFormat($format);
	    }	    
	    
	    if (isset($file))
	    {
	        // get mime type, if not already set!
	        if (!isset($format))
	        {
	        	$format = ilObjMediaObject::getMimeType($file);
	        }

	        // set real meta and object data
	        $mediaItem->setFormat($format);
	        $mediaItem->setLocation($location);
	        $mediaItem->setLocationType($locationType);
	        $mediaItem->setHAlign("Left");
	        $mediaItem->setHeight(self::isAudio($format)?0:180);	        
	    } 
	        	    
	    if ($purpose == "Standard")
	    {
	        if (isset($title))
	            $mob->setTitle ($title);
	        if (isset($format))
	            $mob->setDescription($format);
	    }

	    return $file;
	}
	
	/**
	* Update cast item
	*/
	function updateCastItemObject()
	{
		global $tpl, $lng, $ilCtrl, $ilUser, $log;
		
		$this->checkPermission("write");
		
		$this->initAddCastItemForm("edit");

		if ($this->form_gui->checkInput())
		{
			// create new media cast item
			include_once("./Services/News/classes/class.ilNewsItem.php");
			$mc_item = new ilNewsItem($_GET["item_id"]);
			$mob_id = $mc_item->getMobId();
			
			// create dummy object in db (we need an id)
			include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
			$mob = new ilObjMediaObject($mob_id);


			foreach (ilObjMediaCast::$purposes as $purpose)
			{
			    if ($this->form_gui->getInput("delete_".$purpose)) 
			    {
			        $mob->removeMediaItem($purpose);
			        $log->write ("Mcst: deleting purpose $purpose");
			        continue;
			    }
			    $media_item = $mob->getMediaItem($purpose);			    
			    $url_gui = $this->form_gui->getInput("url_".$purpose);
			    $file_gui = $this->form_gui->getInput("file_".$purpose);
			    
			    if ($media_item == null)
			    {
    			    if ($purpose != "Standard" && 
    			       ($url_gui || $file_gui["size"]>0)) 
    			    {
    			        // check if we added an additional purpose when updating
    			        // either by url or by file
    			        $file = $this->createMediaItemForPurpose($mob, $purpose);
    			    }
			    } else
			    {			        
  			        $file = $this->updateMediaItem($mob, $media_item);  			        
			    }
			    
			    if ($purpose == "Standard")
    			{
    			    $duration = $this->getDuration($file);
    			    $title = $this->form_gui->getInput("title") != "" ? $this->form_gui->getInput("title") : basename($file);
  			        $description = $this->form_gui->getInput("description"); 
			
  			        $mob->setTitle($title);
  			        $mob->setDescription($description);
  			        
  			        $prevpic = $this->form_gui->getInput("preview_pic");
  			        if ($prevpic["size"] > 0)
  			        {
  			        	$mob->uploadVideoPreviewPic($prevpic);
  			        }
  			        else
					{
						$prevpici = $this->form_gui->getItemByPostVar("preview_pic");
						if ($prevpici->getDeletionFlag())
						{
							$mob->removeAdditionalFile($mob->getVideoPreviewPic(true));
						}
					}
    			}			    
			}
			
			// set real meta and object data
			$mob->update();
			
			//
			// @todo: save usage
			//
			
			$news_set = new ilSetting("news");
			$enable_internal_rss = $news_set->get("enable_rss_for_internal");

			$mc_item->setUserId($ilUser->getId());
			if (isset($duration))
			{
			    $mc_item->setPlaytime($duration);
		    }
			$mc_item->setTitle($title);
			$mc_item->setContent($description);
			if ($enable_internal_rss)
			{
				$mc_item->setVisibility($this->form_gui->getInput("visibility"));
			}
			$mc_item->update();

			$ilCtrl->redirect($this, "listItems");
		}
		else
		{
		    $this->populateFormFromPost();
		}
	}

	/**
	* Confirmation Screen.
	*/
	function confirmDeletionItemsObject()
	{
		global $ilCtrl, $lng, $tpl, $ilTabs;
		
		$this->checkPermission("write");
		$ilTabs->activateTab("edit_content");
		
		if (!is_array($_POST["item_id"]))
		{
			$this->listItemsObject();
			return;
		}
		
		include_once("Services/Utilities/classes/class.ilConfirmationGUI.php");
		$c_gui = new ilConfirmationGUI();
		
		// set confirm/cancel commands
		$c_gui->setFormAction($ilCtrl->getFormAction($this, "deleteItems"));
		$c_gui->setHeaderText($lng->txt("info_delete_sure"));
		$c_gui->setCancel($lng->txt("cancel"), "listItems");
		$c_gui->setConfirm($lng->txt("confirm"), "deleteItems");

		// add items to delete
		include_once("./Services/News/classes/class.ilNewsItem.php");
		foreach($_POST["item_id"] as $item_id)
		{
			$item = new ilNewsItem($item_id);
			$c_gui->addItem("item_id[]", $item_id, $item->getTitle(),
				ilUtil::getImagePath("icon_mcst.png"));
		}
		
		$tpl->setContent($c_gui->getHTML());
	}

	/**
	* Delete news items.
	*/
	function deleteItemsObject()
	{
		global $ilCtrl;
		
		$this->checkPermission("write");
		
		// delete all selected news items
		foreach($_POST["item_id"] as $item_id)
		{
			$mc_item = new ilNewsItem($item_id);
			$mc_item->delete();
		}
		
		$ilCtrl->redirect($this, "listItems");
	}
	
	/**
	 * Download news media item
	 */
	function downloadItemObject($a_increase_download_cnt)
	{
		global $ilCtrl;
		$this->checkPermission("read");		
		
		$news_item = new ilNewsItem($_GET["item_id"]);
		if (!$news_item->deliverMobFile($_GET["purpose"], (int) $_GET["presentation"]))
		{
			$ilCtrl->redirect($this, "listItems");
		}
		exit;
	}
	
	/**
	* Delete news items.
	*/
	function determinePlaytimeObject()
	{
		global $ilCtrl, $lng;
		
		$mc_item = new ilNewsItem($_GET["item_id"]);
		$mob = $mc_item->getMobId();
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObject.php");
		$mob = new ilObjMediaObject($mob);
		$mob_dir = ilObjMediaObject::_getDirectory($mob->getId());
		$m_item = $mob->getMediaItem("Standard");
		$file = $mob_dir."/".$m_item->getLocation();
		$duration = $this->getDuration($file);
		if ($duration != "00:00:00")
		{
			$mc_item->setPlaytime($duration);
			$mc_item->update();
			ilUtil::sendSuccess($lng->txt("mcst_set_playtime"), true);
		}
		else
		{
			ilUtil::sendFailure($lng->txt("mcst_unable_to_determin_playtime"), true);
		}

		$ilCtrl->redirect($this, "listItems");
	}

	/**
	* this one is called from the info button in the repository
	* not very nice to set cmdClass/Cmd manually, if everything
	* works through ilCtrl in the future this may be changed
	*/
	function infoScreenObject()
	{
		$this->checkPermission("visible");
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen();
	}

	/**
	* show information screen
	*/
	function infoScreen()
	{
		global $ilAccess, $ilUser, $ilTabs;
		
		$ilTabs->activateTab("id_info");

		if (!$ilAccess->checkAccess("visible", "", $this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_read"),$this->ilias->error_obj->MESSAGE);
		}

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);
		
		$info->enablePrivateNotes();
		
		/*
		$info->enableNews();
		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]))
		{
			//$info->enableNewsEditing();
			$info->setBlockProperty("news", "settings", true);
		}*/
		
		// general information
		$this->lng->loadLanguageModule("meta");
		$this->lng->loadLanguageModule("mcst");
		$med_items = $this->object->getItemsArray();
		$info->addSection($this->lng->txt("meta_general"));
		$info->addProperty($this->lng->txt("mcst_nr_items"),
			(int) count($med_items));
			
		if (count($med_items) > 0)
		{
			$cur = current($med_items);
			$last = ilDatePresentation::formatDate(new ilDateTime($cur["creation_date"], IL_CAL_DATETIME));
		}
		else
		{
			$last = "-";
		}

		$info->addProperty($this->lng->txt("mcst_last_submission"), $last);

		// forward the command
		$this->ctrl->forwardCommand($info);
	}

	/**
	* get tabs
	* @access	public
	*/
	function setTabs()
	{
		global $ilAccess, $ilTabs, $lng, $ilHelp;
		
		$ilHelp->setScreenIdComponent("mcst");
		
		// list items
		if ($ilAccess->checkAccess('read', "", $this->object->getRefId()))
		{
			$ilTabs->addTab("content",
				$lng->txt("content"),
				$this->ctrl->getLinkTarget($this, "showContent"));
		}

		// info screen
		if ($ilAccess->checkAccess('visible', "", $this->object->getRefId()))
		{
			$ilTabs->addTab("id_info",
				$lng->txt("info_short"),
				$this->ctrl->getLinkTargetByClass("ilinfoscreengui", "showSummary"));
		}

		// settings
		if ($ilAccess->checkAccess('write', "", $this->object->getRefId()))
		{
			$ilTabs->addTab("id_settings",
				$lng->txt("settings"),
				$this->ctrl->getLinkTarget($this, "editSettings"));
		}

		// export
		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
			$ilTabs->addTab("export",
				$lng->txt("export"),
				$this->ctrl->getLinkTargetByClass("ilexportgui", ""));
		}

		// edit permissions
		if ($ilAccess->checkAccess('edit_permission', "", $this->object->getRefId()))
		{
			$ilTabs->addTab("id_permissions",
				$lng->txt("perm_settings"),
				$this->ctrl->getLinkTargetByClass("ilpermissiongui", "perm"));
		}
	}

	/**
	 * Add content subtabs
	 *
	 * @param
	 * @return
	 */
	function addContentSubTabs($a_active = "content")
	{
		global $ilTabs, $ilAccess, $lng;
		
		$ilTabs->addSubTab("content",
			$lng->txt("view"),
			$this->ctrl->getLinkTarget($this, "showContent"));

		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]))
		{
			$ilTabs->addSubTab("manage",
				$lng->txt("mcst_manage"),
				$this->ctrl->getLinkTarget($this, "listItems"));
			
			if ($this->object->getOrder() == ilObjMediaCast::ORDER_MANUAL)
			{
				$ilTabs->addSubTab("sorting",
					$lng->txt("mcst_ordering"),
					$this->ctrl->getLinkTarget($this, "editOrder"));
			}
		}
		
		$ilTabs->activateSubTab($a_active);
		$ilTabs->activateTab("content");
	}
	
	
	/**
	* Edit settings
	*/
	function editSettingsObject()
	{
		global $tpl, $ilTabs;
		
		$this->checkPermission("write");
		$ilTabs->activateTab("id_settings");
		
		$this->initSettingsForm();
		$tpl->setContent($this->form_gui->getHtml());
	}
	
	/**
	* Init Settings Form
	*/
	function initSettingsForm()
	{
		global $tpl, $lng, $ilCtrl;
		
		$lng->loadLanguageModule("mcst");
		
		include("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form_gui = new ilPropertyFormGUI();
		$this->form_gui->setTitle($lng->txt("mcst_settings"));
		
		// Title
		$tit = new ilTextInputGUI($lng->txt("title"), "title");
		$tit->setValue($this->object->getTitle());
		$tit->setRequired(true);
		$this->form_gui->addItem($tit);

		// description
		$des = new ilTextAreaInputGUI($lng->txt("description"), "description");
		$des->setValue($this->object->getLongDescription());
		$this->form_gui->addItem($des);

		// Online
		$online = new ilCheckboxInputGUI($lng->txt("online"), "online");
		$online->setChecked($this->object->getOnline());
		$this->form_gui->addItem($online);
		
		// Sorting
		$sort = new ilRadioGroupInputGUI($lng->txt("mcst_ordering"), "order");
		$sort->addOption(new ilRadioOption($lng->txt("mcst_ordering_title"), 
			ilObjMediaCast::ORDER_TITLE));
		$sort->addOption(new ilRadioOption($lng->txt("mcst_ordering_creation_date_asc"), 
			ilObjMediaCast::ORDER_CREATION_DATE_ASC));
		$sort->addOption(new ilRadioOption($lng->txt("mcst_ordering_creation_date_desc"),
			ilObjMediaCast::ORDER_CREATION_DATE_DESC));
		$sort->addOption(new ilRadioOption($lng->txt("mcst_ordering_manual"), 
			ilObjMediaCast::ORDER_MANUAL));		
		$sort->setValue($this->object->getOrder());
		$this->form_gui->addItem($sort);
		
		// view mode
		$options = array(
			ilObjMediaCast::VIEW_LIST => $lng->txt("mcst_list"),
			ilObjMediaCast::VIEW_GALLERY => $lng->txt("mcst_gallery")
			);
		$si = new ilSelectInputGUI($this->lng->txt("mcst_viewmode"), "viewmode");
		$si->setOptions($options);
		$si->setValue($this->object->getViewMode());
		$this->form_gui->addItem($si);
		
		
		// Downloadable
		$downloadable = new ilCheckboxInputGUI($lng->txt("mcst_downloadable"), "downloadable");
		$downloadable->setChecked($this->object->getDownloadable());
		$downloadable->setInfo($lng->txt("mcst_downloadable_info"));
		$this->form_gui->addItem($downloadable);
		
		$news_set = new ilSetting("news");
		$enable_internal_rss = $news_set->get("enable_rss_for_internal");

		//Default Visibility
		if ($enable_internal_rss)
		{
			$radio_group = new ilRadioGroupInputGUI($lng->txt("news_default_visibility"), "defaultaccess");
			$radio_option = new ilRadioOption($lng->txt("news_visibility_users"), "0");
			$radio_group->addOption($radio_option);					
			$radio_option = new ilRadioOption($lng->txt("news_visibility_public"), "1");
			$radio_group->addOption($radio_option);
			$radio_group->setInfo($lng->txt("news_news_item_visibility_info"));
			$radio_group->setRequired(false);			
			$radio_group->setValue($this->object->getDefaultAccess());			
			#$ch->addSubItem($radio_group);
			$this->form_gui->addItem($radio_group);
		
			//Extra Feed
			include_once("./Services/Block/classes/class.ilBlockSetting.php");
			$public_feed = ilBlockSetting::_lookup("news", "public_feed", 0, $this->object->getId());
			$ch = new ilCheckboxInputGUI($lng->txt("news_public_feed"), "extra_feed");
			$ch->setInfo($lng->txt("news_public_feed_info"));
			$ch->setChecked($public_feed);
			$this->form_gui->addItem($ch);
			
				// keep minimal x number of items
				$ni = new ilNumberInputGUI($this->lng->txt("news_keep_minimal_x_items"), "keep_rss_min");
				$ni->setMaxValue(100);
				$ni->setMinValue(0);
				$ni->setMaxLength(3);
				$ni->setSize(3);
				$ni->setInfo($this->lng->txt("news_keep_minimal_x_items_info")." (".
					ilNewsItem::_lookupRSSPeriod()." ".(ilNewsItem::_lookupRSSPeriod() == 1 ? $lng->txt("day") : $lng->txt("days")).")");
				$ni->setValue((int) ilBlockSetting::_lookup("news", "keep_rss_min", 0, $this->object->getId()));
				$ch->addSubItem($ni);
			
			// Include Files in Pubic Items
			$incl_files = new ilCheckboxInputGUI($lng->txt("mcst_incl_files_in_rss"), "public_files");
			$incl_files->setChecked($this->object->getPublicFiles());
			$incl_files->setInfo($lng->txt("mcst_incl_files_in_rss_info"));
			#$ch->addSubItem($incl_files);
			$this->form_gui->addItem($incl_files);
		}
		
		// Form action and save button
		$this->form_gui->addCommandButton("saveSettings", $lng->txt("save"));
		$this->form_gui->setFormAction($ilCtrl->getFormAction($this, "saveSettings"));
	}
	
	/**
	* Save Settings
	*/
	function saveSettingsObject()
	{
		global $ilCtrl, $ilTabs;
		
		$this->checkPermission("write");
		$ilTabs->activateTab("id_settings");
		
		$this->initSettingsForm();
		if ($this->form_gui->checkInput())
		{
			$news_set = new ilSetting("news");
			$enable_internal_rss = $news_set->get("enable_rss_for_internal");
			
			$this->object->setTitle($this->form_gui->getInput("title"));
			$this->object->setDescription($this->form_gui->getInput("description"));
			$this->object->setOnline($this->form_gui->getInput("online"));
			$this->object->setDownloadable($this->form_gui->getInput("downloadable"));
			$this->object->setOrder($this->form_gui->getInput("order"));
			$this->object->setViewMode($this->form_gui->getInput("viewmode"));
			
			if ($enable_internal_rss)
			{
				$this->object->setPublicFiles($this->form_gui->getInput("public_files"));
				$this->object->setDefaultAccess($this->form_gui->getInput("defaultaccess"));				
			}
			$this->object->update();
			
			if ($enable_internal_rss)
			{
				include_once("./Services/Block/classes/class.ilBlockSetting.php");
				ilBlockSetting::_write("news", "public_feed",
					$this->form_gui->getInput("extra_feed"),
					0, $this->object->getId());

				ilBlockSetting::_write("news", "keep_rss_min",
					$this->form_gui->getInput("keep_rss_min"),
					0, $this->object->getId());
			}
			
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"),true);
			$ilCtrl->redirect($this, "editSettings");
		}
		else
		{
			$this->form_gui->setValuesByPost();
			$this->tpl->setContent($this->form_gui->getHTML());
		}
	}

	// add media cast to locator
	function addLocatorItems()
	{
		global $ilLocator;
		
		if (is_object($this->object))
		{
			$ilLocator->addItem($this->object->getTitle(), $this->ctrl->getLinkTarget($this, "listItems"), "", $_GET["ref_id"]);
		}
	}

	function _goto($a_target)
	{
		global $ilAccess, $ilErr, $lng;

		if ($ilAccess->checkAccess("read", "", $a_target))
		{
			$_GET["cmd"] = "listItems";
			$_GET["ref_id"] = $a_target;
			$_GET["baseClass"] = "ilmediacasthandlergui";
			$_GET["cmdClass"] = "ilobjmediacastgui";
			include("ilias.php");
			exit;
		}
		else if ($ilAccess->checkAccess("visible", "", $a_target))
		{
			ilObjectGUI::_gotoRepositoryNode($a_target, "infoScreen");
		}
		else if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID))
		{
			ilUtil::sendFailure(sprintf($lng->txt("msg_no_perm_read_item"),
				ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))));
			ilObjectGUI::_gotoRepositoryRoot();
		}

		$ilErr->raiseError($lng->txt("msg_no_perm_read"), $ilErr->FATAL);

	}
	
	/**
	 * detect audio mimetype
	 *
	 * @param string $extension
	 * @return true, if extension contains string "audio"
	 */
	protected static function isAudio($extension) {
		return strpos($extension,"audio") !== false;
	}
	
	/**
	 * get MediaItem for id and updates local variable mcst_item
	 * 
	 * @return ilMediaItem
	 *
	 */
	protected function getMediaItem ($id) {
		include_once("./Services/News/classes/class.ilNewsItem.php");
		$this->mcst_item = new ilNewsItem($id);
		// create dummy object in db (we need an id)
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
		$mob = new ilObjMediaObject($this->mcst_item->getMobId());
		return $mob->getMediaItem("Standard");						
	}
	
/**
	 * get MediaItems for id and updates local variable mcst_item
	 * 
	 * @return array of ilMediaItem
	 *
	 */
	protected function getMediaItems ($id) {
		include_once("./Services/News/classes/class.ilNewsItem.php");
		$this->mcst_item = new ilNewsItem($id);
		// create dummy object in db (we need an id)
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
		$mob = new ilObjMediaObject($this->mcst_item->getMobId());
		return $mob->getMediaItems();						
	}
	
	private function populateFormFromPost() 
	{
	    global $tpl;
	    //issue: we have to display the current settings
	    // problem: POST does not contain values of disabled textfields
	    // solution: use hidden field and label to display-> here we need to synchronize the labels
	    // with the values from the hidden fields. 
		foreach (ilObjMediaCast::$purposes as $purpose) 
		{
		    if ($_POST["value_".$purpose])
		    {
		        $_POST["label_value_".$purpose] = $_POST["value_".$purpose]; 
		    }
		}					    
		
		$this->form_gui->setValuesByPost();
		$tpl->setContent($this->form_gui->getHTML());			    
	}
	
	protected function editOrderObject()
	{		
		global $ilTabs, $lng, $tpl;
		
		$this->checkPermission("write");
		$ilTabs->activateTab("edit_content");
		
		$this->addContentSubTabs("sorting");
		
		// sort by order setting
		switch($this->object->getOrder())
		{
			case ilObjMediaCast::ORDER_TITLE:				
			case ilObjMediaCast::ORDER_CREATION_DATE_ASC:	
			case ilObjMediaCast::ORDER_CREATION_DATE_DESC:
				return $this->listItemsObject();
			
			case ilObjMediaCast::ORDER_MANUAL:
				// sub-tabs
				break;
		}
	
		include_once("./Modules/MediaCast/classes/class.ilMediaCastTableGUI.php");
		$table_gui = new ilMediaCastTableGUI($this, "editOrder", true);
				
		$table_gui->setTitle($lng->txt("mcst_media_cast"));
		$table_gui->setData($this->object->getSortedItemsArray());
		
		$table_gui->addCommandButton("saveOrder", $lng->txt("mcst_save_order"));
		
		$tpl->setContent($table_gui->getHTML());
	}
	
	function saveOrderObject()
	{
		global $lng;
		
		asort($_POST["item_id"]);
		
		$items = array();
		foreach(array_keys($_POST["item_id"]) as $id)
		{
			$items[] = $id;
		}
		$this->object->saveOrder($items);
		
		ilUtil::sendSuccess($lng->txt("settings_saved"), true);
		$this->ctrl->redirect($this, "editOrder");
	}
	
	////
	//// Show content
	////
	
	/**
	 * Show content
	 *
	 * @param
	 * @return
	 */
	function showContentObject()
	{
		if ($this->object->getViewMode() == ilObjMediaCast::VIEW_GALLERY)
		{
			$this->showGallery();
		}
		else
		{
			$this->listItemsObject(true);
		}
	}
	
	function showGallery()
	{
		global $tpl, $ilTabs, $ilCtrl;
		
		$tpl->addJavascript("./Modules/MediaCast/js/MediaCast.js");
		
		$ilTabs->activateTab("content");
		
		$this->addContentSubTabs("content");
		
		$ctpl = new ilTemplate("tpl.mcst_content.html", true, true, "Modules/MediaCast");
		
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObject.php");
		foreach ($this->object->getSortedItemsArray() as $item)
		{
			$mob = new ilObjMediaObject($item["mob_id"]);
			$med = $mob->getMediaItem("Standard");
			
			$ctpl->setCurrentBlock("item");
			$ctpl->setVariable("TITLE", $item["title"]);
			$ctpl->setVariable("TIME", $item["playtime"]);
			$ctpl->setVariable("ID", $item["id"]);
			
			if ($mob->getVideoPreviewPic() != "")
			{
				$ctpl->setVariable("PREVIEW_PIC",
					ilUtil::img($mob->getVideoPreviewPic(), $item["title"], 320, 240));
			}
			else
			{
				$ctpl->setVariable("PREVIEW_PIC",
					ilUtil::img(ilUtil::getImagePath("mcst_preview.png"), $item["title"], 320, 240));
			}
			
			// player
			if (is_object($med))
			{
				include_once("./Services/MediaObjects/classes/class.ilMediaPlayerGUI.php");
				
				// the news id will be used as player id, see also ilMediaCastTableGUI
				$mpl = new ilMediaPlayerGUI($item["id"],
					$ilCtrl->getLinkTarget($this, "handlePlayerEvent", "", true, false));
				
				if (strcasecmp("Reference", $med->getLocationType()) == 0)
				{
					$mpl->setFile($med->getLocation());
				}
				else
				{
					$mpl->setFile(ilObjMediaObject::_getURL($mob->getId())."/".$med->getLocation());
				}
				$mpl->setMimeType ($med->getFormat());
				//$mpl->setDisplayHeight($med->getHeight());
				$mpl->setDisplayHeight("480");
				$mpl->setDisplayWidth("640");
				$mpl->setVideoPreviewPic($mob->getVideoPreviewPic());
				$mpl->setTitle($item["title"]);
				$mpl->setDescription($item["content"]);
				$mpl->setForceAudioPreview(true);
				$med_alt = $mob->getMediaItem("VideoAlternative");
				if (is_object($med_alt))
				{
					$mpl->setAlternativeVideoFile(ilObjMediaObject::_getURL($mob->getId())."/".
						$med_alt->getLocation());
					$mpl->setAlternativeVideoMimeType($med_alt->getFormat());
				}
				
				$ctpl->setVariable("PLAYER", $mpl->getPreviewHtml());
			}

			
			$ctpl->parseCurrentBlock();
		}
		
		$feed_icon_html = $this->getFeedIconsHTML();

		if ($feed_icon_html != "")
		{
			$feed_icon_html = '<p>'.$feed_icon_html.'</p>';
		}
		
		$tpl->setContent($feed_icon_html.$ctpl->get());
	}
	
	/**
	 * Convert file object
	 *
	 * @param
	 * @return
	 */
	function convertFileObject()
	{
		global $ilCtrl;
		
		$this->checkPermission("write");
		
		$this->mcst_item = new ilNewsItem($_GET["item_id"]);
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
		$mob = new ilObjMediaObject($this->mcst_item->getMobId());
		
		$target_purpose = ilUtil::stripSlashes($_POST["target_purpose"]);
		$target_format = ilUtil::stripSlashes($_POST["target_format"]);
		
		try
		{
			include_once("./Services/MediaObjects/classes/class.ilFFmpeg.php");
			$med = $mob->getMediaItem("Standard");
			$mob_file = ilObjMediaObject::_getDirectory($mob->getId())."/".$med->getLocation();
			$new_file = ilFFmpeg::convert($mob_file, $target_format);
			$ret = ilFFmpeg::getLastReturnValues();
			$pi = pathinfo($new_file);
			$med = $mob->getMediaItem($target_purpose);
			if (!is_object($med))
			{
				$med = new ilMediaItem();
				$med->setMobId($mob->getId());
				$mob->addMediaItem($med);
				$mob->update();
				$med->setPurpose($target_purpose);
			}
			$med->setFormat($target_format);
			$med->setLocation($pi["basename"]);
			$med->setLocationType("LocalFile");
			$med->update();
			
			$add = (is_array($ret) && count($ret) > 0)
				? "<br />".implode($ret, "<br />")
				: "";
			
			ilUtil::sendInfo($this->lng->txt("mcst_converted_file").$add, true);
		}
		catch (ilException $e)
		{
			$ret = ilFFmpeg::getLastReturnValues();
			$add = (is_array($ret) && count($ret) > 0)
				? "<br />".implode($ret, "<br />")
				: "";
			ilUtil::sendFailure($e->getMessage().$add, true);
		}
		
		
		$ilCtrl->redirect($this, "editCastItem");
	}
	
	/**
	 * Extract preview image
	 *
	 * @param
	 * @return
	 */
	function extractPreviewImageObject()
	{
		global $ilCtrl;
		
		$this->checkPermission("write");
		
		$this->mcst_item = new ilNewsItem($_GET["item_id"]);
		include_once("./Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php");
		$mob = new ilObjMediaObject($this->mcst_item->getMobId());
		
		try
		{
			$sec = (int) $_POST["sec"];
			if ($sec < 0)
			{
				$sec = 0;
			}
			if ($mob->getVideoPreviewPic() != "")
			{
				$mob->removeAdditionalFile($mob->getVideoPreviewPic(true));
			}
			include_once("./Services/MediaObjects/classes/class.ilFFmpeg.php");
			$med = $mob->getMediaItem("Standard");
			$mob_file = ilObjMediaObject::_getDirectory($mob->getId())."/".$med->getLocation();
			$new_file = ilFFmpeg::extractImage($mob_file, "mob_vpreview.png",
				ilObjMediaObject::_getDirectory($mob->getId()), $sec);
			
			if ($new_file != "")
			{
				ilUtil::sendInfo($this->lng->txt("mcst_image_extracted"), true);
			}
			else
			{
				ilUtil::sendFailure($this->lng->txt("mcst_no_extraction_possible"), true);
			}
		}
		catch (ilException $e)
		{
			if (DEVMODE == 1)
			{
				$ret = ilFFmpeg::getLastReturnValues();
				$add = (is_array($ret) && count($ret) > 0)
					? "<br />".implode($ret, "<br />")
					: "";
			}
			ilUtil::sendFailure($e->getMessage().$add, true);
		}
		
		
		$ilCtrl->redirect($this, "editCastItem");
	}

	/**
	 * Handle player event
	 *
	 * @param
	 * @return
	 */
	function handlePlayerEventObject()
	{
		if ($_GET["event"] == "play")
		{
			$player = explode("_", $_GET["player"]);
			$news_id = (int) $player[1];
			include_once("./Services/News/classes/class.ilNewsItem.php");
			$item = new ilNewsItem($news_id);
			$item->increasePlayCounter();
		}
		exit;
	}
	
	
}
?>