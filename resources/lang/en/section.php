<?php

return [
    'index' => 'Sections fetched successfully.',
    'created' => 'Section created successfully.',
    'updated' => 'Section updated successfully.',
    'deleted' => 'Section deleted successfully.',
    'found' => 'Section found successfully.',

    'ar_name' => [
        'required' => 'The Arabic name is required.',
        'string' => 'The Arabic name must be a string.',
        'max' => 'The Arabic name must not exceed 255 characters.',
        'unique' => 'This Arabic name already exists in this level.',
        'regex' => 'The Arabic name must contain only Arabic letters.',
    ],

    'en_name' => [
        'required' => 'The English name is required.',
        'string' => 'The English name must be a string.',
        'max' => 'The English name must not exceed 255 characters.',
        'unique' => 'This English name already exists in this level.',
        'regex' => 'The English name must contain only English letters.',
    ],
];
