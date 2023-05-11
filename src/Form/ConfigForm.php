<?php
namespace GoogleTagManager\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form {
    public function init() {
        $this->add([
            'name' => 'googletagmanager_code',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Google Tag Manager code', // @translate
                'info' => 'Google Tag Manager code', // @translate
            ],
            'attributes' => [
                'id' => 'googletagmanager_code',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'googletagmanager_code',
            'required' => false,
        ]);
    }
}