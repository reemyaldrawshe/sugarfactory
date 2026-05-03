<?php
return [
    'login_success' => 'Logged in successfully.',
    'logout_success' => 'Logged out successfully.',
    'register_success' => 'Registered successfully.',
    'failed' => 'Invalid email or password.',
    'not_authorized' => 'User is not authorized to login as ',
    'admin' => 'Admin',
    'client_wrong_credentials' => 'Email or password are not correct.',
    'admin_wrong_credentials' => 'Email or password are not correct.',
    'no_token' => 'Invalid token',
    'not_allowed' => "You don't have permission to access this point.",
    'no_permission' => "You don't have permission to access this point.",

    'name' => [
        'required' => 'Name is required.',
        'string' => 'Name must be a string.',
        'max' => 'Name must not exceed 255 characters.',
        'unique' => 'Name must be unique.',
    ],

    'email' => [
        'required' => 'Email is required.',
        'email' => 'Email must be valid.',
        'unique' => 'This email is already taken.',
        'exists' => 'This email is not registered.',
    ],

    'gender' => [
        'required' => 'Gender is required.',
        'in' => 'Gender must be either male or female.',
    ],

    'lang' => [
        'required' => 'Language is required.',
        'in' => 'Language must be either ar or en.',
    ],

    'password' => [
        'required' => 'Password is required.',
        'string' => 'Password must be a string.',
        'min' => 'Password must be at least 8 characters.',
        'confirmed' => 'Password confirmation does not match.',
    ],

    'profile_photo' => [
        'image' => 'Profile photo must be an image.',
        'mimes' => 'Profile photo must be a file of type: jpeg, png, jpg, gif, svg.',
        'max' => 'Profile photo must not exceed the allowed size.',
    ],

];
