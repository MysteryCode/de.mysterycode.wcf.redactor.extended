<?php

namespace wcf\system\form\builder\field\wysiwyg;

use wcf\system\bbcode\BBCodeHandler;
use wcf\system\form\builder\field\AbstractFormField;
use wcf\system\form\builder\field\II18nFormField;
use wcf\system\form\builder\field\TI18nFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\message\censorship\Censorship;

class WysiwygI18nFormField extends WysiwygFormField implements II18nFormField {
	use TI18nFormField {
		validate as protected i18nValidate;
	}
	
	/**
	 * @inheritDoc
	 */
	protected $templateName = '__wysiwygI18nFormField';
	
	/**
	 * @var string[]
	 */
	protected $disallowedBBCodes = [];
	
	/**
	 * @param string[] $disallowedBBCodes
	 */
	public function disallowedBBCodes(array $disallowedBBCodes = []) {
		$this->disallowedBBCodes = $disallowedBBCodes;
	}
	
	/**
	 * @return string[]
	 */
	public function getDisAllowedBBCodes() {
		return $this->disallowedBBCodes;
	}
	
	/**
	 * @inheritDoc
	 */
	public function validate() {
		$this->i18nValidate();
		
		if (!$this->isI18n() || $this->hasPlainValue()) {
			parent::validate();
		}
		else if ($this->hasI18nValues()) {
			foreach ($this->getValue() as $languageID => $value) {
				$this->htmlInputProcessor = new HtmlInputProcessor();
				$this->htmlInputProcessor->process($this->getValue(), $this->getObjectType()->objectType);
				
				if ($this->isRequired() && $this->htmlInputProcessor->appearsToBeEmpty()) {
					$this->addValidationError(new FormFieldValidationError('empty'));
				}
				else {
					$message = $this->htmlInputProcessor->getTextContent();
					$this->validateMinimumLength($message);
					$this->validateMaximumLength($message);
					$this->validateBBCodes($message);
					if (ENABLE_CENSORSHIP) $this->validateCensorship($message);
				}
				
				AbstractFormField::validate();
			}
		}
	}
	
	/**
	 * Validates bbcodes used in $text
	 *
	 * @param string $text
	 */
	public function validateBBCodes($text) {
		BBCodeHandler::getInstance()->setDisallowedBBCodes($this->getDisAllowedBBCodes());
	}
	
	/**
	 * Checks $text for censored words
	 *
	 * @param string $text
	 */
	public function validateCensorship($text) {
		$result = Censorship::getInstance()->test($text);
		if ($result) {
			$this->addValidationError(new FormFieldValidationError('censoredWordsFound'));
		}
	}
}
