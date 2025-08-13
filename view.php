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
    global $CFG, $PAGE, $DB;

    require_capability('mod/assign:grade', $context);

    $PAGE->set_url('/mod/assign/view.php', ['id' => $id, 'action' => 'downloadfiltered']);

    require_once($CFG->dirroot . '/mod/assign/gradingtable.php');
    require_once($CFG->libdir . '/csvlib.class.php');

    $perpage = 0;
    $filter = get_user_preferences('assign_filter', '');
    $page = optional_param('page', 0, PARAM_INT);
    $quickgrading = get_user_preferences('assign_quickgrading', false);
    $batchcodefilter = get_user_preferences('assign_batchcodefilter', '');
    $rowoffset = 0;

    $gradingtable = new assign_grading_table(
        $assign,
        $perpage,
        $filter,
        $batchcodefilter,
        $rowoffset,
        $quickgrading
    );

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
    error_log('Available columns: ' . print_r($gradingtable->columns, true));
    error_log('Sample row: ' . print_r(array_slice($gradingtable->rawdata, 0, 1, true), true));

    $allowed_columns = [
        'studentid',
        'picture', 
        'fullname',
        'batchcode',
        'centercode',
        'email',
        'status',
        'grade',
        'timemodified_submission',
        'onlinetext',
        'submissioncomments', 
        'timemodified_grade',
        'feedbackcomments',
        'finalgrade'
    ];
 
    $clean_headers = [];
    foreach ($allowed_columns as $colname) {
        switch ($colname) {
            case 'studentid':
                $clean_headers[] = 'Student ID';
                break;
            case 'fullname':
                $clean_headers[] = 'Full Name';
                break;
            case 'batchcode':
                $clean_headers[] = 'Batch Code';
                break;
            case 'centercode':
                $clean_headers[] = 'Center Code';
                break;
            case 'email':
                $clean_headers[] = 'Email';
                break;
            case 'status':
                $clean_headers[] = 'Status';
                break;
            case 'grade':
                $clean_headers[] = 'Grade';
                break;
            case 'timesubmitted':
            case 'timemodified_submission':
                $clean_headers[] = 'Submission Time';
                break;
            case 'timemarked':
            case 'timemodified_grade':
                $clean_headers[] = 'Grading Time';
                break;
            case 'finalgrade':
                $clean_headers[] = 'Final Grade';
                break;
            case 'onlinetext':
                $clean_headers[] = 'Onlinetext';
                break;
            case 'submissioncomments':
                $clean_headers[] = 'Submissioncomments';
                break;
            case 'feedbackcomments':
                $clean_headers[] = 'Feedbackcomments';
                break;
            default:
                $clean_headers[] = ucfirst(str_replace('_', ' ', $colname));
                break;
        }
    }

    $export = new csv_export_writer();
    $filename = clean_filename($assign->get_instance()->name . '_filtered_' . date('Y-m-d'));
    $export->set_filename($filename);
    $export->add_data($clean_headers);

    // Loop through each row of data using rawdata
    foreach ($gradingtable->rawdata as $userid => $row) {
        $clean_row = [];
        
        // Map the data directly from the row object based on your allowed columns
        foreach ($allowed_columns as $colname) {
            $value = '';
            
            switch ($colname) {
                case 'studentid':
                    $value = isset($row->studentid) ? $row->studentid : '';
                    break;
                case 'picture':
                    $value = isset($row->picture) ? $row->picture : '';
                    break;
                case 'fullname':
                    $value = isset($row->firstname) && isset($row->lastname) ? 
                            $row->firstname . ' ' . $row->lastname : '';
                    break;
                case 'batchcode':
                    $value = isset($row->batchcode) ? strip_tags($row->batchcode) : '';
                    break;
                case 'centercode':
                    $value = isset($row->centercode) ? $row->centercode : '';
                    break;
                case 'email':
                    $value = isset($row->email) ? $row->email : '';
                    break;
                case 'status':
                    $value = isset($row->status) ? $row->status : '';
                    break;
                case 'grade':
                    $value = isset($row->grade) ? $row->grade : '';
                    break;
                case 'timemodified_submission':
                case 'timesubmitted':
                    $value = isset($row->timesubmitted) ? 
                            date('Y-m-d H:i:s', $row->timesubmitted) : '';
                    break;
                case 'timemodified_grade':
                case 'timemarked':
                    $value = isset($row->timemarked) ? 
                            date('Y-m-d H:i:s', $row->timemarked) : '';
                    break;
                case 'finalgrade':
                    $value = isset($row->grade) ? $row->grade : '';
                    break;
                case 'onlinetext':
                    // Get online text submission
                    if (isset($row->submissionid) && $row->submissionid) {
                        $onlinetextdata = $DB->get_record('assignsubmission_onlinetext', 
                            ['assignment' => $assign->get_instance()->id, 'submission' => $row->submissionid]);
                        if ($onlinetextdata && !empty($onlinetextdata->onlinetext)) {
                            $value = strip_tags($onlinetextdata->onlinetext);
                            // Limit length to avoid very long text
                            if (strlen($value) > 500) {
                                $value = substr($value, 0, 500) . '...';
                            }
                        } else {
                            $value = '';
                        }
                    } else {
                        $value = '';
                    }
                    break;

                case 'submissioncomments':
                    // Get submission comments 
                    $value = ''; 
                    break;

                case 'feedbackcomments':
                    // Get feedback comments
                    if (isset($row->gradeid) && $row->gradeid) {
                        $grade = $DB->get_record('assign_grades', ['id' => $row->gradeid]);
                        if ($grade) {
                            $feedbackplugins = $assign->get_feedback_plugins();
                            $feedback = '';
                            foreach ($feedbackplugins as $plugin) {
                                if ($plugin->is_enabled() && $plugin->is_visible()) {
                                    $pluginfeedback = $plugin->text_for_gradebook($grade);
                                    if (!empty($pluginfeedback)) {
                                        $feedback .= strip_tags($pluginfeedback) . ' ';
                                    }
                                }
                            }
                            $value = trim($feedback);
                        } else {
                            $value = '';
                        }
                    } else {
                        $value = '';
                    }
                    break;
                default:
                    $value = '';
                    break;
            }
            $clean_row[] = $value;
        }
        $export->add_data($clean_row);
    }
    $export->download_file();
    exit;
}

$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_ALPHA),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $assign->get_useridlist_key_id(), PARAM_ALPHANUM));

                  if ($download && $action == 'grading') {
    require_capability('mod/assign:grade', $context);
    
    if ($download == 'csv') {
        error_log('Starting CSV download'); 
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

// Get the assign class to render the page.
//echo $assign->view(optional_param('action', '', PARAM_ALPHA));

