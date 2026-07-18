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
		$this->inject_custom_page_class_phpdoc = 0;
	}

	/**
	 * Ready
	 */
	public function ready() {
		$this->addHookAfter('Fieldtype::renamedField', $this, 'hookRenamedField');
		$this->addHookAfter('Templates::renamed', $this, 'hookRenamedTemplate');
		$this->addHookAfter('Fields::save', $this, 'fieldSaved');
		$this->addHookAfter('Fields::saveFieldgroupContext', $this, 'fieldContextSaved');
		$this->addHookBefore('Fieldgroups::save', $this, 'fieldgroupSaved');
		$this->addHookAfter('Fieldgroups::delete', $this, 'fieldgroupDeleted');
		$this->addHookAfter('Fields::delete', $this, 'fieldDeleted');
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
					$class_name = $this->wire()->sanitizer->pascalCase($field->name) . 'FieldsetPage';
					return "FieldsetPage|{$class_name}";
				} else {
					return "FieldsetPage|{$this->class_prefix}repeater_{$field->name}";
				}
			},
			'FieldtypeFile' => function (Field $field) {
				return $this->resolveFileImageType($field, 'file');
			},
			'FieldtypeFloat' => 'float',
			'FieldtypeImage' => function (Field $field) {
				return $this->resolveFileImageType($field, 'image');
			},
			'FieldtypeInteger' => 'int',
			'FieldtypeModule' => 'string',
			'FieldtypeMultiplier' => function (Field $field) {
				return $this->getMultiplierReturnType($field);
			},
			'FieldtypeOptions' => 'SelectableOptionArray',
			'FieldtypePage' => function (Field $field) {
				switch($field->derefAsPage) {
					case FieldtypePage::derefAsPageOrFalse:
						return 'Page|false';
					case FieldtypePage::derefAsPageOrNullPage:
						return 'Page|NullPage';
					default:
						return 'PageArray';
				}
			},
			'FieldtypePageTable' => 'PageArray',
			'FieldtypePageTitle' => 'string',
			'FieldtypePageTitleLanguage' => 'string',
			'FieldtypePassword' => 'Password',
			'FieldtypeRepeater' => function (Field $field) {
				if($this->custom_page_class_compatible) {
					$class_name = $this->wire()->sanitizer->pascalCase($field->name) . 'RepeaterPage';
					return "RepeaterPageArray|{$class_name}[]";
				} else {
					return "RepeaterPageArray|{$this->class_prefix}repeater_{$field->name}[]";
				}
			},
			'FieldtypeRepeaterMatrix' => function (Field $field) {
				if(!method_exists($field, 'getMatrixTypesInfo')) return 'RepeaterMatrixPageArray';
				$matrixTypes = $field->getMatrixTypesInfo();
				$types = array('RepeaterMatrixPageArray');
				
				foreach($matrixTypes as $typeName => $typeInfo) {
					if($this->custom_page_class_compatible) {
						$fieldClassName = $this->wire()->sanitizer->pascalCase($field->name) . 'RepeaterMatrixPage';
						$className = "{$fieldClassName}_" . $this->wire()->sanitizer->pascalCase($typeName);
					} else {
						$typeSlug = str_replace('-', '_', $typeName);
						$className = "{$this->class_prefix}repeater_matrix_{$field->name}_{$typeSlug}";
					}
					$types[] = $className . '[]';
				}
				
				return implode('|', $types);
			},
			'FieldtypeSelector' => 'string',
			'FieldtypeTable' => function (Field $field) {
				return $this->getTableRowsReturnType($field);
			},
			'FieldtypeText' => 'string',
			'FieldtypeTextLanguage' => 'string',
			'FieldtypeTextarea' => 'string',
			'FieldtypeTextareaLanguage' => 'string',
			'FieldtypeTextareas' => function (Field $field) {
				return $this->getTextareasReturnType($field);
			},
			'FieldtypeCombo' => function (Field $field) {
				return "ComboValue_{$field->name}";
			},
			'FieldtypeCustom' => function (Field $field) {
				return "CustomField_{$field->name}";
			},
		);
	}

	/**
	 * Resolve File or Image field type based on outputFormat and maxFiles
	 *
	 * @param Field|Inputfield $obj Field or Inputfield object
	 * @param string $type 'file' or 'image'
	 * @return string
	 */
	protected function resolveFileImageType($obj, $type) {
		$isImage = ($type === 'image');
		$single = $isImage ? 'Pageimage|null' : 'Pagefile|null';
		$multiple = $isImage ? 'Pageimages' : 'Pagefiles';
		
		$outputFormat = $obj instanceof Field ? $obj->outputFormat : $obj->get('outputFormat');
		
		if($outputFormat !== null) {
			if($outputFormat == FieldtypeFile::outputFormatArray) return $multiple;
			if($outputFormat == FieldtypeFile::outputFormatSingle) return $single;
			if($outputFormat == FieldtypeFile::outputFormatString) return 'string';
		}
		
		$maxFiles = $obj instanceof Field ? $obj->maxFiles : $obj->maxFiles;
		return $maxFiles == 1 ? $single : $multiple;
	}

	/**
	 * Get return type for a given Fieldtype class name
	 *
	 * @param string $fieldtypeClass
	 * @param Field $field
	 * @return string
	 */
	protected function getReturnTypeForFieldtypeClass($fieldtypeClass, Field $field) {
		$return_types = $this->getReturnTypes();
		if(!isset($return_types[$fieldtypeClass])) return 'mixed';
		$return_type = $return_types[$fieldtypeClass];
		if(is_callable($return_type)) $return_type = $return_type($field);
		return $return_type ? $return_type : 'mixed';
	}

	/**
	 * Get return type for a Table field
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTableRowsReturnType(Field $field) {
		$className = $this->getTableRowsClassName($field);
		return "$className|TableRows";
	}

	/**
	 * Get return type for a Textareas field
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTextareasReturnType(Field $field) {
		$className = $this->getTextareasDataClassName($field);
		return "$className|TextareasData";
	}

	/**
	 * Get return type for a Multiplier field
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getMultiplierReturnType(Field $field) {
		$itemType = 'mixed';
		$fieldtype = $field->type;
		if($fieldtype && method_exists($fieldtype, 'getFieldtype')) {
			$baseFieldtype = $fieldtype->getFieldtype($field);
			if($baseFieldtype) {
				$itemType = $this->getReturnTypeForFieldtypeClass($baseFieldtype->className(), $field);
			}
		}
		$itemArrayType = $this->getArrayType($itemType);
		return "MultiplierArray|$itemArrayType";
	}

	/**
	 * Get class name for TableRows stub
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTableRowsClassName(Field $field) {
		return $this->wire()->sanitizer->pascalCase($field->name) . 'TableRows';
	}

	/**
	 * Get class name for TableRow stub
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTableRowClassName(Field $field) {
		return $this->wire()->sanitizer->pascalCase($field->name) . 'TableRow';
	}

	/**
	 * Get class name for TextareasData stub
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTextareasDataClassName(Field $field) {
		return "TextareasData_{$field->name}";
	}

	/**
	 * Format an array type from a value type
	 *
	 * @param string $type
	 * @return string
	 */
	protected function getArrayType($type) {
		if(strpos($type, '|') !== false) {
			return "($type)[]";
		}
		return $type . '[]';
	}

	/**
	 * Map FieldtypeTable column metadata to PHPDoc return types
	 *
	 * @param array $col
	 * @return string
	 */
	protected function getTableColumnReturnType(array $col) {
		if(empty($col['valid'])) return 'mixed';
		switch($col['valid']) {
			case 'text':
			case 'textarea':
			case 'email':
			case 'url':
			case 'html':
				return 'string';
			case 'int':
				return 'int';
			case 'float':
			case 'double':
			case 'decimal':
				return 'float';
			case 'date':
			case 'datetime':
				return 'int|string';
			case 'time':
				return 'string|int';
			case 'toggle':
				return 'int|string';
			case 'array':
			case 'textTags':
				return 'array';
			case 'Page':
				return 'Page|NullPage';
			case 'PageArray':
				return 'PageArray';
			case 'LanguagesPageFieldValue':
				return 'LanguagesPageFieldValue';
			case 'file':
				return 'Pagefile|null';
			case 'image':
				return 'Pageimage|null';
			default:
				return 'mixed';
		}
	}

	/**
	 * Derive return type for Textareas definitions
	 *
	 * @param Field $field
	 * @return string
	 */
	protected function getTextareasDefinitionReturnType(Field $field) {
		$fieldtype = $field->type;
		if(!$fieldtype || !method_exists($fieldtype, 'getValueType')) return 'string';
		$type = $fieldtype->getValueType($field, 'type');
		$fieldtypeClass = $fieldtype->getValueType($field, 'fieldtype');
		if($fieldtypeClass) {
			return $this->getReturnTypeForFieldtypeClass($fieldtypeClass, $field);
		}
		switch($type) {
			case 'Array':
				return 'array';
			case 'PageArray':
				return 'PageArray';
			case 'Page':
				return 'Page|NullPage';
			case 'Boolean':
				return 'int';
			case 'Text':
			case 'Textarea':
				return 'string';
			default:
				return 'mixed';
		}
	}

	/**
	 * Explicit Inputfield return-type map (used by FieldtypeCustom)
	 *
	 * @return array
	 */
	public function ___getInputfieldReturnTypes() {
		return array(
			'InputfieldInteger' => 'int',
			'InputfieldFloat' => 'float',
			'InputfieldCheckbox' => 'int',
			'InputfieldDatetime' => 'int|string',
			'InputfieldPage' => function(Inputfield $f) {
				$deref = $f->derefAsPage;
				if($deref == FieldtypePage::derefAsPageOrFalse) return 'Page|false';
				if($deref == FieldtypePage::derefAsPageOrNullPage) return 'Page|NullPage';
				return 'PageArray';
			},
			'InputfieldSelect' => function(Inputfield $f) {
				return $f->attr('multiple') ? 'array' : 'string';
			},
			'InputfieldCheckboxes' => 'array',
			'InputfieldAsmSelect' => 'array',
			'InputfieldPageListSelectMultiple' => 'array',
			'InputfieldText' => 'string',
			'InputfieldTextarea' => 'string',
			'InputfieldTextTags' => 'string',
			'InputfieldRadios' => 'string',
			'InputfieldToggle' => 'int',
			'InputfieldTinyMCE' => 'string',
			'InputfieldCKEditor' => 'string',
			'InputfieldFieldset' => null,
		);
	}

	/**
	 * Hook Fieldtype::renamedField
	 * 
	 * @param HookEvent $event
	 */
	protected function hookRenamedField(HookEvent $event) {
		$field = $event->arguments(0);
		$prevName = $event->arguments(1);
		
		// Clone field and set to old name to reuse delete logic
		$oldField = clone $field;
		$oldField->name = $prevName;
		
		$this->deleteFieldStub($oldField);
	}

	/**
	 * Hook Templates::renamed
	 * 
	 * @param HookEvent $event
	 */
	protected function hookRenamedTemplate(HookEvent $event) {
		$oldName = $event->arguments(1);
		$this->deleteTemplateStub($oldName);
	}

	/**
	 * Field saved
	 *
	 * @param HookEvent $event
	 */
	protected function fieldSaved(HookEvent $event) {
		$field = $event->arguments(0);
		$this->handleFieldSave($field, $field->getTemplates());
	}

	/**
	 * Field context saved
	 *
	 * @param HookEvent $event
	 */
	protected function fieldContextSaved(HookEvent $event) {
		$field = $event->arguments(0);
		$fieldgroup = $event->arguments(1);
		$this->handleFieldSave($field, $fieldgroup->getTemplates());
	}

	/**
	 * Handle field save logic (shared by fieldSaved and fieldContextSaved)
	 *
	 * @param Field $field
	 * @param Templates|array $templates
	 */
	protected function handleFieldSave(Field $field, $templates) {
		$fieldType = (string) $field->type;
		if(in_array($fieldType, $this->skip_fieldtypes)) return;
		
		if($fieldType === 'FieldtypeCombo') $this->generateComboStub($field);
		if($fieldType === 'FieldtypeTable') $this->generateTableStubs($field);
		if($fieldType === 'FieldtypeTextareas') $this->generateTextareasStub($field);
		if($fieldType === 'FieldtypeRepeaterMatrix') {
			$this->deleteFieldStub($field);
			$this->generateMatrixStub($field);
		}
		if($fieldType === 'FieldtypeCustom') $this->generateCustomFieldStub($field);
		
		foreach($templates as $template) {
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
		// Inject custom Page class PHPDoc toggle changed
		if($data['inject_custom_page_class_phpdoc'] !== $this->inject_custom_page_class_phpdoc) {
			$this->inject_custom_page_class_phpdoc = $data['inject_custom_page_class_phpdoc'];
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
	 * Field deleted
	 *
	 * @param HookEvent $event
	 */
	protected function fieldDeleted(HookEvent $event) {
		$field = $event->arguments(0);
		$this->deleteFieldStub($field);
	}

	/**
	 * Delete stub file(s) for a field
	 * 
	 * @param Field $field
	 */
	protected function deleteFieldStub(Field $field) {
		$stubs_path = $this->getStubsPath();
		$files = array();

		if((string) $field->type === 'FieldtypeCombo') {
			$files[] = $stubs_path . "ComboValue_{$field->name}.php";
		}
		
		if((string) $field->type === 'FieldtypeTable') {
			$files[] = $stubs_path . $this->getTableRowClassName($field) . ".php";
			$files[] = $stubs_path . $this->getTableRowsClassName($field) . ".php";
		}
		
		if((string) $field->type === 'FieldtypeTextareas') {
			$files[] = $stubs_path . $this->getTextareasDataClassName($field) . ".php";
		}
		
		if((string) $field->type === 'FieldtypeCustom') {
			$files[] = $stubs_path . "CustomField_{$field->name}.php";
		}
		
		if((string) $field->type === 'FieldtypeRepeaterMatrix') {
			// Match default naming
			$p1 = $stubs_path . $this->class_prefix . "repeater_matrix_{$field->name}_*.php";
			$p1 = str_replace('-', '_', $p1);
			// Match custom page class naming
			$fieldPascalName = $this->wire()->sanitizer->pascalCase($field->name);
			$p2 = $stubs_path . "{$fieldPascalName}RepeaterMatrixPage_*.php";
			
			foreach(glob($p1) as $f) $files[] = $f;
			foreach(glob($p2) as $f) $files[] = $f;
		}

		foreach($files as $file) {
			if(is_file($file)) unlink($file);
		}
	}

	/**
	 * Get stub info for a field
	 *
	 * @param Field $field
	 * @param Template|null $template
	 * @return array
	 */
	protected function getFieldInfo(Field $field, $template = null) {
		if($template) $field = $template->fieldgroup->getFieldContext($field);
		$field_type = (string) $field->type;
		$return_type = $this->getReturnTypeForFieldtypeClass($field_type, $field);
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
		$defaultPageExists = $this->custom_page_class_compatible
			&& file_exists($this->wire()->config->paths->classes . 'DefaultPage.php');
		$extends = $defaultPageExists ? 'DefaultPage' : 'Page';
		if($template->name === 'user') {
			$extends = 'User';
		}
		if(strpos($template->name, 'repeater_') === 0) {
			$fieldName = substr($template->name, 9);
			$field = $this->wire()->fields->get($fieldName);
			if($field) {
				if($field->type instanceof FieldtypeRepeaterMatrix) {
					$extends = 'RepeaterMatrixPage';
				} elseif($field->type instanceof FieldtypeRepeater) {
					$extends = 'RepeaterPage';
				}
			}
		} else if(strpos($template->name, 'fieldset_') === 0) {
			$extends = 'FieldsetPage';
		}
		$class_name = $this->getTemplateClassName($template->name, $extends);

		$contents = "<?php namespace ProcessWire;\n\n";
		$template_name = $template->name;
		if($template->label) $template_name .= " ($template->label)";
		$contents .= "/**\n * Template: $template_name\n";
		$properties = array();
		$inject_phpdoc = $this->custom_page_class_compatible && $this->inject_custom_page_class_phpdoc;
		$vars = $inject_phpdoc ? array() : null;
		foreach($template->fields as $field) {
			if(in_array((string) $field->type, $this->skip_fieldtypes)) continue;
			if($field->name === 'repeater_matrix_type') continue;
			$field_info = $this->getFieldInfo($field, $template);
			$properties[] = " * @property {$field_info['returns']} \${$field->name} {$field_info['label']}";
			if($inject_phpdoc) $vars[] = " * @property {$field_info['returns']} \${$field->name} {$field_info['label']}";
		}
		if($properties) $contents .= implode("\n", $properties) . "\n";
		$contents .= " */\nclass $class_name extends $extends {}\n";
		if(!$this->inject_custom_page_class_phpdoc) {
			$this->writeStubFile($class_name, $contents);
		}
		if($inject_phpdoc) $this->injectCustomPageClassDoc($template, $vars, $class_name, $extends);
	}

	/**
	 * Generate stub file for a Combo field
	 *
	 * @param Field $field
	 */
	protected function generateComboStub(Field $field) {
		if((string) $field->type !== 'FieldtypeCombo') return;
		
		$className = "ComboValue_{$field->name}";
		$phpDoc = $field->getComboSettings()->toPhpDoc(false, true);
		$this->writeStubFile($className, $phpDoc);
	}

	/**
	 * Generate stub file for a FieldtypeCustom field
	 *
	 * @param Field $field
	 */
	protected function generateCustomFieldStub(Field $field) {
		if((string) $field->type !== 'FieldtypeCustom') return;

		/** @var FieldtypeCustom $fieldtype */
		$fieldtype = $field->type;
		$defs = $fieldtype->newCustomFieldDefs($field);
		$defs->getInputfields($field->name);
		$inputfields = $defs->getFlatInputfields();
		
		if(!is_array($inputfields)) $inputfields = array();

		if(empty($inputfields) && $defs->getInputfields() instanceof InputfieldWrapper) {
			$inputfields = array();
			$collect = function($wrapper) use (&$collect, &$inputfields) {
				foreach($wrapper as $f) {
					$prop = $f->get(CustomFieldDefs::_property_name);
					if($prop) $inputfields[$prop] = $f;
					if($f instanceof InputfieldWrapper && count($f)) $collect($f);
				}
			};
			$collect($defs->getInputfields());
		}

		if(empty($inputfields)) return;

		$types = $this->getInputfieldReturnTypes();
		$className = "CustomField_{$field->name}";
		$properties = array();
		
		foreach($inputfields as $name => $f) {
			$inputfieldClass = $f->className();
			$type = null;
			
			if(array_key_exists($inputfieldClass, $types)) {
				$type = $types[$inputfieldClass];
				$type = is_callable($type) ? $type($f) : $type;
			} else {
				foreach($types as $class => $t) {
					if($f instanceof $class) {
						$type = is_callable($t) ? $t($f) : $t;
						break;
					}
				}
			}
			
			if($type === null) $type = 'mixed';
			$type = $type ?: 'string';
			$properties[] = " * @property $type \$$name {$f->label}";
		}

		if(empty($properties)) return;

		$contents = "<?php namespace ProcessWire;\n\n";
		$contents .= "/**\n * Custom Field: {$field->name}\n";
		$contents .= implode("\n", $properties);
		$contents .= "\n */\nclass $className extends CustomWireData {}\n";
		
		$this->writeStubFile($className, $contents);
	}

	/**
	 * Generate stub files for a Table field
	 *
	 * @param Field $field
	 */
	protected function generateTableStubs(Field $field) {
		if((string) $field->type !== 'FieldtypeTable') return;
		
		$rowClass = $this->getTableRowClassName($field);
		$rowsClass = $this->getTableRowsClassName($field);
		$fieldtype = $field->type;
		$properties = array();
		
		if($fieldtype && method_exists($fieldtype, 'getColumns')) {
			foreach($fieldtype->getColumns($field) as $col) {
				if(empty($col['name'])) continue;
				$name = $this->wire()->sanitizer->fieldName($col['name']);
				if(!$name) continue;
				$type = $this->getTableColumnReturnType($col);
				$label = isset($col['label']) ? $col['label'] : '';
				$properties[] = " * @property $type \$$name $label";
			}
		}
		
		$contents = "<?php namespace ProcessWire;\n\n";
		$contents .= "/**\n * Table Field: {$field->name}\n";
		if($properties) $contents .= implode("\n", $properties);
		$contents .= "\n * @property int|null \$rowId Row ID";
		$contents .= "\n */\nclass $rowClass extends TableRow {}\n";
		$this->writeStubFile($rowClass, $contents);
		
		$contents = "<?php namespace ProcessWire;\n\n";
		$contents .= "/**\n * Table Field: {$field->name}\n";
		$contents .= " * @method $rowClass new(array \$data = array())\n";
		$contents .= " * @method $rowClass makeBlankItem()\n";
		$contents .= " */\nclass $rowsClass extends TableRows {}\n";
		$this->writeStubFile($rowsClass, $contents);
	}

	/**
	 * Generate stub file for a Textareas field
	 *
	 * @param Field $field
	 */
	protected function generateTextareasStub(Field $field) {
		if((string) $field->type !== 'FieldtypeTextareas') return;
		
		$className = $this->getTextareasDataClassName($field);
		$fieldtype = $field->type;
		if(!$fieldtype || !method_exists($fieldtype, 'getTextareaDefinitions')) return;
		
		$definitions = $fieldtype->getTextareaDefinitions($field);
		if(!is_array($definitions) || empty($definitions)) return;
		
		$type = $this->getTextareasDefinitionReturnType($field);
		$properties = array();
		
		foreach($definitions as $name => $definition) {
			$label = is_array($definition) && isset($definition[0]) ? $definition[0] : '';
			$properties[] = " * @property $type \$$name $label";
		}
		
		if(empty($properties)) return;
		
		$contents = "<?php namespace ProcessWire;\n\n";
		$contents .= "/**\n * Textareas Field: {$field->name}\n";
		$contents .= implode("\n", $properties);
		$contents .= "\n */\nclass $className extends TextareasData {}\n";
		
		$this->writeStubFile($className, $contents);
	}

	/**
	 * Generate stub files for a RepeaterMatrix field
	 *
	 * @param Field $field
	 */
	protected function generateMatrixStub(Field $field) {
		if((string) $field->type !== 'FieldtypeRepeaterMatrix') return;
		if(!method_exists($field, 'getMatrixTypesInfo')) return; // Field not yet a RepeaterMatrixField (e.g. during creation)
		
		$matrixTypes = $field->getMatrixTypesInfo();
		if(empty($matrixTypes)) return;
		
		foreach($matrixTypes as $typeName => $typeInfo) {
			$this->generateMatrixTypeStub($field, $typeName, $typeInfo);
		}
	}

	/**
	 * Generate stub file for a single matrix type
	 *
	 * @param Field $field
	 * @param string $typeName
	 * @param array $typeInfo
	 */
	protected function generateMatrixTypeStub(Field $field, $typeName, array $typeInfo) {
		if($this->custom_page_class_compatible) {
			$fieldClassName = $this->wire()->sanitizer->pascalCase($field->name) . 'RepeaterMatrixPage';
			$className = "{$fieldClassName}_" . $this->wire()->sanitizer->pascalCase($typeName);
		} else {
			$typeSlug = str_replace('-', '_', $typeName);
			$className = "{$this->class_prefix}repeater_matrix_{$field->name}_{$typeSlug}";
		}
		
		$properties = array();
		if(!empty($typeInfo['fields'])) {
			foreach($typeInfo['fields'] as $subField) {
				$fieldInfo = $this->getFieldInfo($subField);
				$properties[] = " * @property {$fieldInfo['returns']} \${$subField->name} {$fieldInfo['label']}";
			}
		}
		
		$contents = "<?php namespace ProcessWire;\n\n";
		$contents .= "/**\n";
		$contents .= " * Matrix Type: {$typeInfo['label']}\n";
		$contents .= " * Field: {$field->name}\n";
		if($properties) $contents .= " *\n" . implode("\n", $properties);
		$contents .= "\n */\nclass $className extends RepeaterMatrixPage {}\n";
		
		$this->writeStubFile($className, $contents);
	}

	/**
	 * Generate stub files for all templates
	 */
	protected function generateAllTemplateStubs() {
		foreach($this->wire()->fields->findByType('FieldtypeCombo') as $field) {
			$this->generateComboStub($field);
		}

		foreach($this->wire()->fields->findByType('FieldtypeTable') as $field) {
			$this->generateTableStubs($field);
		}

		foreach($this->wire()->fields->findByType('FieldtypeTextareas') as $field) {
			$this->generateTextareasStub($field);
		}
		
		foreach($this->wire()->fields->findByType('FieldtypeRepeaterMatrix') as $field) {
			$this->generateMatrixStub($field);
		}

		foreach($this->wire()->fields->findByType('FieldtypeCustom') as $field) {
			$this->generateCustomFieldStub($field);
		}
		
		foreach($this->wire()->templates as $template) {
			if(in_array($template->name, $this->skip_templates)) continue;
			$this->generateTemplateStub($template);
		}
	}

	/**
	 * Delete stub file for a template
	 *
	 * @param string $templateName
	 */
	protected function deleteTemplateStub($templateName) {
		$className = $this->getTemplateClassName($templateName);
		$file_path = $this->getStubsPath() . "$className.php";
		if(is_file($file_path)) unlink($file_path);
	}

	/**
	 * Get the class name for a given template name
	 * 
	 * @param string $templateName
	 * @return string
	 */
	protected function getTemplateClassName($templateName, $extends = null) {
		if($this->custom_page_class_compatible) {
				if($extends === null) {
					$extends = 'Page';
					if($templateName === 'user') {
						$extends = 'User';
					}
					if(strpos($templateName, 'repeater_') === 0) {
						$fieldName = substr($templateName, 9);
						$field = $this->wire()->fields->get($fieldName);
						if($field) {
						if($field->type instanceof FieldtypeRepeaterMatrix) {
							$extends = 'RepeaterMatrixPage';
						} elseif($field->type instanceof FieldtypeRepeater) {
							$extends = 'RepeaterPage';
						}
					}
				} else if(strpos($templateName, 'fieldset_') === 0) {
					$extends = 'FieldsetPage';
				}
			}

			$baseName = $templateName;
			if($extends === 'RepeaterPage' || $extends === 'RepeaterMatrixPage') {
				if(strpos($templateName, 'repeater_') === 0) $baseName = substr($templateName, 9);
			} else if($extends === 'FieldsetPage' && strpos($templateName, 'fieldset_') === 0) {
				$baseName = substr($templateName, 9);
			}

			$pascal = $this->wire()->sanitizer->pascalCase($baseName);
			if($extends === 'RepeaterPage') return $pascal . 'RepeaterPage';
			if($extends === 'RepeaterMatrixPage') return $pascal . 'RepeaterMatrixPage';
			if($extends === 'FieldsetPage') return $pascal . 'FieldsetPage';
			return $pascal . 'Page';
		}
		return $this->class_prefix . str_replace('-',  '_', $templateName);
	}

	/**
	 * Inject AutoTemplateStubs properties into native custom Page class
	 *
	 * @param Template $template
	 * @param array $properties
	 */
	protected function injectCustomPageClassDoc(Template $template, array $vars, $className, $extends) {
		if(!$this->custom_page_class_compatible) return;
		if(!$this->inject_custom_page_class_phpdoc) return;
		if(empty($vars)) return;
		$classesPath = $this->wire()->config->paths->classes;
		if(!is_dir($classesPath)) $this->wire()->files->mkdir($classesPath, true);
		$filePath = $classesPath . "$className.php";
		if(!is_file($filePath)) {
			$region = "//region AutoTemplateStubs\n//endregion";
			$contents = "<?php namespace ProcessWire;\n\n";
			$contents .= "$region\n";
			$contents .= "class $className extends $extends {\n";
			$contents .= "}\n";
			$this->wire()->files->filePutContents($filePath, $contents);
		}
		if(!is_file($filePath) || !is_writable($filePath)) return;
		$contents = file_get_contents($filePath);
		if($contents === false) return;
		$regionPattern = '/\/\/region AutoTemplateStubs\s*.*?\s*\/\/endregion/s';
		if(!preg_match($regionPattern, $contents)) return;
		$regionLines = array('//region AutoTemplateStubs');
		$regionLines[] = '/**';
		foreach($vars as $line) $regionLines[] = $line;
		$regionLines[] = ' */';
		$regionLines[] = '//endregion';
		$region = implode("\n", $regionLines);
		$contents = preg_replace($regionPattern, $region, $contents, 1);
		$this->wire()->files->filePutContents($filePath, $contents);
	}

	/**
	 * Write stub content to file
	 * 
	 * @param string $className
	 * @param string $contents
	 * @return int|bool
	 */
	protected function writeStubFile($className, $contents) {
		$stubs_path = $this->getStubsPath();
		if(!is_dir($stubs_path)) $this->wire()->files->mkdir($stubs_path, true);
		return $this->wire()->files->filePutContents($stubs_path . "$className.php", $contents);
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

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f_name = 'inject_custom_page_class_phpdoc';
		$f->name = $f_name;
		$f->label = $this->_('Inject PHPDoc into custom Page classes');
		$f->description = $this->_('When enabled, AutoTemplateStubs updates files in /site/classes by replacing the region "AutoTemplateStubs". **Warning: This edits your existing class files.**');
		$f->notes = $this->_('Only the region block is modified. For existing classes, you must add the region marker manually. If a class does not exist yet, an empty class file with the region marker will be created.  
		```//region AutoTemplateStubs
		//endregion```');

		$f->checked = $this->$f_name === 1 ? 'checked' : '';
		$f->showIf = 'custom_page_class_compatible=1';
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
