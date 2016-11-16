<?php

namespace Charcoal\Admin\Property\Input;

use \Charcoal\Admin\Property\AbstractSelectableInput;
use Charcoal\Translation\TranslationString;

/**
 * List Builder Input Property
 *
 * Represents a control of selectable values that can be moved between two list boxes,
 * one representing selected values and the other representing unselected ones.
 *
 * Learn more about {@link https://en.wikipedia.org/wiki/List_builder List builder} control.
 */
class DualSelectInput extends AbstractSelectableInput
{
    /**
     * Whether the lists can be filtered.
     *
     * @var mixed
     */
    protected $searchable;

    /**
     * Whether options in the right-side can be moved amongst each other.
     *
     * @var boolean
     */
    protected $reorderable;

    /**
     * @var array $options
     */
    protected $options;

    /**
     * @var array $dualSelectOptions
     */
    protected $dualSelectOptions;

    /**
     * Retrieve the unselected options.
     *
     * @return Generator|array
     */
    public function unselectedChoices()
    {
        $choices = parent::choices();

        /* Filter the all options down to those *not* selected */
        foreach ($choices as $choice) {
            if (!$choice['selected']) {
                yield $choice;
            }
        }
    }

    /**
     * Retrieve the selectable options.
     *
     * @return Generator|array
     */
    public function selectedChoices()
    {
        $val = $this->propertyVal();

        if ($val !== null) {
            $val = $this->p()->parseVal($val);

            if (!$this->p()->multiple()) {
                $val = [$val];
            }

            $choices = iterator_to_array(parent::choices());

            /* Filter the all options down to those selected */
            foreach ($val as $v) {
                if (isset($choices[$v])) {
                    yield $choices[$v];
                }
            }
        }
    }

    /**
     * @return array
     */
    public function options()
    {
        $opts = $this->dualSelectOptions;

        $optionName = array_keys($opts);

        foreach ($optionName as $optName) {
            $this->options[$optName] = $opts[$optName];
        }

        if ($this->options) {
            return json_encode($this->options);
        } else {
            return [];
        }
    }

    /**
     * @return mixed
     */
    public function searchable()
    {
        if ($this->searchable === null) {
            if (isset($this->dualSelectOptions['searchable'])) {
                $searchable = $this->dualSelectOptions['searchable'];

                $label = new TranslationString([
                    'en' => 'Search…',
                    'fr' => 'Recherche…'
                ]);

                $defaultOptions = [
                    'left'  => [
                        'placeholder' => $label
                    ],
                    'right' => [
                        'placeholder' => $label
                    ]
                ];

                if (is_bool($searchable) && $searchable) {
                    $searchable = $defaultOptions;
                } elseif (is_array($searchable)) {
                    $lists = ['left', 'right'];

                    foreach ($lists as $ident) {
                        if (isset($searchable[$ident]['placeholder'])) {
                            $placeholder = $searchable[$ident]['placeholder'];
                        } elseif (isset($searchable['placeholder'])) {
                            $placeholder = $searchable['placeholder'];
                        }

                        if (isset($placeholder) && TranslationString::isTranslatable($placeholder)) {
                            $searchable[$ident]['placeholder'] = new TranslationString($placeholder);
                        } else {
                            $searchable[$ident]['placeholder'] = $label;
                        }
                    }
                } else {
                    $searchable = false;
                }
            } else {
                $searchable = false;
            }

            $this->searchable = $searchable;
        }

        return $this->searchable;
    }

    /**
     * Determine if the right-side can be manually sorted.
     *
     * @return boolean
     */
    public function reorderable()
    {
        if ($this->reorderable === null) {
            if (isset($this->dualSelectOptions['reorderable'])) {
                $this->reorderable = boolval($this->dualSelectOptions['reorderable']);
            } else {
                $this->reorderable = false;
            }
        }

        return $this->reorderable;
    }

    /**
     * @return mixed
     */
    public function dualSelectOptions()
    {
        return $this->dualSelectOptions;
    }

    /**
     * @param mixed $dualSelectOptions The dual-select options.
     * @return DualSelectInput
     */
    public function setDualSelectOptions($dualSelectOptions)
    {
        $this->dualSelectOptions = $dualSelectOptions;

        return $this;
    }

    /**
     * @return string
     */
    public function inputRawIdent()
    {
        return $this->p()->ident();
    }
}
