<?php
namespace wcf\form;
use wcf\data\language\Language;
use wcf\system\form\builder\container\wysiwyg\WysiwygFormContainer;
use wcf\system\form\builder\field\CaptchaFormField;
use wcf\system\form\builder\field\language\ContentLanguageFormField;
use wcf\system\form\builder\field\TitleFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\language\LanguageFactory;
use wcf\system\message\censorship\Censorship;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * MessageForm is an abstract form implementation for a message with optional captcha support.
 *
 * @author	Marcel Werk
 * @copyright	2001-2019 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Form
 */
abstract class MessageFormBuilderForm extends AbstractFormBuilderForm {
	/**
	 * object type for attachments, if left blank, attachment support is disabled
	 * @var	string
	 */
	public $attachmentObjectType = '';
	
	/**
	 * parent object id for attachments
	 * @var	integer
	 */
	public $attachmentParentObjectID = 0;
	
	/**
	 * list of available content languages
	 * @var	Language[]
	 */
	public $availableContentLanguages = [];
	
	/**
	 * content language id
	 * @var	integer
	 */
	public $languageID;
	
	/**
	 * maximum text length
	 * @var	integer
	 */
	public $maxTextLength = 0;
	
	/**
	 * message object type for html processing
	 * @var string
	 */
	public $messageObjectType = '';
	
	/**
	 * temp hash
	 * @var	string
	 */
	public $tmpHash = '';
	
	/**
	 * @var WysiwygFormContainer
	 */
	private $wysiwygField;
	
	/**
	 * @var CaptchaFormField
	 */
	private $captchaField;
	
	/**
	 * @var TitleFormField
	 */
	private $subjectField;
	
	/**
	 * @var ContentLanguageFormField
	 */
	private $languageIDField;
	
	/**
	 * @inheritDoc
	 */
	public function readParameters() {
		parent::readParameters();
		
		if (isset($_REQUEST['tmpHash'])) {
			$this->tmpHash = $_REQUEST['tmpHash'];
		}
		if (empty($this->tmpHash)) {
			$this->tmpHash = WCF::getSession()->getVar('__wcfAttachmentTmpHash');
			if ($this->tmpHash === null) {
				$this->tmpHash = StringUtil::getRandomID();
			}
			else {
				WCF::getSession()->unregister('__wcfAttachmentTmpHash');
			}
		}
		
		$this->availableContentLanguages = LanguageFactory::getInstance()->getContentLanguages();
		if (WCF::getUser()->userID) {
			foreach ($this->availableContentLanguages as $key => $value) {
				if (!in_array($key, WCF::getUser()->getLanguageIDs())) unset($this->availableContentLanguages[$key]);
			}
		}
	}
	
	/**
	 * @return WysiwygFormContainer
	 */
	protected function getWysiwygContainer() {
		if ($this->wysiwygField === null) {
			/** @var WysiwygFormContainer $wysiwyg */
			$this->wysiwygField = WysiwygFormContainer::create('text')
				->messageObjectType($this->messageObjectType)
				->maximumLength($this->maxTextLength);
			if (MODULE_ATTACHMENT && $this->attachmentObjectType) {
				$this->wysiwygField->attachmentData($this->attachmentObjectType,$this->attachmentParentObjectID);
			}
		}
		
		return $this->wysiwygField;
	}
	
	/**
	 * @return CaptchaFormField
	 */
	protected function getCaptchaField() {
		if ($this->captchaField === null) {
			$this->captchaField = CaptchaFormField::create('captcha')
				->label('reCAPTCHA')
				->objectType(CAPTCHA_TYPE);
		}
		
		return $this->captchaField;
	}
	
	/**
	 * @return ContentLanguageFormField
	 */
	protected function getLanguageIDField() {
		if ($this->languageIDField === null) {
			$this->languageIDField = ContentLanguageFormField::create('languageID');
		}
		
		return $this->languageIDField;
	}
	
	/**
	 * @return TitleFormField
	 */
	protected function getSubjectField() {
		if ($this->subjectField === null) {
			$this->subjectField = TitleFormField::create('subject')
				->required(true)
				->maximumLength(255)
				->addValidator(new FormFieldValidator('censorship', function (TitleFormField $formField) {
						if (ENABLE_CENSORSHIP) {
							$result = Censorship::getInstance()->test($formField->getValue());
							if ($result) {
								$formField->addValidationError(new FormFieldValidationError('censoredWordsFound'));
							}
						}
					}));
		}
		
		return $this->subjectField;
	}
	
	/**
	 * @inheritDoc
	 */
	protected function createForm() {
		parent::createForm();
		
		$this->form->appendChildren([
			$this->getSubjectField(),
			$this->getWysiwygContainer()
		]);
		
		$this->createMessageForm();
		
		$this->form->appendChild($this->getCaptchaField());
	}
	
	/**
	 * Add elements to the form document before the captcha field
	 * 
	 * This is the method that is intended to be overwritten by child classes
	 * to add the form containers and fields instead of createForm().
	 */
	protected function createMessageForm() {
		// does nothing
	}
	
	/**
	 * @inheritDoc
	 */
	public function readData() {
		if (empty($_POST) && $this->formAction == 'create') {
			$this->getLanguageIDField()->value(WCF::getLanguage()->languageID);
		}
		
		parent::readData();
	}
	
	/**
	 * @inheritDoc
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		WCF::getTPL()->assign([
			'availableContentLanguages' => $this->availableContentLanguages,
			'tmpHash' => $this->tmpHash
		]);
	}
}
