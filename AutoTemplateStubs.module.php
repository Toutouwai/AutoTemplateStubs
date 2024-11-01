<?php namespace ProcessWire;

class AutoTemplateStubs extends WireData implements Module, ConfigurableModule {

	/**
	 * Skip these fieldtypes because they aren't usable within a template file
	 */
	public $skip_fieldtypes = array(
		'FieldtypeFieldsetOpen',
		'FieldtypeFieldsetTabOpen',
		'FieldtypeFieldsetGroup',
		'FieldtypeFieldsetClose',
	);

	/**
	 * Skip these system templates
	 */
	public $skip_templates = array(
		'admin',
		'form-builder',
		'language',
		'permission',
		'role',
	);

	/**
	 * Construct
	 */
	public function __construct() {
		parent::__construct();
		$this->custom_page_class_compatible = 0;
		$this->class_prefix = 'tpl_';
		$this->stubs_path_relative = '/site/templates/';
	}

	/**
	 * Ready
	 */
	public function ready() {
		$this->addHookAfter('Fields::save', $this, 'fieldSaved');
		$this->addHookAfter('Fields::saveFieldgroupContext', $this, 'fieldContextSaved');
		$this->addHookBefore('Fieldgroups::save', $this, 'fieldgroupSaved');
		$this->addHookAfter('Fieldgroups::delete', $this, 'fieldgroupDeleted');
		$this->addHookBefore('Modules::saveModuleConfigData', $this, 'moduleConfigSaved');
	}

	/**
	 * Get array of data types returned by core fieldtypes
	 *
	 * @return array
	 */
	public function ___getReturnTypes() {
		return array(
			'FieldtypeCache' => 'array',
			'FieldtypeCheckbox' => 'int',
			'FieldtypeComments' => 'CommentArray',
			'FieldtypeDatetime' => 'int|string',
			'FieldtypeEmail' => 'string',
			'FieldtypeFieldsetPage' => function (Field $field) {
				if($this->custom_page_class_compatible) {
					$class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)) . 'Page';
					return "FieldsetPage|Repeater{$class_name}";
				} else {
					return "FieldsetPage|{$this->class_prefix}repeater_{$field->name}";
				}
			},
			'FieldtypeFile' => function (Field $field) {
				switch($field->outputFormat) {
					case FieldtypeFile::outputFormatArray:
						return 'Pagefiles';
					case FieldtypeFile::outputFormatSingle:
						return 'Pagefile|null';
					case FieldtypeFile::outputFormatString:
						return 'string';
					default: // outputFormatAuto
						return $field->maxFiles == 1 ? 'Pagefile|null' : 'Pagefiles';
				}
			},
			'FieldtypeFloat' => 'float',
			'FieldtypeImage' => function (Field $field) {
				switch($field->outputFormat) {
					case FieldtypeImage::outputFormatArray:
						return 'Pageimages';
					case FieldtypeImage::outputFormatSingle:
						return 'Pageimage|null';
					case FieldtypeImage::outputFormatString:
						return 'string';
					default: // outputFormatAuto
						return $field->maxFiles == 1 ? 'Pageimage|null' : 'Pageimages';
				}
			},
			'FieldtypeInteger' => 'int',
			'FieldtypeModule' => 'string',
			'FieldtypeOptions' => 'SelectableOptionArray',
			'FieldtypePage' => function (Field $field) {
				switch($field->derefAsPage) {
					case FieldtypePage::derefAsPageOrFalse:
						return 'Page|false';
					case FieldtypePage::derefAsPageOrNullPage:
						return 'Page|NullPage';
					default: // derefAsPageArray
						return 'PageArray';
				}
			},
			'FieldtypePageTable' => 'PageArray',
			'FieldtypePageTitle' => 'string',
			'FieldtypePageTitleLanguage' => 'string',
			'FieldtypePassword' => 'Password',
			'FieldtypeRepeater' => function (Field $field) {
				if($this->custom_page_class_compatible) {
					$class_name = ucfirst($this->wire()->sanitizer->camelCase($field->name)) . 'Page';
					return "RepeaterPageArray|Repeater{$class_name}[]";
				} else {
					return "RepeaterPageArray|{$this->class_prefix}repeater_{$field->name}[]";
				}
			},
			'FieldtypeSelector' => 'string',
			'FieldtypeText' => 'string',
			'FieldtypeTextLanguage' => 'string',
			'FieldtypeTextarea' => 'string',
			'FieldtypeTextareaLanguage' => 'string',
			'FieldtypeCombo' => function (Field $field) {
				return "ComboValue_{$field->name}";
			},
		);
	}

	/**
	 * Field saved
	 *
	 * @param HookEvent $event
	 */
	protected function fieldSaved(HookEvent $event) {
		$field = $event->arguments(0);
		if(in_array((string) $field->type, $this->skip_fieldtypes)) return;
		foreach($field->getTemplates() as $template) {
			$this->generateTemplateStub($template);
		}
	}

	/**
	 * Field context saved
	 *
	 * @param HookEvent $event
	 */
	protected function fieldContextSaved(HookEvent $event) {
		$fieldgroup = $event->arguments(1);
		foreach($fieldgroup->getTemplates() as $template) {
			if(in_array($template->name, $this->skip_templates)) continue;
			$this->generateTemplateStub($template);
		}
	}

	/**
	 * Fieldgroup saved
	 *
	 * @param HookEvent $event
	 */
	protected function fieldgroupSaved(HookEvent $event) {
		$fieldgroup = $event->arguments(0);
		// Generate stubs for each template that uses this fieldgroup
		foreach($fieldgroup->getTemplates() as $template) {
			if(in_array($template->name, $this->skip_templates)) continue;
			$this->generateTemplateStub($template);
		}
		// If fieldgroup renamed, delete old stub
		// There is surely a better way to get the existing fieldgroup name but can't get my head around track changes
		$sql = "SELECT name FROM fieldgroups WHERE id = {$fieldgroup->id} LIMIT 1";
		$query = $this->wire()->database->query($sql);
		$existing_name = $query->fetch(\PDO::FETCH_COLUMN);
		if($fieldgroup->name !== $existing_name) $this->deleteTemplateStub($existing_name);
	}

	/**
	 * Fieldgroup deleted
	 *
	 * @param HookEvent $event
	 */
	protected function fieldgroupDeleted(HookEvent $event) {
		$fieldgroup = $event->arguments(0);
		$this->deleteTemplateStub($fieldgroup->name);
	}

	/**
	 * Module config saved
	 *
	 * @param HookEvent $event
	 */
	protected function moduleConfigSaved(HookEvent $event) {
		$class = $event->arguments(0);
		$data = $event->arguments(1);
		if($class != $this) return;
		$regenerate_stubs = false;
		// Compatibility with custom Page classes setting changed
		if($data['custom_page_class_compatible'] !== $this->custom_page_class_compatible) {
			$this->custom_page_class_compatible = $data['custom_page_class_compatible'];
			$regenerate_stubs = true;
		}
		// Class name prefix changed
		if($data['class_prefix'] !== $this->class_prefix) {
			$this->class_prefix = $data['class_prefix'];
			$regenerate_stubs = true;
		}
		// Stubs relative path changed
		if($data['stubs_path_relative'] !== $this->stubs_path_relative) {
			// Delete all existing stubs and stubs dir (if empty) before changing the relative path
			$this->deleteAllTemplateStubs();
			@rmdir($this->getStubsPath());
			// Change relative path and regenerate stubs
			$this->stubs_path_relative = $data['stubs_path_relative'];
			$regenerate_stubs = true;
		}
		// Regenerate template stubs checkbox checked
		if(!empty($data['regenerate_stubs'])) {
			$regenerate_stubs = true;
			$this->message($this->_('Template stubs regenerated.'));
			unset($data['regenerate_stubs']);
		}
		$event->arguments(1, $data);
		// Regenerate stubs when needed
		if($regenerate_stubs) {
			$this->regenerateAllStubs();
		}
	}

	/**
	 * Get stub info for a field
	 *
	 * @param Field $field
	 * @param Template|null $template
	 * @return array
	 */
	protected function getFieldInfo(Field $field, Template $template = null) {
		// If Combo field then create ComboValue class stub for the field
		if($field instanceof ComboField) {
			$settings = $field->getComboSettings();
			$phpdoc = $settings->toPhpDoc(false, true);
			$class_name = "ComboValue_{$field->name}";
			$stubs_path = $this->getStubsPath();
			if(!is_dir($stubs_path)) $this->wire()->files->mkdir($stubs_path, true);
			$this->wire()->files->filePutContents($stubs_path . "$class_name.php", $phpdoc);
		}
		if($template) $field = $template->fieldgroup->getFieldContext($field);
		$field_type = (string) $field->type;
		$return_types = $this->getReturnTypes();
		$return_type = 'mixed'; // default
		if(!empty($return_types[$field_type])) $return_type = $return_types[$field_type];
		if(is_callable($return_type)) $return_type = $return_type($field);
		return array(
			'label' => $field->label,
			'returns' => $return_type,
		);
	}

	/**
	 * Generate stub file for a template
	 *
	 * @param Template $template
	 */
	protected function generateTemplateStub(Template $template) {
		if($this->custom_page_class_compatible) {
			$class_name = ucfirst($this->wire()->sanitizer->camelCase($template->name)) . 'Page';
		} else {
			$class_name = $this->class_prefix . str_replace('-',  '_', $template->name);
		}
		$contents = "<?php namespace ProcessWire;\n\n";
		$template_name = $template->name;
		if($template->label) $template_name .= " ($template->label)";
		$contents .= "/**\n * Template: $template_name\n *";
		foreach($template->fields as $field) {
			if(in_array((string) $field->type, $this->skip_fieldtypes)) continue;
			$field_info = $this->getFieldInfo($field, $template);
			$contents .= "\n * @property {$field_info['returns']} \${$field->name} {$field_info['label']}";
		}
		$contents .= "\n */\nclass $class_name extends Page {}\n";
		$stubs_path = $this->getStubsPath();
		if(!is_dir($stubs_path)) $this->wire()->files->mkdir($stubs_path, true);
		$this->wire()->files->filePutContents($stubs_path . "$class_name.php", $contents);
	}

	/**
	 * Generate stub files for all templates
	 */
	protected function generateAllTemplateStubs() {
		foreach($this->wire()->templates as $template) {
			if(in_array($template->name, $this->skip_templates)) continue;
			$this->generateTemplateStub($template);
		}
	}

	/**
	 * Delete stub file for a template
	 *
	 * @param string $fieldgroup_name
	 */
	protected function deleteTemplateStub($fieldgroup_name) {
		$stub_name = $this->class_prefix . $fieldgroup_name;
		$file_path = $this->getStubsPath() . "$stub_name.php";
		if(is_file($file_path)) unlink($file_path);
	}

	/**
	 * Delete stub files for all templates
	 */
	protected function deleteAllTemplateStubs() {
		$stub_files = glob($this->getStubsPath() .  '*' . '.php');
		foreach($stub_files as $file) {
			if(is_file($file)) unlink($file);
		}
	}

	/**
	 * Regenerate all stub files
	 */
	protected function regenerateAllStubs() {
		$this->deleteAllTemplateStubs();
		$this->generateAllTemplateStubs();
	}

	/**
	 * Get full path to stubs directory
	 */
	protected function getStubsPath() {
		$path = $this->wire()->config->paths->root;
		$relative_path = trim($this->stubs_path_relative, '/');
		if($relative_path) $path .= $relative_path . '/';
		$path .= 'AutoTemplateStubs/';
		return $path;
	}

	/**
	 * Install
	 */
	public function ___install() {
		$this->generateAllTemplateStubs();
	}

	/**
	 * Upgrade
	 *
	 * @param $fromVersion
	 * @param $toVersion
	 */
	public function ___upgrade($fromVersion, $toVersion) {
		// Upgrade from < v0.1.8: remove old stubs directory
		if(version_compare($fromVersion, '0.1.8', '<')) {
			$old_stubs_dir = $this->wire()->config->paths->$this . 'stubs/';
			if(is_dir($old_stubs_dir)) {
				$this->wire()->files->rmdir($old_stubs_dir, true);
			}
			// Regenerate stubs
			$this->regenerateAllStubs();
		}

		// Upgrade from < v0.2.5
		// Attempt to update stubs path
		if(version_compare($fromVersion, '0.2.5', '<')) {
			$old_stubs_path = rtrim($this->stubs_path_relative, '/');
			if(substr($old_stubs_path, -17) === 'AutoTemplateStubs') {
				$new_stubs_path = substr($old_stubs_path, 0, -17);
				$this->wire()->modules->saveConfig($this, 'stubs_path_relative', $new_stubs_path);
			}
		}
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire()->modules;

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f_name = 'custom_page_class_compatible';
		$f->name = $f_name;
		$f->label = sprintf($this->_('Name stub classes for compatibility with [custom Page classes](%s)'), 'https://processwire.com/blog/posts/pw-3.0.152/#new-ability-to-specify-custom-page-classes');
		$f->notes = $this->_('If checked stub classes will be named according to the camel case "[TemplateName]Page" format used for custom page classes, e.g. BlogPostPage');
		$f->checked = $this->$f_name === 1 ? 'checked' : '';
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f_name = 'class_prefix';
		$f->name = $f_name;
		$f->label = $this->_('Class name prefix');
		$f->description = $this->_('Optionally enter a class name prefix to apply to generated stub classes.');
		$f->value = $this->$f_name;
		$f->showIf = 'custom_page_class_compatible!=1';
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f_name = 'stubs_path_relative';
		$f->name = $f_name;
		$f->label = $this->_('Stubs parent directory path');
		$f->description = $this->_('The location where the AutoTemplateStubs directory will be created, relative to the site root. Default: /site/templates/');
		$f->value = $this->$f_name;
		$inputfields->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'regenerate_stubs';
		$f->label = $this->_('Regenerate template stubs');
		$f->icon = 'refresh';
		$f->collapsed = Inputfield::collapsedYes;
		$f->description = $this->_('By checking this box and saving module config you can force all template stubs to be regenerated.');
		$inputfields->add($f);
	}

}
