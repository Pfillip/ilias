<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/feedback/class.ilAssQuestionFeedback.php';

/**
 * abstract parent feedback class for question types
 * with multiple answer options (mc, sc, ...)
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 * 
 * @package		Modules/TestQuestionPool
 * 
 * @abstract
 */
abstract class ilAssMultiOptionQuestionFeedback extends ilAssQuestionFeedback
{
	/**
	 * table name for specific feedback
	 */
	const TABLE_NAME_SPECIFIC_FEEDBACK = 'qpl_fb_specific';
	
	/**
	 * returns the html of SPECIFIC feedback for the given question id
	 * and answer index for test presentation
	 * 
	 * @access public
	 * @param integer $questionId
	 * @param integer $answerIndex
	 * @return string $specificAnswerFeedbackTestPresentationHTML
	 */
	public function getSpecificAnswerFeedbackTestPresentation($questionId, $answerIndex)
	{
		if( $this->questionOBJ->isAdditionalContentEditingModePageObject() )
		{
			$specificAnswerFeedbackTestPresentationHTML = $this->getPageObjectContent(
					$this->getSpecificAnswerFeedbackPageObjectType(),
					$this->getSpecificAnswerFeedbackPageObjectId($questionId, $answerIndex)
			);
		}
		else
		{
			$specificAnswerFeedbackTestPresentationHTML = $this->getSpecificAnswerFeedbackContent(
				$questionId, $answerIndex
			);
		}
				
		return $specificAnswerFeedbackTestPresentationHTML;
	}
	
	/**
	 * completes a given form object with the specific form properties
	 * required by this question type
	 * 
	 * @access public
	 * @param ilPropertyFormGUI $form
	 */
	public function completeSpecificFormProperties(ilPropertyFormGUI $form)
	{
		$header = new ilFormSectionHeaderGUI();
		$header->setTitle($this->lng->txt('feedback_answers'));
		$form->addItem($header);
		
		if( !$this->questionOBJ->getSelfAssessmentEditingMode() )
		{
			foreach( $this->getAnswerOptionsByAnswerIndex() as $index => $answer )
			{
				$propertyLabel = $this->questionOBJ->prepareTextareaOutput(
						$this->buildAnswerOptionLabel($index, $answer), true
				);
				
				$propertyPostVar = "feedback_answer_$index";
				
				$form->addItem($this->buildFeedbackContentFormProperty(
					$propertyLabel , $propertyPostVar, $this->questionOBJ->isAdditionalContentEditingModePageObject()
				));
			}
		}
	}
	
	/**
	 * initialises a given form object's specific form properties
	 * relating to this question type
	 * 
	 * @access public
	 * @param ilPropertyFormGUI $form
	 */
	public function initSpecificFormProperties(ilPropertyFormGUI $form)
	{
		if (!$this->questionOBJ->getSelfAssessmentEditingMode())
		{
			foreach( $this->getAnswerOptionsByAnswerIndex() as $index => $answer )
			{
				if( $this->questionOBJ->isAdditionalContentEditingModePageObject() )
				{
					$value = $this->getPageObjectNonEditableValueHTML(
							$this->getSpecificAnswerFeedbackPageObjectType(),
							$this->getSpecificAnswerFeedbackPageObjectId($this->questionOBJ->getId(), $index)
					);
				}
				else
				{
					$value = $this->questionOBJ->prepareTextareaOutput(
							$this->getSpecificAnswerFeedbackContent($this->questionOBJ->getId(), $index)
					);
				}
				
				$form->getItemByPostVar("feedback_answer_$index")->setValue($value);
			}
		}
	}
	
	/**
	 * saves a given form object's specific form properties
	 * relating to this question type
	 * 
	 * @access public
	 * @param ilPropertyFormGUI $form
	 */
	public function saveSpecificFormProperties(ilPropertyFormGUI $form)
	{
		if( !$this->questionOBJ->isAdditionalContentEditingModePageObject() )
		{
			foreach( $this->getAnswerOptionsByAnswerIndex() as $index => $answer )
			{
				$this->saveSpecificAnswerFeedbackContent(
						$this->questionOBJ->getId(), $index, $form->getInput("feedback_answer_$index")
				);
			}
		}
	}

	/**
	 * returns the SPECIFIC answer feedback content for a given question id and answer index.
	 *
	 * @access public
	 * @param integer $questionId
	 * @param boolean $answerIndex
	 * @return string $feedbackContent
	 */
	public function getSpecificAnswerFeedbackContent($questionId, $answerIndex)
	{
		require_once 'Services/RTE/classes/class.ilRTE.php';
		
		$res = $this->db->queryF(
			"SELECT * FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s AND answer = %s",
			array('integer','integer'), array($questionId, $answerIndex)
		);
		
		while( $row = $this->db->fetchAssoc($res) )
		{
			$feedbackContent = ilRTE::_replaceMediaObjectImageSrc($row['feedback'], 1);
			break;
		}
		
		return $feedbackContent;
	}
	
	/**
	 * saves SPECIFIC answer feedback content for the given question id and answer index to the database.
	 *
	 * @access public
	 * @param integer $questionId
	 * @param integer $answerIndex
	 * @param string $feedbackContent
	 * @return integer $feedbackId
	 */
	public function saveSpecificAnswerFeedbackContent($questionId, $answerIndex, $feedbackContent)
	{
		require_once 'Services/RTE/classes/class.ilRTE.php';
		
		if( strlen($feedbackContent) )
		{
			$feedbackContent = ilRTE::_replaceMediaObjectImageSrc($feedbackContent, 0);
		}
		
		$feedbackId = $this->getSpecificAnswerFeedbackId($questionId, $answerIndex);
		
		if( $feedbackId )
		{
			$this->db->update($this->getSpecificFeedbackTableName(),
				array(
					'feedback' => array('text', $feedbackContent),
					'tstamp' => array('integer', time())
				),
				array(
					'feedback_id' => array('integer', $feedbackId),
				)
			);
		}
		else
		{
			$feedbackId = $this->db->nextId($this->getSpecificFeedbackTableName());
			
			$this->db->insert($this->getSpecificFeedbackTableName(), array(
				'feedback_id' => array('integer', $feedbackId),
				'question_fi' => array('integer', $questionId),
				'answer' => array('integer', $answerIndex),
				'feedback' => array('text', $feedbackContent),
				'tstamp' => array('integer', time())
			));
		}
		
		return $feedbackId;
	}
		
	/**
	 * deletes all SPECIFIC answer feedback contents (and page objects if required)
	 * for the given question id
	 * 
	 * @access public
	 * @param integer $questionId
	 * @param boolean $isAdditionalContentEditingModePageObject
	 */
	public function deleteSpecificAnswerFeedbacks($questionId, $isAdditionalContentEditingModePageObject)
	{
		if( $isAdditionalContentEditingModePageObject )
		{
			foreach( $this->getSpecificAnswerFeedbackIdByAnswerIndexMap($questionId) as $answerIndex => $pageObjectId )
			{
				$this->ensurePageObjectDeleted($this->getSpecificAnswerFeedbackPageObjectType(), $pageObjectId);
			}
		}
		
		$this->db->manipulateF(
			"DELETE FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s",
			array('integer'), array($questionId)
		);
	}
	/**
	 * duplicates the SPECIFIC feedback relating to the given original question id
	 * and saves it for the given duplicate question id
	 * 
	 * @access protected
	 * @param integer $originalQuestionId
	 * @param integer $duplicateQuestionId
	 */
	protected function duplicateSpecificFeedback($originalQuestionId, $duplicateQuestionId)
	{
		$res = $this->db->queryF(
			"SELECT * FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s",
			array('integer'), array($originalQuestionId)
		);
		
		while( $row = $this->db->fetchAssoc($res) )
		{
			$nextId = $this->db->nextId($this->getSpecificFeedbackTableName());
			
			$this->db->insert($this->getSpecificFeedbackTableName(), array(
				'feedback_id' => array('integer', $nextId),
				'question_fi' => array('integer', $duplicateQuestionId),
				'answer' => array('integer', $row['answer']),
				'feedback' => array('text', $row['feedback']),
				'tstamp' => array('integer', time())
			));
			
			if( $this->questionOBJ->isAdditionalContentEditingModePageObject() )
			{
				$pageObjectType = $this->getSpecificAnswerFeedbackPageObjectType();
				$this->duplicatePageObject($pageObjectType, $row['feedback_id'], $nextId, $duplicateQuestionId);
			}
		}
	}
	
	/**
	 * syncs the SPECIFIC feedback from a duplicated question back to the original question
	 * 
	 * @access protected
	 * @param integer $originalQuestionId
	 * @param integer $duplicateQuestionId
	 */
	protected function syncSpecificFeedback($originalQuestionId, $duplicateQuestionId)
	{
		// delete specific feedback of the original
		$this->db->manipulateF(
			"DELETE FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s", array('integer'), array($originalQuestionId)
		);
			
		// get specific feedback of the actual question
		$res = $this->db->queryF(
			"SELECT * FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s", array('integer'), array($duplicateQuestionId)
		);

		// save specific feedback to the original
		while( $row = $this->db->fetchAssoc($res) )
		{
			$nextId = $this->db->nextId($this->getSpecificFeedbackTableName());
			
			$this->db->insert($this->getSpecificFeedbackTableName(), array(
				'feedback_id' => array('integer', $nextId),
				'question_fi' => array('integer', $originalQuestionId),
				'answer' => array('integer',$row['answer']),
				'feedback' => array('text',$row['feedback']),
				'tstamp' => array('integer',time())
			));
		}
	}
	
	/**
	 * returns the SPECIFIC answer feedback ID for a given question id and answer index.
	 *
	 * @final
	 * @access protected
	 * @param integer $questionId
	 * @param boolean $answerIndex
	 * @return string $feedbackId
	 */
	final protected function getSpecificAnswerFeedbackId($questionId, $answerIndex)
	{
		$res = $this->db->queryF(
			"SELECT feedback_id FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s AND answer = %s",
			array('integer','integer'), array($questionId, $answerIndex)
		);
		
		$feedbackId = null;
		
		while( $row = $this->db->fetchAssoc($res) )
		{
			$feedbackId = $row['feedback_id'];
			break;
		}
		
		return $feedbackId;
	}
	
	/**
	 * returns an array mapping feedback ids to answer indexes
	 * for all answer options of question
	 * 
	 * @final
	 * @access protected
	 * @param integer $questionId
	 * @return array $feedbackIdByAnswerIndexMap
	 */
	final protected function getSpecificAnswerFeedbackIdByAnswerIndexMap($questionId)
	{
		$res = $this->db->queryF(
			"SELECT feedback_id, answer FROM {$this->getSpecificFeedbackTableName()} WHERE question_fi = %s",
			array('integer'), array($questionId)
		);
		
		$feedbackIdByAnswerIndexMap = array();
		
		while( $row = $this->db->fetchAssoc($res) )
		{
			$feedbackIdByAnswerIndexMap[ $row['answer'] ] = $row['feedback_id'];
		}
		
		return $feedbackIdByAnswerIndexMap;
	}

	/**
	 * returns the table name for specific feedback
	 * 
	 * @final
	 * @return string $specificFeedbackTableName
	 */
	final protected function getSpecificFeedbackTableName()
	{
		return self::TABLE_NAME_SPECIFIC_FEEDBACK;
	}
	
	/**
	 * returns the answer options mapped by answer index
	 * (can be overwritten by concrete question type class)
	 * 
	 * @return array $answerOptionsByAnswerIndex
	 */
	protected function getAnswerOptionsByAnswerIndex()
	{
		return $this->questionOBJ->getAnswers();
	}
	
	/**
	 * builds an answer option label from given (mixed type) index and answer
	 * (can be overwritten by concrete question types)
	 * 
	 * @access protected
	 * @param integer $index
	 * @param mixed $answer
	 * @return string $answerOptionLabel
	 */
	protected function buildAnswerOptionLabel($index, $answer)
	{
		return $answer->getAnswertext();
	}
	
	/**
	 * returns a useable page object id for specific answer feedback page objects
	 * for the given question id and answer index
	 * (using the id sequence of non page object specific answer feedback)
	 * 
	 * @final
	 * @access protected
	 * @param integer $questionId
	 * @param integer $answerIndex
	 * @return integer $pageObjectId
	 */
	final protected function getSpecificAnswerFeedbackPageObjectId($questionId, $answerIndex)
	{
		$pageObjectId = $this->getSpecificAnswerFeedbackId($questionId, $answerIndex);
		
		if( !$pageObjectId )
		{
			$pageObjectId = $this->saveSpecificAnswerFeedbackContent($questionId, $answerIndex, null);
		}
		
		return $pageObjectId;
	}
	
	/**
	 * returns the generic feedback export presentation for given question id
	 * either for solution completed or incompleted
	 * 
	 * @access public 
	 * @param integer $questionId
	 * @param integer $answerIndex
	 * @return string $specificAnswerFeedbackExportPresentation
	 */
	public function getSpecificAnswerFeedbackExportPresentation($questionId, $answerIndex)
	{
		if( $this->questionOBJ->isAdditionalContentEditingModePageObject() )
		{
			$specificAnswerFeedbackExportPresentation = $this->getPageObjectXML(
					$this->getSpecificAnswerFeedbackPageObjectType(),
					$this->getSpecificAnswerFeedbackPageObjectId($questionId, $answerIndex)
			);
		}
		else
		{
			$specificAnswerFeedbackExportPresentation = $this->getSpecificAnswerFeedbackContent(
				$questionId, $answerIndex
			);
		}
				
		return $specificAnswerFeedbackExportPresentation;
	}
	
	/**
	 * imports the given feedback content as specific feedback
	 * for the given question id and answer index
	 * 
	 * @access public
	 * @param integer $questionId
	 * @param integer $answerIndex
	 * @param string $feedbackContent
	 */
	public function importSpecificAnswerFeedback($questionId, $answerIndex, $feedbackContent)
	{
		if( $this->questionOBJ->isAdditionalContentEditingModePageObject() )
		{
			$pageObjectId = $this->getSpecificAnswerFeedbackPageObjectId($questionId, $answerIndex);
			$pageObjectType = $this->getSpecificAnswerFeedbackPageObjectType();
			
			$this->createPageObject($pageObjectType, $pageObjectId, $feedbackContent);
		}
		else
		{
			$this->saveSpecificAnswerFeedbackContent($questionId, $answerIndex, $feedbackContent);
		}
	}
}
