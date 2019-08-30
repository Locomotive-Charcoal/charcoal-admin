<?php

namespace Charcoal\Admin\Property;

use Traversable;
use UnexpectedValueException;
use InvalidArgumentException;

// From PSR-3
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

// From Pimple
use Pimple\Container;

// From 'charcoal-core'
use Charcoal\Model\DescribableInterface;
use Charcoal\Model\DescribableTrait;

// From 'charcoal-view'
use Charcoal\View\ViewableInterface;
use Charcoal\View\ViewableTrait;

// From 'charcoal-translator'
use Charcoal\Translator\Translation;
use Charcoal\Translator\TranslatorAwareTrait;

// From 'charcoal-property'
use Charcoal\Property\PropertyInterface;
use Charcoal\Property\PropertyMetadata;

// From 'charcoal-admin'
use Charcoal\Admin\Property\PropertyInputInterface;

/**
 *
 */
abstract class AbstractPropertyInput implements
    DescribableInterface,
    PropertyInputInterface,
    LoggerAwareInterface,
    ViewableInterface
{
    use DescribableTrait;
    use LoggerAwareTrait;
    use TranslatorAwareTrait;
    use ViewableTrait;

    const DEFAULT_INPUT_TYPE = 'text';

    /**
     * @var string $lang
     */
    private $lang;

    /**
     * @var string $ident
     */
    private $ident;

    /**
     * @var boolean $readOnly
     */
    private $readOnly;

    /**
     * @var boolean $required
     */
    private $required;

    /**
     * @var boolean $disabled
     */
    private $disabled;

    /**
     * @var boolean $multiple
     */
    private $multiple;

    /**
     * The control type for the HTML element `<input>`.
     *
     * @var string $type
     */
    protected $type;

    /**
     * @var string $inputType
     */
    protected $inputType;

    /**
     * @var string $inputMode
     */
    protected $inputMode;

    /**
     * @var string $inputName
     */
    protected $inputName;

    /**
     * @var string $inputId
     */
    protected $inputId;

    /**
     * @var string $inputClass
     */
    protected $inputClass = '';

    /**
     * @var Translation|string|null $placeholder
     */
    private $placeholder;

    /**
     * The control's prefix.
     *
     * @var Translation|string|null
     */
    protected $inputPrefix;

    /**
     * The control's suffix.
     *
     * @var Translation|string|null
     */
    protected $inputSuffix;

    /**
     * @var array $propertyData
     */
    private $propertyData = [];

    /**
     * @var mixed $propertyVal
     */
    private $propertyVal;

    /**
     * @var PropertyInterface $property
     */
    private $property;

    /**
     * @param array|\ArrayAccess $data Constructor data.
     */
    public function __construct($data = null)
    {
        if (!isset($data['logger'])) {
            $data['logger'] = new NullLogger();
        }
        $this->setLogger($data['logger']);

        if (isset($data['metadata_loader'])) {
            $this->setMetadataLoader($data['metadata_loader']);
        }

        // DI Container can optionally be set in property constructor.
        if (isset($data['container'])) {
            $this->setDependencies($data['container']);
        }
    }

    /**
     * This function takes an array and fill the model object with its value.
     *
     * This method either calls a setter for each key (`set_{$key}()`) or sets a public member.
     *
     * For example, calling with `setData(['properties'=>$properties])` would call
     * `setProperties($properties)`, becasue `setProperties()` exists.
     *
     * But calling with `setData(['foobar'=>$foo])` would set the `$foobar` member
     * on the metadata object, because the method `set_foobar()` does not exist.
     *
     * @param array $data The input data.
     * @return self
     */
    public function setData(array $data)
    {
        foreach ($data as $prop => $val) {
            $func = [$this, $this->setter($prop)];
            if (is_callable($func)) {
                call_user_func($func, $val);
                unset($data[$prop]);
            } else {
                $this->{$prop} = $val;
            }
        }

        $this->propertyData = $data;

        return $this;
    }

    /**
     * Retrieve the control's data options for JavaScript components.
     *
     * @return array
     */
    public function controlDataForJs()
    {
        return [];
    }

    /**
     * Retrieve the control's {@see self::controlDataForJs() options} as a JSON string.
     *
     * @return string Returns data serialized with {@see json_encode()}.
     */
    public function controlDataForJsAsJson()
    {
        return json_encode($this->controlDataForJs(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Retrieve the control's {@see self::controlDataForJs() options} as a JSON string, protected from Mustache.
     *
     * @return string Returns a stringified JSON object, protected from Mustache rendering.
     */
    public function escapedControlDataForJsAsJson()
    {
        return '{{=<% %>=}}'.$this->controlDataForJsAsJson().'<%={{ }}=%>';
    }

    /**
     * @param mixed $val The property value.
     * @return PropertyInputInterface Chainable
     */
    public function setPropertyVal($val)
    {
        $this->propertyVal = $val;
        return $this;
    }

    /**
     * @return mixed
     */
    public function propertyVal()
    {
        return $this->propertyVal;
    }

    /**
     * @param string $lang The language code / ident.
     * @return PropertyInputInterface Chainable
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
        return $this;
    }

    /**
     * Get the input language
     * @return string
     */
    public function lang()
    {
        if ($this->lang === null) {
            return $this->translator()->getLocale();
        }

        return $this->lang;
    }

    /**
     * @return boolean
     */
    public function hidden()
    {
        if ($this->p()['l10n']) {
            if ($this->lang() != $this->translator()->getLocale()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ident Input identifier.
     * @throws InvalidArgumentException If the ident is not a string.
     * @return self
     */
    public function setIdent($ident)
    {
        if (!is_string($ident)) {
            throw new InvalidArgumentException(
                'Property Input identifier must be string'
            );
        }
        $this->ident = $ident;
        return $this;
    }

    /**
     * @return string
     */
    public function ident()
    {
        return $this->ident;
    }

    /**
     * @param boolean $readOnly The read-only flag.
     * @return self
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = !!$readOnly;
        return $this;
    }

    /**
     * @return boolean
     */
    public function readOnly()
    {
        return $this->readOnly;
    }

    /**
     * @param boolean $required Required flag.
     * @return self
     */
    public function setRequired($required)
    {
        $this->required = !!$required;
        return $this;
    }

    /**
     * @return boolean
     */
    public function required()
    {
        return $this->required;
    }

    /**
     * @param boolean $disabled Disabled flag.
     * @return self
     */
    public function setDisabled($disabled)
    {
        $this->disabled = !!$disabled;
        return $this;
    }

    /**
     * @return boolean
     */
    public function disabled()
    {
        return $this->disabled;
    }

    /**
     * @param boolean $multiple Multiple flag.
     * @return self
     */
    public function setMultiple($multiple)
    {
        $this->multiple = !!$multiple;
        return $this;
    }

    /**
     * @return boolean
     */
    public function multiple()
    {
        return $this->multiple;
    }

    /**
     * Set the form control's placeholder.
     *
     * A placeholder is a hint to the user of what can be entered
     * in the property control.
     *
     * @param  mixed $placeholder The placeholder attribute.
     * @return self
     */
    public function setPlaceholder($placeholder)
    {
        if ($placeholder === null || $placeholder === '') {
            $this->placeholder = '';
            return $this;
        }

        $this->placeholder = $this->translator()->translation($placeholder);
        if ($this->placeholder instanceof Translation) {
            $this->placeholder->isRendered = false;
        } else {
            $this->placeholder = '';
        }

        return $this;
    }

    /**
     * Retrieve the placeholder.
     *
     * @return Translation|string|null
     */
    public function placeholder()
    {
        if ($this->placeholder === null) {
            $metadata = $this->metadata();

            if (isset($metadata['data']['placeholder'])) {
                $this->setPlaceholder($metadata['data']['placeholder']);
            } else {
                $this->placeholder = '';
            }
        }

        if ($this->placeholder instanceof Translation) {
            if (isset($this->placeholder->isRendered) && $this->placeholder->isRendered === false) {
                $this->placeholder = $this->renderTranslatableTemplate($this->placeholder);
            }

            if ($this->lang()) {
                return $this->placeholder[$this->lang()];
            }
        }

        return $this->placeholder;
    }

    /**
     * Set the input ID.
     *
     * Used for the HTML "ID" attribute.
     *
     * @param  string $inputId HTML input id attribute.
     * @return self
     */
    public function setInputId($inputId)
    {
        $this->inputId = $inputId;

        return $this;
    }

    /**
     * Retrieve the input ID.
     *
     * If none was previously set then a unique random one will be generated.
     *
     * @return string
     */
    public function inputId()
    {
        if (!$this->inputId) {
            $this->inputId = $this->generateInputId();
        }

        return $this->inputId;
    }

    /**
     * @param string $inputClass The input class attribute.
     * @throws InvalidArgumentException If the class is not a string.
     * @return self
     */
    public function setInputClass($inputClass)
    {
        if (!is_string($inputClass)) {
            throw new InvalidArgumentException('CSS Class(es) must be a string');
        }
        $this->inputClass = $inputClass;
        return $this;
    }

    /**
     * @return string
     */
    public function inputClass()
    {
        return $this->inputClass;
    }

    /**
     * Set the input name.
     *
     * Used for the HTML "name" attribute.
     *
     * @param  string $inputName HTML input id attribute.
     * @return self
     */
    public function setInputName($inputName)
    {
        $this->inputName = $inputName;

        return $this;
    }

    /**
     * Retrieve the input name.
     *
     * The input name should always be the property's ident.
     *
     * @return string
     */
    public function inputName()
    {
        if ($this->inputName) {
            $name = $this->inputName;
        } else {
            $name = $this->propertyIdent();
        }

        if ($this->p()['l10n']) {
            $name .= '['.$this->lang().']';
        }

        if ($this->multiple()) {
            $name .= '[]';
        }

        return $name;
    }

    /**
     * @uses   AbstractProperty::inputVal() Must handle string sanitization of value.
     * @throws UnexpectedValueException If the value is invalid.
     * @return string
     */
    public function inputVal()
    {
        $prop = $this->p();
        $val  = $prop->inputVal($this->propertyVal(), [
            'lang' => $this->lang()
        ]);

        if ($val === null) {
            return '';
        }

        if (!is_scalar($val)) {
            throw new UnexpectedValueException(sprintf(
                'Property Input Value must be a string, received %s',
                (is_object($val) ? get_class($val) : gettype($val))
            ));
        }

        return $val;
    }

    /**
     * Set the hint to the browser for which keyboard to display.
     *
     * @param  string $inputMode The input type.
     * @throws InvalidArgumentException If the provided argument is not a string.
     * @return self
     */
    public function setInputMode($inputMode)
    {
        if (!is_string($inputMode)) {
            throw new InvalidArgumentException(
                'Property Input Mode must be a string.'
            );
        }
        $this->inputMode = $inputMode;
        return $this;
    }

    /**
     * Retrieve the hint to the browser for which keyboard to display.
     *
     * @return string
     */
    public function inputMode()
    {
        return $this->inputMode;
    }

    /**
     * @param  string $inputType The input type.
     * @throws InvalidArgumentException If the provided argument is not a string.
     * @return self
     * @todo   [mcaskill 2016-11-16]: Rename to `controlType` or `controlTemplate`.
     */
    public function setInputType($inputType)
    {
        if (!is_string($inputType)) {
            throw new InvalidArgumentException(
                'Property Input Type must be a string.'
            );
        }
        $this->inputType = $inputType;
        return $this;
    }

    /**
     * @return string
     */
    public function inputType()
    {
        if ($this->inputType === null) {
            $this->inputType = 'charcoal/admin/property/input/text';
        }
        return $this->inputType;
    }


    /**
     * Determine if the property has an affix.
     *
     * ### Textual `<input>`s only
     *
     * Avoid using `<select>` elements here as they cannot be fully styled in WebKit browsers.
     *
     * Avoid using `<textarea>` elements here as their `rows` attribute will
     * not be respected in some cases.
     *
     * @return boolean
     */
    public function hasInputAffix()
    {
        return ($this->inputPrefix() || $this->inputSuffix());
    }

    /**
     * Retrieve the control's prefix.
     *
     * @param  mixed $affix Text to display before the control.
     * @throws InvalidArgumentException If the suffix is not translatable.
     * @return self
     */
    public function setInputPrefix($affix)
    {
        $this->inputPrefix = $this->translator()->translation($affix);
        $this->inputPrefix->isRendered = false;

        return $this;
    }

    /**
     * Retrieve the control's prefix.
     *
     * @return Translation|string|null
     */
    public function inputPrefix()
    {
        if ($this->inputPrefix instanceof Translation) {
            if (isset($this->inputPrefix->isRendered) && $this->inputPrefix->isRendered === false) {
                $this->inputPrefix = $this->renderTranslatableTemplate($this->inputPrefix);
            }

            if ($this->lang()) {
                return $this->inputPrefix[$this->lang()];
            }
        }

        return $this->inputPrefix;
    }

    /**
     * Retrieve the control's suffix.
     *
     * @param  mixed $affix Text to display after the control.
     * @throws InvalidArgumentException If the suffix is not translatable.
     * @return self
     */
    public function setInputSuffix($affix)
    {
        $this->inputSuffix = $this->translator()->translation($affix);
        $this->inputSuffix->isRendered = false;

        return $this;
    }

    /**
     * Retrieve the control's suffix.
     *
     * @return Translation|string|null
     */
    public function inputSuffix()
    {
        if ($this->inputSuffix instanceof Translation) {
            if (isset($this->inputSuffix->isRendered) && $this->inputSuffix->isRendered === false) {
                $this->inputSuffix = $this->renderTranslatableTemplate($this->inputSuffix);
            }

            if ($this->lang()) {
                return $this->inputSuffix[$this->lang()];
            }
        }

        return $this->inputSuffix;
    }

    /**
     * Set the control type for the HTML element `<input>`.
     *
     * @param  string $type The control type.
     * @throws InvalidArgumentException If the provided argument is not a string.
     * @return self
     * @todo   [mcaskill 2016-11-16]: Rename to `inputType`.
     */
    public function setType($type)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException(
                'HTML Input Type must be a string.'
            );
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Retrieve the control type for the HTML element `<input>`.
     *
     * @return string
     */
    public function type()
    {
        if ($this->type === null) {
            $this->type = self::DEFAULT_INPUT_TYPE;
        }

        return $this->type;
    }

    /**
     * @return string
     */
    public function propertyIdent()
    {
        return $this->p()->ident();
    }

    /**
     * @param PropertyInterface $p The property.
     * @return self
     */
    public function setProperty(PropertyInterface $p)
    {
        $this->property  = $p;
        $this->inputName = null;

        return $this;
    }

    /**
     * @return PropertyInterface
     */
    public function property()
    {
        return $this->property;
    }

    /**
     * Alias of {@see self::property()}
     *
     * @return PropertyInterface
     */
    public function p()
    {
        return $this->property();
    }

    /**
     * Render the given template from string.
     *
     * @see    \Charcoal\View\ViewableInterface::renderTemplate()
     * @param  mixed $templateString The template to render.
     * @return string The rendered template.
     */
    public function renderTranslatableTemplate($templateString)
    {
        if ($templateString instanceof Translation) {
            $origLang = $this->translator()->getLocale();
            foreach ($this->translator()->availableLocales() as $lang) {
                if (!isset($templateString[$lang])) {
                    continue;
                }
                $translation = $templateString[$lang];
                $isBlank = empty($translation) && !is_numeric($translation);
                if (!$isBlank) {
                    $this->translator()->setLocale($lang);
                    $translation = $this->renderTemplate($translation);
                    if ($translation !== null) {
                        $templateString[$lang] = $translation;
                    }
                }
            }
            $this->translator()->setLocale($origLang);
            $templateString->isRendered = true;

            return $templateString;
        } elseif (is_string($templateString)) {
            $isBlank = empty($templateString) && !is_numeric($templateString);
            if (!$isBlank) {
                return $this->renderTemplate($templateString);
            }
        }

        return '';
    }

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A dependencies container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        // Fullfills the DescribableTrait dependencies
        $this->setMetadataLoader($container['metadata/loader']);

        // Fulfills the TranslatorAwareTrait dependencies
        $this->setTranslator($container['translator']);

        // Fulfills the ViewableTrait dependencies
        $this->setView($container['view']);
    }

    /**
     * Generate a unique input ID.
     *
     * @return string
     */
    protected function generateInputId()
    {
        return 'input_'.uniqid();
    }

    /**
     * Create a new metadata object.
     *
     * @param  array $data Optional metadata to merge on the object.
     * @return PropertyMetadata
     */
    protected function createMetadata(array $data = null)
    {
        $class = $this->metadataClass();
        return new $class($data);
    }

    /**
     * Retrieve the class name of the metadata object.
     *
     * @return string
     */
    protected function metadataClass()
    {
        return PropertyMetadata::class;
    }

    /**
     * Allow an object to define how the key getter are called.
     *
     * @param string $key The key to get the getter from.
     * @return string The getter method name, for a given key.
     */
    protected function getter($key)
    {
        $getter = $key;
        return $this->camelize($getter);
    }

    /**
     * Allow an object to define how the key setter are called.
     *
     * @param string $key The key to get the setter from.
     * @return string The setter method name, for a given key.
     */
    protected function setter($key)
    {
        $setter = 'set_'.$key;
        return $this->camelize($setter);
    }

    /**
     * Transform a snake_case string to camelCase.
     *
     * @param string $str The snake_case string to camelize.
     * @return string The camelCase string.
     */
    private function camelize($str)
    {
        return lcfirst(implode('', array_map('ucfirst', explode('_', $str))));
    }
}
