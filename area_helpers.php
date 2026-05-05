<?php

function kodus_assignment_areas(): array
{
    return [
        'Field Office',
        'Agusan del Norte',
        'Agusan del Sur',
        'Dinagat Islands',
        'Surigao del Norte',
        'Surigao del Sur',
    ];
}

function kodus_is_assignment_area(string $area): bool
{
    return in_array($area, kodus_assignment_areas(), true);
}
