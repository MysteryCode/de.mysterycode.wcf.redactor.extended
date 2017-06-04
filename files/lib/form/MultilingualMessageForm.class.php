<?php

namespace wcf\form;

use wcf\system\bbcode\BBCodeHandler;
use wcf\system\exception\UserInputException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\I18nHandler;
use wcf\system\message\censorship\Censorship;
use wcf\system\WCF;

/**
 * MultilingualMessageForm is an abstract form implementation for a message with optional captcha support providing some fields (and especially redactor-fields) using I18n.
 *
 * @author	Florian Gail
 * @copyright	2016-2017 Florian Gail <https://www.mysterycode.de/>
 * @license	Kostenlose Plugins <https://downloads.mysterycode.de/license/6-kostenlose-plugins/>
 * @package	de.mysterycode.wcf.redactor.extended
 */
abstract class MultilingualMessageForm extends MessageForm {
	/**
	 * list of multilingual fields (input and textarea)
	 * @var string[]
	 */
	public $multilingualFields = [];
	
	/**
	 * list of field names of multilingual redactor instances (redactor only!)
	 * @var string[]
	 */
	public $multilingualMessageFields = [];
	
	/**
	 * fields from $multilingualFields and $multilingualMessageFields that must be multilingual
	 * @var string[]
	 */
	public $forceMultilingualFields = [];
	
	/**
	 * fields from $multilingualFields and $multilingualMessageFields that are allowed to be empty
	 * @var string[]
	 */
	public $mayEmptyMultilingualFields = [];
	
	/**
	 * @var HtmlInputProcessor[][][]
	 */
	public $htmlInputProcessors = [];
	
	/**
	 * @inheritDoc
	 */
	public function readParameters() {
		parent::readParameters();
		
		foreach ($this->multilingualFields as $field) {
			I18nHandler::getInstance()->register($field);
		}
		foreach ($this->multilingualMessageFields as $field) {
			I18nHandler::getInstance()->register($field);
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		I18nHandler::getInstance()->readValues();
		
		foreach ($this->multilingualFields as $field) {
			if (I18nHandler::getInstance()->isPlainValue($field)) $this->$field = I18nHandler::getInstance()->getValue($field);
		}
		foreach ($this->multilingualMessageFields as $field) {
			if (I18nHandler::getInstance()->isPlainValue($field)) $this->$field = I18nHandler::getInstance()->getValue($field);
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function validate() {
		parent::validate();
		
		foreach ($this->multilingualFields as $field) {
			if (!I18nHandler::getInstance()->validateValue($field, in_array($field, $this->forceMultilingualFields), in_array($field, $this->mayEmptyMultilingualFields))) {
				if (I18nHandler::getInstance()->isPlainValue($field)) {
					throw new UserInputException($field);
				} else {
					throw new UserInputException($field, 'multilingual');
				}
			}
		}
		
		foreach ($this->multilingualFields as $field) {
			$this->validateMultilingualMessageField($field);
		}
	}
	
	protected function validateMultilingualMessageField($field) {
		if (empty($this->messageObjectType)) {
			throw new \RuntimeException("Expected non-empty message object type for '".get_class($this)."'");
		}
		
		if (!I18nHandler::getInstance()->validateValue($field, in_array($field, $this->forceMultilingualFields), in_array($field, $this->mayEmptyMultilingualFields))) {
			if (I18nHandler::getInstance()->isPlainValue($field)) {
				throw new UserInputException($field);
			} else {
				throw new UserInputException($field, 'multilingual');
			}
		}
		
		if ($this->disallowedBBCodesPermission) {
			BBCodeHandler::getInstance()->setDisallowedBBCodes(explode(',', WCF::getSession()->getPermission($this->disallowedBBCodesPermission)));
		}
		
		foreach (I18nHandler::getInstance()->getValues($field) as $languageID => $value) {
			$this->htmlInputProcessors[$field][$languageID] = new HtmlInputProcessor();
			$this->htmlInputProcessors[$field][$languageID]->process($value, $this->messageObjectType, 0);
			
			// check text length
			if (!in_array($field, $this->mayEmptyMultilingualFields) && $this->htmlInputProcessor->appearsToBeEmpty()) {
				throw new UserInputException($field);
			}
			$message = $this->htmlInputProcessor->getTextContent();
			if ($this->maxTextLength != 0 && mb_strlen($message) > $this->maxTextLength) {
				throw new UserInputException($field, 'tooLong');
			}
			
			$disallowedBBCodes = $this->htmlInputProcessor->validate();
			if (!empty($disallowedBBCodes)) {
				WCF::getTPL()->assign('disallowedBBCodes', $disallowedBBCodes);
				throw new UserInputException($field, 'disallowedBBCodes');
			}
			
			// search for censored words
			if (ENABLE_CENSORSHIP) {
				$result = Censorship::getInstance()->test($message);
				if ($result) {
					WCF::getTPL()->assign('censoredWords', $result);
					throw new UserInputException($field, 'censoredWordsFound');
				}
			}
		}
	}
	
	/**
	 * @inheritDoc
	 */
	protected function validateText() {
		if (!in_array('text', $this->multilingualMessageFields) || I18nHandler::getInstance()->isPlainValue('text')) {
			parent::validateText();
		} else {
			if (empty($this->messageObjectType)) {
				throw new \RuntimeException("Expected non-empty message object type for '".get_class($this)."'");
			}
			
			if (!I18nHandler::getInstance()->validateValue('text')) {
				throw new UserInputException('text', 'multilingual');
			}
			
			if ($this->disallowedBBCodesPermission) {
				BBCodeHandler::getInstance()->setDisallowedBBCodes(explode(',', WCF::getSession()->getPermission($this->disallowedBBCodesPermission)));
			}
			
			foreach (I18nHandler::getInstance()->getValues('text') as $languageID => $value) {
				$this->htmlInputProcessors['text'][$languageID] = new HtmlInputProcessor();
				$this->htmlInputProcessors['text'][$languageID]->process($value, $this->messageObjectType, 0);
				
				// check text length
				if ($this->htmlInputProcessor->appearsToBeEmpty()) {
					throw new UserInputException('text');
				}
				$message = $this->htmlInputProcessor->getTextContent();
				if ($this->maxTextLength != 0 && mb_strlen($message) > $this->maxTextLength) {
					throw new UserInputException('text', 'tooLong');
				}
				
				$disallowedBBCodes = $this->htmlInputProcessor->validate();
				if (!empty($disallowedBBCodes)) {
					WCF::getTPL()->assign('disallowedBBCodes', $disallowedBBCodes);
					throw new UserInputException('text', 'disallowedBBCodes');
				}
				
				// search for censored words
				if (ENABLE_CENSORSHIP) {
					$result = Censorship::getInstance()->test($message);
					if ($result) {
						WCF::getTPL()->assign('censoredWords', $result);
						throw new UserInputException('text', 'censoredWordsFound');
					}
				}
			}
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		I18nHandler::getInstance()->assignVariables();
	}
}
