<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
require_once './Modules/Test/classes/inc.AssessmentConstants.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiQuestionScoringAdjustable.php';

/**
 * The assFileUploadGUI class encapsulates the GUI representation for file upload questions.
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 * 
 * @version	$Id$
 *
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assFileUploadGUI: ilObjQuestionPoolGUI
 * 
 */
class assFileUploadGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable
{
	/**
	 * assFileUploadGUI constructor
	 *
	 * The constructor takes possible arguments an creates an instance of the assFileUploadGUI object.
	 *
	 * @param integer $id The database id of a single choice question object
	 * 
	 */
	public function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Modules/TestQuestionPool/classes/class.assFileUpload.php";
		$this->object = new assFileUpload();
		$this->setErrorMessage($this->lng->txt("msg_form_save_error"));
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	/**
	 * Evaluates a posted edit form and writes the form data in the question object
	 *
	 * @param bool $always
	 *
	 * @return integer A positive value, if one of the required fields wasn't set, else 0
	 */
	public function writePostData($always = false)
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if (!$hasErrors)
		{
			$this->writeQuestionGenericPostData();
			$this->writeQuestionSpecificPostData();
			$this->saveTaxonomyAssignments();
			return 0;
		}
		return 1;
	}

	public function writeQuestionSpecificPostData($always = false)
	{
		$this->object->setPoints( $_POST["points"] );
		$this->object->setMaxSize( $_POST["maxsize"] );
		$this->object->setAllowedExtensions( $_POST["allowedextensions"] );
		$this->object->setCompletionBySubmission( $_POST['completion_by_submission'] == 1 ? true : false );
	}

	/**
	 * Creates an output of the edit form for the question
	 *
	 * @access public
	 */
	public function editQuestion($checkonly = FALSE)
	{
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(false);
		$form->setTableWidth("100%");
		$form->setId("assfileupload");

		$this->addBasicQuestionFormProperties($form);
		$this->populateQuestionSpecificFormPart( $form );

		$this->populateTaxonomyFormSection($form);
		$this->addQuestionFormCommandButtons($form);
		
		$errors = false;
	
		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and 
									  // we need this if we don't want to have duplication of backslashes
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		return $errors;
	}

	public function populateQuestionSpecificFormPart(ilPropertyFormGUI $form)
	{
		// maxsize
		$maxsize = new ilNumberInputGUI($this->lng->txt( "maxsize" ), "maxsize");
		$maxsize->setValue( $this->object->getMaxSize() );
		$maxsize->setInfo( $this->lng->txt( "maxsize_info" ) );
		$maxsize->setSize( 10 );
		$maxsize->setMinValue( 0 );
		$maxsize->setMaxValue( $this->determineMaxFilesize() );
		$maxsize->setRequired( FALSE );
		$form->addItem( $maxsize );

		// allowedextensions
		$allowedextensions = new ilTextInputGUI($this->lng->txt( "allowedextensions" ), "allowedextensions");
		$allowedextensions->setInfo( $this->lng->txt( "allowedextensions_info" ) );
		$allowedextensions->setValue( $this->object->getAllowedExtensions() );
		$allowedextensions->setRequired( FALSE );
		$form->addItem( $allowedextensions );

		// points
		$points = new ilNumberInputGUI($this->lng->txt( "points" ), "points");
		$points->setValue( is_numeric( $this->object->getPoints() ) && $this->object->getPoints(
						   ) >= 0 ? $this->object->getPoints() : ''
		);
		$points->setRequired( TRUE );
		$points->setSize( 3 );
		$points->setMinValue( 0.0 );
		$points->setMinvalueShouldBeGreater( false );
		$form->addItem( $points );

		$subcompl = new ilCheckboxInputGUI($this->lng->txt( 'ass_completion_by_submission'
										   ), 'completion_by_submission');
		$subcompl->setInfo( $this->lng->txt( 'ass_completion_by_submission_info' ) );
		$subcompl->setValue( 1 );
		$subcompl->setChecked( $this->object->isCompletionBySubmissionEnabled() );
		$form->addItem( $subcompl );
		return $form;
	}

	/**
	 * @return mixed
	 */
	public function determineMaxFilesize()
	{
		//mbecker: Quick fix for mantis bug 8595: Change size file
		$upload_max_filesize = get_cfg_var( "upload_max_filesize" );
		// get the value for the maximal post data from the php.ini (if available)
		$post_max_size = get_cfg_var( "post_max_size" );

		//convert from short-string representation to "real" bytes
		$multiplier_a = array( "K" => 1024, "M" => 1024 * 1024, "G" => 1024 * 1024 * 1024 );
		$umf_parts    = preg_split( "/(\d+)([K|G|M])/",
									$upload_max_filesize,
									-1,
									PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);
		$pms_parts    = preg_split( "/(\d+)([K|G|M])/",
									$post_max_size,
									-1,
									PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		if (count( $umf_parts ) == 2)
		{
			$upload_max_filesize = $umf_parts[0] * $multiplier_a[$umf_parts[1]];
		}

		if (count( $pms_parts ) == 2)
		{
			$post_max_size = $pms_parts[0] * $multiplier_a[$pms_parts[1]];
		}

		// use the smaller one as limit
		$max_filesize = min( $upload_max_filesize, $post_max_size );

		if (!$max_filesize)
		{
			$max_filesize = max( $upload_max_filesize, $post_max_size );
			return $max_filesize;
		}
		return $max_filesize;
	}

	function outQuestionForTest($formaction, $active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		$test_output = $this->getTestOutput($active_id, $pass, $is_postponed, $use_post_solutions, $show_feedback); 
		$this->tpl->setVariable("QUESTION_OUTPUT", $test_output);
		$this->tpl->setVariable("FORMACTION", $formaction);
		$this->tpl->setVariable("ENCTYPE", 'enctype="multipart/form-data"');
	}

	/**
	* Get the question solution output
	*
	* @param integer $active_id The active user id
	* @param integer $pass The test pass
	* @param boolean $graphicalOutput Show visual feedback for right/wrong answers
	* @param boolean $result_output Show the reached points for parts of the question
	* @param boolean $show_question_only Show the question without the ILIAS content around
	* @param boolean $show_feedback Show the question feedback
	* @param boolean $show_correct_solution Show the correct solution instead of the user solution
	* @param boolean $show_manual_scoring Show specific information for the manual scoring output
	* @return The solution output of the question as HTML code
	*/
	function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	)
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		$template = new ilTemplate("tpl.il_as_qpl_fileupload_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$solutionvalue = "";
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions =& $this->object->getSolutionValues($active_id, $pass);

			$files = ($show_manual_scoring) ? $this->object->getUploadedFilesForWeb($active_id, $pass) : $this->object->getUploadedFiles($active_id, $pass);
			if (count($files))
			{
				include_once "./Modules/TestQuestionPool/classes/tables/class.assFileUploadFileTableGUI.php";
				$table_gui = new assFileUploadFileTableGUI("iltestoutputgui", 'gotoquestion');
				$table_gui->setTitle($this->lng->txt('already_delivered_files'), 'icon_file.png', $this->lng->txt('already_delivered_files'));
				$table_gui->setData($files);
				$table_gui->setRowTemplate("tpl.il_as_qpl_fileupload_file_view_row.html", "Modules/TestQuestionPool");
				$table_gui->setSelectAllCheckbox("");
				$table_gui->clearCommandButtons();
				$table_gui->disable('select_all');
				$table_gui->disable('numinfo');
				$template->setCurrentBlock("files");
				$template->setVariable('FILES', $table_gui->getHTML());	
				$template->parseCurrentBlock();
			}
		}

		if (($active_id > 0) && (!$show_correct_solution))
		{
			$reached_points = $this->object->getReachedPoints($active_id, $pass);
			if ($graphicalOutput)
			{
				// output of ok/not ok icons for user entered solutions
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.png"));
					$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock("icon_ok");
					if ($reached_points > 0)
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_mostly_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_not_correct_but_positive"));
					}
					else
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
					}
					$template->parseCurrentBlock();
				}
			}
		}
		else
		{
			$reached_points = $this->object->getPoints();
		}

		if ($result_output)
		{
			$resulttext = ($reached_points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")"; 
			$template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $reached_points));
		}
		if ($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		}
		$questionoutput = $template->get();
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$feedback = ($show_feedback) ? $this->getAnswerFeedbackOutput($active_id, $pass) : "";
		if (strlen($feedback)) $solutiontemplate->setVariable("FEEDBACK", $feedback);
		$solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);
		$solutionoutput = $solutiontemplate->get(); 
		if (!$show_question_only)
		{
			// get page object output
			$solutionoutput = '<div class="ilc_question_Standard">'.$solutionoutput."</div>";
		}
		return $solutionoutput;
	}
	
	function getPreview($show_question_only = FALSE)
	{
		$template = new ilTemplate("tpl.il_as_qpl_fileupload_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		if (strlen($this->object->getAllowedExtensions()))
		{
			$template->setCurrentBlock("allowed_extensions");
			$template->setVariable("TXT_ALLOWED_EXTENSIONS", $this->object->prepareTextareaOutput($this->lng->txt("allowedextensions") . ": " . $this->object->getAllowedExtensions()));
			$template->parseCurrentBlock();
		}
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->question, TRUE));
		$template->setVariable("TEXT_UPLOAD", $this->object->prepareTextareaOutput($this->lng->txt('upload')));
		$template->setVariable("TXT_UPLOAD_FILE", $this->object->prepareTextareaOutput($this->lng->txt('file_add')));
		$template->setVariable("TXT_MAX_SIZE", $this->object->prepareTextareaOutput($this->lng->txt('file_notice') . ": " . $this->object->getMaxFilesizeAsString()));

		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		// generate the question output
		$template = new ilTemplate("tpl.il_as_qpl_fileupload_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		
		if ($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions =& $this->object->getSolutionValues($active_id, $pass);

			$files = $this->object->getUploadedFiles($active_id, $pass);
			if (count($files))
			{
				include_once "./Modules/TestQuestionPool/classes/tables/class.assFileUploadFileTableGUI.php";
				$table_gui = new assFileUploadFileTableGUI(null , 'handleQuestionAction');
				$table_gui->setTitle($this->lng->txt('already_delivered_files'), 'icon_file.png', $this->lng->txt('already_delivered_files'));
				$table_gui->setData($files);
				$template->setCurrentBlock("files");
				$template->setVariable('FILES', $table_gui->getHTML());	
				$template->parseCurrentBlock();
			}
		}
		
		if (strlen($this->object->getAllowedExtensions()))
		{
			$template->setCurrentBlock("allowed_extensions");
			$template->setVariable("TXT_ALLOWED_EXTENSIONS", $this->object->prepareTextareaOutput($this->lng->txt("allowedextensions") . ": " . $this->object->getAllowedExtensions()));
			$template->parseCurrentBlock();
		}
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->question, TRUE));
		$template->setVariable("CMD_UPLOAD", 'handleQuestionAction');
		$template->setVariable("TEXT_UPLOAD", $this->object->prepareTextareaOutput($this->lng->txt('upload')));
		$template->setVariable("TXT_UPLOAD_FILE", $this->object->prepareTextareaOutput($this->lng->txt('file_add')));
		$template->setVariable("TXT_MAX_SIZE", $this->object->prepareTextareaOutput($this->lng->txt('file_notice') . ": " . $this->object->getMaxFilesizeAsString()));

		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}

	/**
	 * Sets the ILIAS tabs for this question type
	 *
	 * @access public
	 *
	 * @todo:	MOVE THIS STEPS TO COMMON QUESTION CLASS assQuestionGUI
	 */
	function setQuestionTabs()
	{
		global $rbacsystem, $ilTabs;
		
		$this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$q_type = $this->object->getQuestionType();

		if (strlen($q_type))
		{
			$classname = $q_type . "GUI";
			$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
			$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
		}

		if ($_GET["q_id"])
		{
			if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
			{
				// edit page
				$ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", $force_active);
			}
	
			// edit page
			$ilTabs->addTarget("preview",
				$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"),
				array("preview"),
				"ilAssQuestionPageGUI", "", $force_active);
		}

		$force_active = false;
		if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
		{
			$url = "";
			if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			// edit question properties
			$ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "cancel", "saveEdit"),
				$classname, "");
		}

		// add tab for question feedback within common class assQuestionGUI
		$this->addTab_QuestionFeedback($ilTabs);

		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($ilTabs);
		
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("solution_hint",
				$this->ctrl->getLinkTargetByClass($classname, "suggestedsolution"),
				array("suggestedsolution", "saveSuggestedSolution", "outSolutionExplorer", "cancel", 
				"addSuggestedSolution","cancelExplorer", "linkChilds", "removeSuggestedSolution"
				),
				$classname, 
				""
			);
		}

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}
		
		if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0))
		{
			$ref_id = $_GET["calling_test"];
			if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];

                        global $___test_express_mode;

                        if (!$_GET['test_express_mode'] && !$___test_express_mode) {
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
                        }
                        else {
                            $link = ilTestExpressPage::getReturnToPageLink();
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), $link);
                        }
		}
		else
		{
			$ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
		}
	}

	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$output = "";
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	/**
	 * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
	 * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
	 * make sense in the given context.
	 *
	 * E.g. array('cloze_type', 'image_filename')
	 *
	 * @return string[]
	 */
	public function getAfterParticipationSuppressionQuestionPostVars()
	{
		return array();
	}

	/**
	 * Returns an html string containing a question specific representation of the answers so far
	 * given in the test for use in the right column in the scoring adjustment user interface.
	 *
	 * @param array $relevant_answers
	 *
	 * @return string
	 */
	public function getAggregatedAnswersView($relevant_answers)
	{
		// Empty implementation here since a feasible way to aggregate answer is not known.
		return ''; //print_r($relevant_answers,true);
	}
}