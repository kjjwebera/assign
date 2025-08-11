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

    // permission check: only graders can export grading data
    require_capability('mod/assign:grade', $context);

    // include required classes
    require_once($CFG->dirroot . '/mod/assign/gradingtable.php');
    require_once($CFG->libdir . '/csvlib.class.php'); // csv_export_writer

    $perpage = 0; 
    $filter = get_user_preferences('assign_filter', '');
    $page = optional_param('page', 0, PARAM_INT);
    $quickgrading = get_user_preferences('assign_quickgrading', false);

    $gradingtable = new assign_grading_table($assign, $perpage, $filter, $page, $quickgrading);

    // If the UI passed any explicit search/sort params in the URL, apply them too:
    $tsort = optional_param('tsort', '', PARAM_ALPHANUMEXT);
    $search = optional_param('search', '', PARAM_RAW);
    if (!empty($tsort)) {
        $gradingtable->sort = $tsort;
    }
    if (!empty($search)) {
        $gradingtable->set_filter('search', $search);
    }

    // Run setup and query. query_db(0,false) => all filtered rows, no pagination.
    $gradingtable->setup();
    $gradingtable->query_db(0, false);

    // Prepare CSV export
    $export = new csv_export_writer();
    $filename = clean_filename($assign->get_instance()->name . '_filtered_' . date('Y-m-d'));
    $export->set_filename($filename);

    // Add header row (change/extend columns as needed)
    $export->add_data([
        'User ID',
        'Full name',
        'ID number',
        'Email',
        'Grade',
        'Submission status',
        'Time submitted',
        'Time graded'
    ]);

    // Add data rows. $gradingtable->rawdata contains rows as the UI expects.
    foreach ($gradingtable->rawdata as $row) {
        $userid = isset($row->userid) ? $row->userid : (isset($row->id) ? $row->id : 0);

        // Some useful fields may be directly on $row (email, idnumber), but not always.
        $email = isset($row->email) ? $row->email : '';
        $idnumber = isset($row->idnumber) ? $row->idnumber : '';

        // Full name (uses firstname/lastname from $row)
        $fullname = fullname($row);

        // Get submission and grade objects to show status/time/grading info reliably.
        $submission = $assign->get_user_submission($userid, false);
        $gradeobj = $assign->get_user_grade($userid, false);

        $gradevalue = ($gradeobj && $gradeobj->grade !== null) ? $gradeobj->grade : '';
        $status = ($submission && !empty($submission->status)) ? $submission->status : '';
        $timesub = ($submission && !empty($submission->timemodified)) ? userdate($submission->timemodified) : '';
        $timegraded = ($gradeobj && !empty($gradeobj->timemodified)) ? userdate($gradeobj->timemodified) : '';

        $export->add_data([
            $userid,
            $fullname,
            $idnumber,
            $email,
            $gradevalue,
            $status,
            $timesub,
            $timegraded
        ]);
    }

    // Send file to user (Excel will open CSV fine)
    $export->download_file();
    // make sure script stops after download
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
//started batch code filter for first comment
echo $assign->view(optional_param('action', '', PARAM_ALPHA));
