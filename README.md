# Auto Template Stubs

A module for ProcessWire CMS/CMF. Automatically creates stub files for templates when fields or fieldgroups are saved.

Stub files are useful if you are using an IDE (e.g. PhpStorm) that provides code assistance - the stub files let the IDE know what fields exist in each template and what data type each field returns. Depending on your IDE's features you get benefits such as code completion for field names as you type, type inference, inspection, documentation, etc.

## Installation

[Install](http://modules.processwire.com/install-uninstall/) the Auto Template Stubs module.

## Configuration

* If you're using [custom Page classes](https://processwire.com/blog/posts/pw-3.0.152/#new-ability-to-specify-custom-page-classes) and want code assistance for methods in the custom Page class as well as field names you can tick the "Name stub classes for compatibility with custom Page classes" checkbox. This names the template stub classes using the same format as for custom Page class names. A side-effect of this is that your IDE may warn you that multiple definitions exist for your custom Page classes.

* If you're not using the setting above you have the option to change the class name prefix. It's good to use a class name prefix because it reduces the chance that the class name will clash with an existing class name.

* The directory path used to store the stub files is configurable.

* There is a checkbox to manually trigger the regeneration of all stub files if needed.

## Usage

Add a line near the top of each of your template files to tell your IDE what stub class name to associate with the `$page` variable within the template file. For example, with the default class name prefix you would add the following line at the top of the `home.php` template file:

```php
/** @var tpl_home $page */
```

Or if you're using the "Name stub classes for compatibility with custom Page classes" setting then it would be:

```php
/** @var HomePage $page */
```

Now enjoy code completion, etc, in your IDE.

![stubs](https://user-images.githubusercontent.com/1538852/45592324-d0552a80-b9bd-11e8-9d64-2f29be754c67.gif)

## Adding data types for non-core Fieldtype modules

The module includes the data types returned by all the core Fieldtype modules. If you want to add data types returned by one or more non-core Fieldtype modules then you can hook the `AutoTemplateStubs::getReturnTypes()` method. For example, in `/site/ready.php`:

```php
// Add data types for some non-core Fieldtype modules
$wire->addHookAfter('AutoTemplateStubs::getReturnTypes', function(HookEvent $event) {
    $extra_types = [
        'FieldtypeDecimal' => 'string',
        'FieldtypeLeafletMapMarker' => 'LeafletMapMarker',
        'FieldtypeRepeaterMatrix' => 'RepeaterMatrixPageArray',
        'FieldtypeTable' => 'TableRows',
    ];
    $event->return = $event->return + $extra_types;
});
```

## Credits

Inspired by and much credit to the [Template Stubs](https://modules.processwire.com/modules/template-stubs/) module by mindplay.dk.