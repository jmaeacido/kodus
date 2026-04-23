<?php

function kodus_profile_completion_required_fields(): array
{
    return [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'position' => 'Position',
        'positionAbr' => 'Position Abbreviation',
        'area' => 'Area of Assignment',
        'email' => 'Email',
        'username' => 'Username',
    ];
}

function kodus_profile_completion_status(array $user): array
{
    $missingFields = [];

    foreach (kodus_profile_completion_required_fields() as $field => $label) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            $missingFields[$field] = $label;
        }
    }

    return [
        'needs_attention' => $user === [] || $missingFields !== [],
        'missing_fields' => $missingFields,
    ];
}
