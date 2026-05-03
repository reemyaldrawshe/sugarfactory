<?php

return [
    'index' => 'Users retrieved successfully',
    'created' => 'User created successfully',
    'updated' => 'User updated successfully',
    'deleted' => 'User deleted successfully',
    'found' => 'User found successfully',
    'index_error' => 'Error retrieving users',
    'create_error' => 'Error creating user',
    'update_error' => 'Error updating user',
    'delete_error' => 'Error deleting user',
    'not_found' => 'User not found',

    'search' => [
        'string' => 'Search must be a string',
        'max' => 'Search cannot exceed 255 characters',
    ],

    'name' => [
        'required' => 'User name is required',
        'string' => 'User name must be a string',
        'max' => 'User name cannot exceed 255 characters',
    ],

    'email' => [
        'required' => 'Email is required',
        'email' => 'Email must be a valid email address',
        'unique' => 'Email is already taken',
    ],

    'gender' => [
        'required' => 'Gender is required',
        'string' => 'Gender must be a string',
        'in' => 'Gender must be either male or female',
    ],

    'lang' => [
        'string' => 'Language must be a string',
        'in' => 'Language must be either ar or en',
    ],

    'password' => [
        'required' => 'Password is required',
        'string' => 'Password must be a string',
        'min' => 'Password must be at least 8 characters',
    ],

    'roles' => [
        'array' => 'Roles must be an array',
        'string' => 'Each role must be a string',
        'exists' => 'One or more selected roles do not exist',
    ],

    'per_page' => [
        'integer' => 'Per page must be an integer',
        'min' => 'Per page must be at least 1',
        'max' => 'Per page cannot exceed 100',
    ],
];

