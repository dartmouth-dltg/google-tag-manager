<?php
namespace GoogleTagManager;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'googletagmanager' => [
        'config' => [
            'googletagmanager_code'=>[
                'googletagmanager_code'=>''
            ],
        ],
    ],
];