<?php

function app_meta(): array
{
    return [
        'version' => '1.8.1',
        'codename' => 'Control Center',
        'released_on' => '2026-03-25',
    ];
}

function app_version_label(): string
{
    $meta = app_meta();
    return $meta['version'];
}

function app_release_label(): string
{
    $meta = app_meta();
    return date('M d, Y', strtotime($meta['released_on']));
}
