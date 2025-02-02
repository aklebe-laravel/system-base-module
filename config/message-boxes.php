<?php

/**
 * Predefined message box buttons (html/javascript)
 */
return [
    '__default__'       => [
        'data-table' => [
            // delete box
            'delete'    => [
                'title'   => 'Delete Item',
                'content' => 'ask_delete_item',
                // constant names from defaultActions[] or closure
                'actions' => [
                    'system-base::cancel',
                    'system-base::delete-item',
                ],
            ],
        ],
        'form' => [
            // delete box
            'delete'    => [
                'title'   => 'Delete Item',
                'content' => 'ask_delete_item',
                // constant names from defaultActions[] or closure
                'actions' => [
                    'system-base::cancel',
                    'system-base::delete-item',
                ],
            ],
        ],

    ],
];
