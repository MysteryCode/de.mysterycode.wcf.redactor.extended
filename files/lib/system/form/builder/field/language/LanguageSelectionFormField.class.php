<?php

namespace wcf\system\form\builder\field\language;

use wcf\system\form\builder\TFormNode;
use wcf\system\language\LanguageFactory;

class LanguageSelectionFormField extends ContentLanguageFormField {
	use TFormNode {
		isAvailable as protected parentIsAvailable;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getContentLanguages() {
		return LanguageFactory::getInstance()->getLanguages();
	}
	
	/**
	 * @inheritDoc
	 */
	public function isAvailable() {
		return true; // !empty(LanguageFactory::getInstance()->getContentLanguageIDs()) && $this->parentIsAvailable();
	}
}
