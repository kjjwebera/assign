<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file is the entry point to the assign module. All pages are rendered from here
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$rownum = optional_param('rownum', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$assign = new assign($context, $cm, $course);

if ($action === 'downloadfiltered') {
    global $CFG;

    require_capability('mod/assign:grade', $context);
    require_once($CFG->dirroot . '/mod/assign/gradingtable.php');
    require_once($CFG->libdir . '/csvlib.class.php');
    
    // ... [No changes to the top part of the block] ...
    
    $perpage = 0; // export all
    $filter = get_user_preferences('assign_filter', '');
    $page = optional_param('page', 0, PARAM_INT);
    $quickgrading = get_user_preferences('assign_quickgrading', false);

    $gradingtable = new assign_grading_table($assign, $perpage, $filter, $page, $quickgrading);

    $tsort = optional_param('tsort', '', PARAM_ALPHANUMEXT);
    $search = optional_param('search', '', PARAM_RAW);
    if (!empty($tsort)) {
        $gradingtable->sort = $tsort;
    }
    if (!empty($search)) {
        $gradingtable->set_filter('search', $search);
    }
    
    $gradingtable->setup();
    $gradingtable->query_db(0, false);
    
    $export = new csv_export_writer();
    $filename = clean_filename($assign->get_instance()->name . '_filtered_' . date('Y-m-d'));
    $export->set_filename($filename);

    // Set downloading mode first
    $gradingtable->is_downloading('csv');

    // Get headers - cleaned for CSV
    // Get headers - use column names directly
    $headers = array();
    foreach ($gradingtable->columns as $column => $columnname) {
        $headers[] = !empty($columnname) ? strip_tags($columnname) : ucfirst(str_replace('_', ' ', $column));
    }
    $export->add_data($headers);

    // Use the already filtered rawdata from the table
    foreach ($gradingtable->rawdata as $row) {
        $data = array();
        foreach ($gradingtable->columns as $column => $columnname) {
            $formatmethod = 'format_col_' . $column;
            if (method_exists($gradingtable, $formatmethod)) {
                $celldata = $gradingtable->$formatmethod($row);
            } else {
                $celldata = isset($row->$column) ? $row->$column : '';
            }
            // Clean HTML and entities for CSV
            $data[] = trim(html_entity_decode(strip_tags($celldata), ENT_QUOTES, 'UTF-8'));
        }
        $export->add_data($data);
    }
    
    $export->download_file();
    exit;
}

$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_ALPHA),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $assign->get_useridlist_key_id(), PARAM_ALPHANUM));

if ($download && $action == 'grading') {
    error_log('Download requested: ' . $download); // Debug line
    require_capability('mod/assign:grade', $context);
    
    if ($download == 'csv') {
        error_log('Starting CSV download'); // Debug line
        assign_download_grading_csv($assign, $cm, $context);
        exit;
    }
}

$url = new moodle_url('/mod/assign/view.php', $urlparams);
$PAGE->set_url($url);

// Update module completion status.
$assign->set_module_viewed();

// Apply overrides.
$assign->update_effective_access($USER->id);

// Get the assign class to
// render the page.
echo $assign->view(optional_param('action', '', PARAM_ALPHA));