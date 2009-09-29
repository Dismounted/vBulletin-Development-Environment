<?php

error_reporting(E_ALL & ~E_NOTICE & ~8192);
define('THIS_SCRIPT', 'vde_builder');

if (is_array($argv)) {
    define('CLI_ARGS', serialize($argv));
}


chdir(dirname($_SERVER['SCRIPT_NAME']));
require('./global.php');
require_once(DIR . '/includes/vde/builder.php');
require_once(DIR . 'includes/vde/project.php');

if (defined('CLI_ARGS')) {
    $argv = unserialize(CLI_ARGS);
    $project = $argv[1];
} else {
    $project = $vbulletin->input->clean_gpc('g', 'project', TYPE_NOHTML);
}

$builder = new VDE_Builder($vbulletin);
echo $builder->build(new VDE_Project($project));