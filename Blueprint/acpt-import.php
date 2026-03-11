<?php
/**
 * ACPT schema import script — run via: wp eval-file /tmp/import-data/acpt-import.php
 * Handles key remapping and field patching for ACPT's strict validator.
 */
function patch_acpt_fields($fields) {
    $defaults = ['defaultValue'=>'','description'=>'','showInArchive'=>false,'isRequired'=>false,'sort'=>0,'options'=>[]];
    foreach ($fields as &$f) {
        foreach ($defaults as $k => $v) {
            if (!isset($f[$k]) || $f[$k] === null) $f[$k] = $v;
        }
        if (!empty($f['children'])) {
            $f['children'] = patch_acpt_fields($f['children']);
        }
    }
    return $fields;
}

$raw = json_decode(file_get_contents('/tmp/import-data/acpt-import.acpt'), true);
if ($raw === null) {
    WP_CLI::error('Invalid JSON in acpt-import.acpt');
}

// Remap keys from export format to import format
$keyMap = ['customPostTypes'=>'customPostType','taxonomies'=>'taxonomy','metaGroups'=>'meta','optionPages'=>'optionPage','forms'=>'form'];
$data = [];
foreach ($raw as $key => $value) {
    $data[$keyMap[$key] ?? $key] = $value;
}

// Patch meta fields — ACPT export has null values but validator uses isset() which fails on null
if (isset($data['meta'])) {
    foreach ($data['meta'] as &$group) {
        if (isset($group['boxes'])) {
            foreach ($group['boxes'] as &$box) {
                if (isset($box['fields'])) {
                    $box['fields'] = patch_acpt_fields($box['fields']);
                }
            }
        }
    }
    unset($group, $box);
}

try {
    \ACPT\Core\Repository\ImportRepository::import($data);
    WP_CLI::success('ACPT schema imported successfully.');
} catch (\Exception $e) {
    WP_CLI::error('ACPT import failed: ' . $e->getMessage());
}
