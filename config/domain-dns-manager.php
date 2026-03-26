<?php

return [
    'ui' => [
        'tab_title' => 'DNS Manager',
        'mode' => [
            'easy_label' => 'Easy Mode',
            'expert_label' => 'Expert Mode',
        ],
    ],

    'add_record_modal' => [
        'title' => 'DNS Eintrag hinzufügen',
        'header_class' => 'border-bottom',
        'content_class' => '',
        'body_class' => '',
        'footer_class' => 'modal-footer',

        'type_label' => 'Typ',
        'name_label' => 'Name',

        'submit_button_label' => 'DNS Eintrag hinzufügen',
        'submit_button_class' => 'btn btn-primary w-100',

        'type_select_container_class' => 'row mb-3',
        'type_select_col_class' => 'col-md-6',

        /*
         * Options for the type dropdown (also used in expert mode).
         */
        'record_types' => [
            ['value' => 'A', 'label' => 'A - IPv4 Adresse'],
            ['value' => 'AAAA', 'label' => 'AAAA - IPv6 Adresse'],
            ['value' => 'CNAME', 'label' => 'CNAME - Weiterleitung'],
            ['value' => 'ALIAS', 'label' => 'ALIAS - Alias Record'],
            ['value' => 'MX', 'label' => 'MX - E-Mail Server'],
            ['value' => 'SRV', 'label' => 'SRV - Service Record'],
            ['value' => 'TXT', 'label' => 'TXT - Text Record'],
            ['value' => 'CAA', 'label' => 'CAA - Zertifikatsstelle'],
            ['value' => 'PTR', 'label' => 'PTR - Reverse DNS'],
            ['value' => 'TLSA', 'label' => 'TLSA - Transport Layer Security Authentication'],
        ],

        /*
         * Type-specific fields for the "DNS Eintrag hinzufügen" modal.
         *
         * Template placeholders use {{fieldKey}}.
         */
        'types' => [
            'A' => [
                'data_template' => '{{ipv4}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'ipv4',
                                'label' => 'IPv4 Adresse',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. 127.0.0.1',
                            ],
                        ],
                    ],
                ],
            ],
            'AAAA' => [
                'data_template' => '{{ipv6}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'ipv6',
                                'label' => 'IPv6 Adresse',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. ::fe80',
                            ],
                        ],
                    ],
                ],
            ],
            'CNAME' => [
                'data_template' => '{{target}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'target',
                                'label' => 'Domain',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'ALIAS' => [
                'data_template' => '{{target}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'target',
                                'label' => 'Alias',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'MX' => [
                'data_template' => '{{priority}} {{server}}',
                'blocks' => [
                    [
                        'type' => 'row',
                        'class' => 'row mb-3',
                        'fields' => [
                            [
                                'key' => 'server',
                                'label' => 'Mail Server',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. mail.example.com',
                                'colClass' => 'col-md-8',
                            ],
                            [
                                'key' => 'priority',
                                'label' => 'Priorität',
                                'component' => 'input',
                                'inputType' => 'number',
                                'placeholder' => '10',
                                'default' => 10,
                                'colClass' => 'col-md-4',
                            ],
                        ],
                    ],
                ],
            ],
            'SRV' => [
                'data_template' => '{{priority}} {{weight}} {{port}} {{target}}',
                'blocks' => [
                    [
                        'type' => 'row',
                        'class' => 'row mb-3',
                        'fields' => [
                            [
                                'key' => 'priority',
                                'label' => 'Priorität',
                                'component' => 'input',
                                'inputType' => 'number',
                                'default' => 0,
                                'colClass' => 'col-md-4',
                            ],
                            [
                                'key' => 'weight',
                                'label' => 'Gewichtung',
                                'component' => 'input',
                                'inputType' => 'number',
                                'default' => 0,
                                'colClass' => 'col-md-4',
                            ],
                            [
                                'key' => 'port',
                                'label' => 'Port',
                                'component' => 'input',
                                'inputType' => 'number',
                                'default' => 0,
                                'colClass' => 'col-md-4',
                            ],
                        ],
                    ],
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'target',
                                'label' => 'Ziel',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. server.example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'TXT' => [
                'data_template' => '{{content}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'content',
                                'label' => 'Inhalt',
                                'component' => 'textarea',
                                'rows' => 3,
                                'placeholder' => 'z.B. v=spf1 include:_spf.example.com ~all',
                            ],
                        ],
                    ],
                ],
            ],
            'CAA' => [
                'data_template' => '{{flags}} {{tag}} {{value}}',
                'blocks' => [
                    [
                        'type' => 'row',
                        'class' => 'row mb-3',
                        'fields' => [
                            [
                                'key' => 'flags',
                                'label' => 'Flags',
                                'component' => 'select',
                                'colClass' => 'col-md-3',
                                'options' => [
                                    ['value' => '0', 'text' => '0 - Nicht kritisch'],
                                    ['value' => '128', 'text' => '128 - Kritisch'],
                                ],
                            ],
                            [
                                'key' => 'tag',
                                'label' => 'Tag',
                                'component' => 'select',
                                'colClass' => 'col-md-5',
                                'options' => [
                                    ['value' => 'issue', 'text' => 'Nur konkrete Hostnamen zulassen'],
                                    ['value' => 'issuewild', 'text' => 'Nur Wildcards zulassen'],
                                    ['value' => 'iodef', 'text' => 'Verstoßmeldungen an URL senden (http:, https: oder mailto:)'],
                                ],
                            ],
                            [
                                'key' => 'value',
                                'label' => 'Wert',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. letsencrypt.org',
                                'colClass' => 'col-md-4',
                            ],
                        ],
                    ],
                ],
            ],
            'PTR' => [
                'data_template' => '{{target}}',
                'blocks' => [
                    [
                        'type' => 'stack',
                        'class' => 'mb-3',
                        'fields' => [
                            [
                                'key' => 'target',
                                'label' => 'Ziel',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'z.B. mail.example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'TLSA' => [
                'data_template' => '{{usage}} {{selector}} {{matching}} {{data}}',
                'blocks' => [
                    [
                        'type' => 'row',
                        'class' => 'row mb-3',
                        'fields' => [
                            [
                                'key' => 'usage',
                                'label' => 'Belegung',
                                'component' => 'select',
                                'colClass' => 'col-md-3',
                                'options' => [
                                    ['value' => '0', 'text' => '0 - PKIX-TA'],
                                    ['value' => '1', 'text' => '1 - PKIX-EE'],
                                    ['value' => '2', 'text' => '2 - DANE-TA'],
                                    ['value' => '3', 'text' => '3 - DANE-EE'],
                                ],
                            ],
                            [
                                'key' => 'selector',
                                'label' => 'Selektor',
                                'component' => 'select',
                                'colClass' => 'col-md-2',
                                'options' => [
                                    ['value' => '0', 'text' => '0 - Cert'],
                                    ['value' => '1', 'text' => '1 - SPKI'],
                                ],
                            ],
                            [
                                'key' => 'matching',
                                'label' => 'Zuordnungsart',
                                'component' => 'select',
                                'colClass' => 'col-md-2',
                                'options' => [
                                    ['value' => '0', 'text' => '0 - Full'],
                                    ['value' => '1', 'text' => '1 - SHA-256'],
                                    ['value' => '2', 'text' => '2 - SHA-512'],
                                ],
                            ],
                            [
                                'key' => 'data',
                                'label' => 'Daten der Zertifikatszuordnung',
                                'component' => 'input',
                                'inputType' => 'text',
                                'placeholder' => 'Hex String',
                                'colClass' => 'col-md-5',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    /*
     * Mode toggle / easy DNS / add record button labels.
     */
    'toolbar' => [
        'easy_dns_button_label' => 'Easy DNS',
        'add_record_button_label' => 'Eintrag hinzufügen',
    ],
];
