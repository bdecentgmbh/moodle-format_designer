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
 * Contains the default section course format output class.
 *
 * @package    format_designer
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer\output;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use context_course;
use core_courseformat\base as course_format;
use completion_info;
use format_designer\helper;
use html_writer;
use moodle_page;
use moodle_url;
use section_info;
use stdclass;

use format_designer\output\call_to_action;
use format_designer\output\cm_completion;

require_once($CFG->dirroot . '/course/format/designer/lib.php');

/**
 * Basic renderer for Designer format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \core_courseformat\output\section_renderer {
    /**
     * Course modinfo instance.
     *
     * @var course_modinfo
     */
    public $modinfo;

    /**
     * Flow delay for each module.
     *
     * @var float
     */
    public $flowdelay;

    /**
     * Constructor method, calls the parent constructor.
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Since format_designer_renderer::section_edit_control_items() only displays the 'Highlight' control
        // when editing mode is on we need to be sure that the link 'Turn editing mode on' is available for a user
        // who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
        $this->page = $page;
    }

    /**
     * Render the main course content.
     *
     * @param \format_designer\output\courseformat\content $widget
     * @return string coursecontent
     */
    public function render_content($widget) {
        global $CFG;
        $data = $widget->export_for_template($this);
        $course = $data->course;
        $this->modinfo = course_get_format($course)->get_modinfo();

        \format_designer\helper::preload_section_backgrounds($course, $this->modinfo);

        [$startid, $startclass] = $this->course_type_class($course);
        $startclass[] = ($course->coursedisplay && !$this->page->user_is_editing()) ? 'row' : '';
        // If kanban board enabled remove the row.
        if ($course->coursetype == DESIGNER_TYPE_KANBAN) {
            $startclass[] = 'kanban-board';
            $data->kanbanmode = true;
        }

        $data->startid = $startid;

        $format = course_get_format($course);

        if (method_exists($format, 'get_sectionnum')) {
            $singlesection = $format->get_sectionnum();
        } else {
            $singlesection = $format->get_section_number();
        }

        $data->issectionpageclass = $singlesection || ($course->coursedisplay == COURSE_DISPLAY_MULTIPAGE)
            ? 'section-page-layout' : '';

        if (!\format_designer\helper::has_pro()) {
            $data->headermetadata = $this->course_header_metadata_details($course);
        }

        if (\format_designer\helper::has_pro()) {
            $startclass[] = ($course->activitydisplaymode == 'bypurpose') ? 'activity-purpose-mode' : 'activity-default-mode';
        }
        $data->startclass = implode(' ', $startclass);
        return $this->render_from_template('format_designer/courseformat/content/section', $data);
    }

    /**
     * Render the course module title to add textcolor.
     *
     * @param format_designer\courseformat\content\cm\title $widget
     * @return string course module title.
     */
    public function render_title($widget) {
        global $CFG;
        $data = $widget->export_for_template($this);
        $data->elementstate = $this->get_activity_elementclasses($data->mod);
        if (\format_designer\helper::has_pro()) {
            if ($textcolor = \format_designer\options::get_option($data->mod->id, 'textcolor')) {
                $data->moduletextcolor = "color: $textcolor" . ";";
            }
        }
        return $this->render_from_template('format_designer/courseformat/content/cm/title', $data);
    }


    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link.
     *
     * @param section_info|stdClass $section The course_section entry from DB
     * @param int|stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }


    /**
     * Generate the edit control items of a section.
     *
     * @param int|stdClass $course The course entry from DB
     * @param section_info|stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return [];
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'dropdown-item editing_highlight menu-action',
                        'data-action' => 'removemarker',
                    ],
                ];
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'dropdown-item editing_highlight menu-action',
                        'data-action' => 'setmarker',
                    ],
                ];
            }
        }

        $parentcontrols = $this->parentsection_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    public function parentsection_edit_control_items($course, $section, $onsectionpage = false) {
        if (!$this->page->user_is_editing()) {
            return [];
        }

        $sectionreturn = $onsectionpage ? $section->section : null;

        $coursecontext = context_course::instance($course->id);
        $numsections = course_get_format($course)->get_last_section_number();
        $isstealth = $section->section > $numsections;

        $baseurl = course_get_url($course, $sectionreturn, ['navigation' => true]);
        $baseurl->param('sesskey', sesskey());

        $controls = [];

        if (!$isstealth && has_capability('moodle/course:update', $coursecontext)) {
            if (
                $section->section > 0 &&
                get_string_manager()->string_exists('editsection', 'format_' . $course->format)
            ) {
                $streditsection = get_string('editsection', 'format_' . $course->format);
            } else {
                $streditsection = get_string('editsection');
            }

            $controls['edit'] = [
                'url'   => new moodle_url('/course/editsection.php', ['id' => $section->id, 'sr' => $sectionreturn]),
                'icon' => 'i/settings',
                'name' => $streditsection,
                'pixattr' => ['class' => ''],
                'attr' => ['class' => 'dropdown-item edit menu-action'],
            ];
        }

        if ($section->section) {
            $url = clone($baseurl);
            if (!$isstealth) {
                if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                    if ($section->visible) { // Show the hide/show eye.
                        $strhidefromothers = get_string('hidefromothers', 'format_' . $course->format);
                        $url->param('hide', $section->section);
                        $controls['visiblity'] = [
                            'url' => $url,
                            'icon' => 'i/hide',
                            'name' => $strhidefromothers,
                            'pixattr' => ['class' => ''],
                            'attr' => ['class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'hide',
                            ],
                        ];
                    } else {
                        $strshowfromothers = get_string('showfromothers', 'format_' . $course->format);
                        $url->param('show', $section->section);
                        $controls['visiblity'] = [
                            'url' => $url,
                            'icon' => 'i/show',
                            'name' => $strshowfromothers,
                            'pixattr' => ['class' => ''],
                            'attr' => ['class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'show',
                            ],
                        ];
                    }
                }

                if (!$onsectionpage) {
                    if (has_capability('moodle/course:movesections', $coursecontext)) {
                        $url = clone($baseurl);
                        if ($section->section > 1) { // Add a arrow to move section up.
                            $url->param('section', $section->section);
                            $url->param('move', -1);
                            $strmoveup = get_string('moveup');
                            $controls['moveup'] = [
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmoveup,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'dropdown-item moveup menu-action'],
                            ];
                        }

                        $url = clone($baseurl);
                        if ($section->section < $numsections) { // Add a arrow to move section down.
                            $url->param('section', $section->section);
                            $url->param('move', 1);
                            $strmovedown = get_string('movedown');
                            $controls['movedown'] = [
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmovedown,
                                'pixattr' => ['class' => ''],
                                'attr' => ['class' => 'dropdown-item movedown menu-action'],
                            ];
                        }
                    }
                }
            }

            if (course_can_delete_section($course, $section)) {
                if (get_string_manager()->string_exists('deletesection', 'format_' . $course->format)) {
                    $strdelete = get_string('deletesection', 'format_' . $course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new moodle_url('/course/editsection.php', [
                    'id' => $section->id,
                    'sr' => $sectionreturn,
                    'delete' => 1,
                    'sesskey' => sesskey(),
                ]);
                $controls['delete'] = [
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => ['class' => ''],
                    'attr' => ['class' => 'dropdown-item editing_delete menu-action'],
                ];
            }
        }
        return $controls;
    }


    /**
     * Render the completion info icon to modify the content.
     *
     * @param \completion_info $completioninfo Completion info of the current course.
     * @return html $result Completion progress info icon.
     */
    public function completioninfo_icon(\completion_info $completioninfo) {
        global $USER;
        $result = '';
        if (
            $completioninfo->is_enabled() && !$this->page->user_is_editing() &&
            $completioninfo->is_tracked_user($USER->id) && isloggedin() && !isguestuser()
        ) {
            $result .= html_writer::tag('div', get_string('yourprogress', 'completion') .
                    $this->output->help_icon('completionicons', 'format_designer'), [
                        'id' => 'completionprogressid', 'class' => 'completionprogress',
                    ]);
        }
        return $result;
    }

    /**
     * Get course completion indicator details.
     * @param object $course
     */
    public static function get_course_completion_indicator($course) {
        global $USER;

        $context = context_course::instance($course->id);
        $completion = new \completion_info($course);
        $status = "";
        $class = "";

        // Check actual course completion first. This correctly handles conditions.
        if ($completion->is_enabled() && $completion->is_course_complete($USER->id)) {
            $status = get_string('strcompleted', 'format_designer');
            $class = "completed";
        } else {
            $courseprogress = self::criteria_progress($course, $USER->id);
            $progress = isset($courseprogress['percent']) ? $courseprogress['percent'] : 0;
            if ($progress > 0) {
                $status = get_string('strinprogress', 'format_designer');
                $class = "inprogress";
            } else if (is_enrolled($context, $USER->id)) {
                $status = get_string('strenrolled', 'format_designer');
                $class = "enrolled";
            }
        }
        return [$status, $class];
    }



    /**
     * Get course time mananagment details user current course progress and due modules course.
     *
     * @param \stdClass $course
     * @param bool $dataonly True then returns only the data.
     *
     * @return string
     */
    public function course_header_metadata_details(\stdClass $course, bool $dataonly = false) {
        global $USER, $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/locallib.php');
        $context = context_course::instance($course->id);
        if (!is_enrolled($context, $USER->id)) {
            return;
        }

        if (is_enrolled($context, $USER->id)) {
            $enrolmanager = new \course_enrolment_manager($this->page, $course);
            $enrolments = $enrolmanager->get_user_enrolments($USER->id);
            $enrolment = (!empty($enrolments)) ? current($enrolments) : [];
            $enrolstartdate = ($enrolment->timestart) ?? '';
            $enrolenddate = ($enrolment->timeend) ?? '';
        } else {
            $enrolstartdate = $course->startdate;
            $enrolenddate = $course->enddate;
        }

        // Find the date options visibility status for the time management.
        $enrolmentstartdate = (in_array('enrolmentstartdate', $course->timemanagement));
        $enrolmentenddate = (in_array('enrolmentenddate', $course->timemanagement));
        $courseduedate = (in_array('courseduedate', $course->timemanagement));
        $coursecompletiondate = (in_array('coursecompletiondate', $course->timemanagement));

        // Get course staffs to show on course header.
        $coursestaffs = helper::create()->get_course_staff_users($course);
        $data = [
            'course' => $course,
            'enrolmentstartdate' => ($enrolmentstartdate) ? $enrolstartdate : '',
            'enrolmentenddate' => $enrolmentenddate ? $enrolenddate : '',
            'coursestaffinfo' => $coursestaffs,
            'statuscoursestaffinfo' => !empty($coursestaffs) ? true : false,
            'slidearrow' => count($coursestaffs) > 1 ? true : false,
            'currentuser' => $USER->id,
            'ismessaging' => $CFG->messaging,
            'dataride' => $CFG->branch >= 500 ? "data-bs-ride" : "data-ride",
            "dataslide" => $CFG->branch >= 500 ? "data-bs-slide" : "data-slide",
            'datatoggle' => $CFG->branch >= 500 ? "data-bs-toggle" : "data-toggle",
            'datacontent' => $CFG->branch >= 500 ? "data-bs-content" : "data-content",
            'datahtml' => $CFG->branch >= 500 ? "data-bs-html" : "data-html",
            'datatrigger' => $CFG->branch >= 500 ? "data-bs-trigger" : "data-trigger",
        ];

        if (\format_designer\helper::has_pro()) {
            [$indicatorstatus, $indicatorclass] = self::get_course_completion_indicator($course);
            $data += [
                'indicatorstatus' => $indicatorstatus,
                'indicatorclass' => $indicatorclass,
                'showindicatorstatus' => isset($course->completionindicator) &&
                ($course->completionindicator == DESIGNER_CMPIND_METADATA) ? true : false,
            ];
        }

        $courseprogress = self::criteria_progress($course, $USER->id);
        $data['courseprogress'] = ($course->activityprogress) ? $courseprogress : '';
        $data['progresshelpicon'] = $this->output->help_icon('criteriaprogressinfo', 'format_designer');
        if ($courseprogress != null && $coursecompletiondate) {
            $sql = "SELECT * FROM {course_completions}
                WHERE course = :course AND userid = :userid AND timecompleted IS NOT NULL";
            $completion = $DB->get_record_sql($sql, ['userid' => $USER->id, 'course' => $course->id]);
            $data += [
                'showcompletiondate' => ($coursecompletiondate) ?: '',
                'completiondate' => (!empty($completion) ? $completion->timecompleted : ''),
            ];
        }

        // Find the course due date. only if the timetable installed.
        if (\format_designer\helper::timetable_installed()) {
            if ($courseduedate && ($timecourse = $DB->get_record('tool_timetable_course', ['course' => $course->id]))) {
                $timemanagement = new \tool_timetable\time_management($timecourse->course);
                // Get user enrolment info in course.
                $usercourseenrollinfo = $timemanagement->get_course_user_enrollment($USER->id);
                $startdate = $usercourseenrollinfo[0]['timestart'] ?? 0;
                $enddate = $usercourseenrollinfo[0]['timeend'] ?? 0;
                $coursedue = $timemanagement->calculate_course_duedate($startdate, $enddate, $USER->id);
                $data['courseduedate'] = $coursedue ?? false;
            }
        }

        $data['due'] = $this->due_overdue_activities_count();

        // Return the data of time managmenet.
        if ($dataonly) {
            return $data;
        }
        $html = $this->output->render_from_template('format_designer/course_time_management', $data);
        return $html;
    }

    /**
     * Get the count of due and overdue activities.
     *
     * @return array Count of due activities and overdue activities.
     */
    public function due_overdue_activities_count(): array {
        global $USER, $DB;
        $cache = \format_designer\helper::get_cache_object();
        $cachekey = "d_o_a_c_c{$this->modinfo->get_course()->id}_u{$USER->id}";
        if (!$cache->get($cachekey)) {
            $duecount = $overduecount = 0;
            $modinfo = $this->modinfo;
            $completion = new \completion_info($this->modinfo->get_course());
            foreach ($modinfo->sections as $modnumbers) {
                foreach ($modnumbers as $modnumber) {
                    $mod = $modinfo->cms[$modnumber];
                    if (
                        !empty($mod) && $DB->record_exists('course_modules', ['id' => $mod->id, 'deletioninprogress' => 0]) &&
                        $mod->uservisible
                    ) {
                        $data = $completion->get_data($mod, true, $USER->id);
                        if ($data->completionstate != COMPLETION_COMPLETE) {
                            $cmcompletion = new cm_completion($mod);
                            $overduecount = ($cmcompletion->is_overdue()) ? $overduecount + 1 : $overduecount;
                            $duecount = ($cmcompletion->is_due_today()) ? $duecount + 1 : $duecount;
                        }
                    }
                }
            }
            $cache->set($cachekey, ['dues' => $duecount, 'overdues' => $overduecount]);
        }
        return $cache->get($cachekey);
    }

    /**
     * Obtains a list of activities for which completion is enabled on the
     * @param object $course The list is ordered by the section order of those activities.
     * @return cm_info[] Array from $cmid => $cm of all activities with completion enabled,
     */
    public static function get_completion_activities($course) {
        $cache = \format_designer\helper::get_cache_object();
        $key = "g_c_a{$course->id}";
        if (!$cache->get($key)) {
            $modinfo = get_fast_modinfo($course);
            $result = [];
            foreach ($modinfo->get_cms() as $cm) {
                $sectioninfo = $cm->get_section_info();
                if (
                    $cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress &&
                        $cm->is_visible_on_course_page() && $sectioninfo->uservisible
                ) {
                    $result[$cm->id] = $cm;
                }
            }
            $cache->set($key, $result);
        }
        return $cache->get($key);
    }

    /**
     * Get the count section in the course.
     *
     * @param [object] $course
     * @return int
     */
    public static function get_count_sections_incourse($course) {
        $cache = \format_designer\helper::get_cache_object();
        $key = "g_c_s_ic{$course->id}";
        if (!$cache->get($key)) {
            $sections = 0;
            $modinfo = get_fast_modinfo($course);
            $realtiveactivities = isset($course->calsectionprogress) &&
                ($course->calsectionprogress == DESIGNER_PROGRESS_RELEVANTACTIVITIES) ? true : false;

            foreach ($modinfo->sections as $sectionno => $modnumbers) {
                $section = course_get_format($course)->get_section($sectionno);
                if (
                    \format_designer\options::is_vaild_section_completed(
                        $section,
                        $course,
                        $modinfo,
                        $realtiveactivities
                    ) == "true"
                ) {
                    $sections += 1;
                }
            }
            $cache->set($key, $sections);
        }
        return $cache->get($key);
    }

    /**
     * Get current course module progress. count of completion enable modules and count of completed modules.
     *
     * @param stdclass $course
     * @param int $userid
     * @return array Modules progress
     */
    public static function criteria_progress($course, $userid) {
        global $USER, $CFG;
        $cache = \format_designer\helper::get_cache_object();
        $cachekey = "c_p_c{$course->id}_u_{$userid}";
        if ($cache->get($cachekey) === false) {
            $completion = new \completion_info($course);
            if (!$completion->is_enabled()) {
                $cache->set($cachekey, null);
            }

            $modinfo = get_fast_modinfo($course);
            $context = \context_course::instance($course->id);
            // First, let's make sure completion is enabled.

            $result = [];
            $completedcriteria = [];
            $uncompletedcriteria = [];

            // Get the number of modules that support completion.
            $modules = self::get_completion_activities($course);
            $completionactivities = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);
            $complteioncourses = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE);

            $count = count($completionactivities);

            $isapplycompletioncourses = false;
            $isapplyallcriteria = false;
            if (!isset($course->calcourseprogress)) {
                $isapplycompletioncourses = true;
                $isapplyallcriteria = true;
            } else if ($course->calcourseprogress == DESIGNER_PROGRESS_CRITERIA) {
                $isapplycompletioncourses = true;
                $isapplyallcriteria = true;
            }

            // Fetch all other completion criteria types (self, date, unenrol, duration, grade, role).
            $othercriteria = [];
            if ($isapplyallcriteria) {
                $othertypes = [
                    COMPLETION_CRITERIA_TYPE_SELF,
                    COMPLETION_CRITERIA_TYPE_DATE,
                    COMPLETION_CRITERIA_TYPE_UNENROL,
                    COMPLETION_CRITERIA_TYPE_DURATION,
                    COMPLETION_CRITERIA_TYPE_GRADE,
                    COMPLETION_CRITERIA_TYPE_ROLE,
                ];
                foreach ($othertypes as $criteriatype) {
                    $criteria = $completion->get_criteria($criteriatype);
                    if ($criteria) {
                        $othercriteria = array_merge($othercriteria, $criteria);
                    }
                }
            }

            if ($isapplycompletioncourses) {
                $count += count($complteioncourses);
            }
            // Add other criteria types to the count.
            if ($isapplyallcriteria) {
                $count += count($othercriteria);
            }
            $cmidentifier = "moduleinstance";

            if (\format_designer\helper::has_pro()) {
                $format = course_get_format($course);
                $course = $format->get_course();
                if ($course->calcourseprogress == DESIGNER_PROGRESS_ALLACTIVITIES) {
                    $count = count($modules);
                    $completionactivities = $modules;
                    $cmidentifier = "id";
                    // In all-activities mode, don't include other criteria.
                    $othercriteria = [];
                    $isapplycompletioncourses = false;
                    $isapplyallcriteria = false;
                } else if ($course->calcourseprogress == DESIGNER_PROGRESS_SECTIONS) {
                    $completionactivities = [];
                    $count = self::get_count_sections_incourse($course);
                    // In sections mode, don't include other criteria.
                    $othercriteria = [];
                    $isapplycompletioncourses = false;
                    $isapplyallcriteria = false;
                }
            }

            if (!$count) {
                $cache->set($cachekey, null);
            }

            // Get the number of modules that have been completed.
            $completed = 0;
            if ($completionactivities) {
                foreach ($completionactivities as $activity) {
                    $cmid = $activity->{$cmidentifier};
                    if (isset($modules[$cmid])) {
                        $data = $completion->get_data($modules[$cmid], true, $userid);
                        $completed += ($data->completionstate == COMPLETION_COMPLETE ||
                            $data->completionstate == COMPLETION_COMPLETE_PASS) ? 1 : 0;
                        $modtooltiplink = html_writer::link(
                            $modules[$cmid]->url,
                            get_string('stractivity', 'format_designer') . ": " . $modules[$cmid]->name
                        );
                        if (
                            $data->completionstate == COMPLETION_COMPLETE ||
                            $data->completionstate == COMPLETION_COMPLETE_PASS
                        ) {
                            $completedcriteria[] = $modtooltiplink;
                        } else {
                            $uncompletedcriteria[] = $modtooltiplink;
                        }
                    }
                }
            }

            if ($isapplycompletioncourses  && $complteioncourses) {
                foreach ($complteioncourses as $coursecriteria) {
                    $courseid = $coursecriteria->courseinstance;
                    $prereqcourse = get_course($courseid);
                    $prereqcompletion = new \completion_info($prereqcourse);
                    $coursetooltiplink = html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $prereqcourse->id]),
                        get_string('strcourse', 'format_designer') . ": " . $prereqcourse->fullname
                    );
                    if ($prereqcompletion->is_course_complete($userid)) {
                        $completed += 1;
                        $completedcriteria[] = $coursetooltiplink;
                    } else {
                        $uncompletedcriteria[] = $coursetooltiplink;
                    }
                }
            }

            // Process all other completion criteria types (self, date, unenrol, duration, grade, role).
            if ($isapplyallcriteria && !empty($othercriteria)) {
                require_once($CFG->dirroot . '/completion/completion_criteria_completion.php');

                // Map criteria type constants to human-readable labels.
                $criteriatypelabels = [
                    COMPLETION_CRITERIA_TYPE_SELF => get_string('criteriaself', 'format_designer'),
                    COMPLETION_CRITERIA_TYPE_DATE => get_string('criteriadate', 'format_designer'),
                    COMPLETION_CRITERIA_TYPE_UNENROL => get_string('criteriaunenrol', 'format_designer'),
                    COMPLETION_CRITERIA_TYPE_DURATION => get_string('criteriaduration', 'format_designer'),
                    COMPLETION_CRITERIA_TYPE_GRADE => get_string('criteriagrade', 'format_designer'),
                    COMPLETION_CRITERIA_TYPE_ROLE => get_string('criteriarole', 'format_designer'),
                ];

                foreach ($othercriteria as $criterion) {
                    // Get the completion record for this criterion and user.
                    $criteriacompletion = new \completion_criteria_completion([
                        'userid' => $userid,
                        'course' => $course->id,
                        'criteriaid' => $criterion->id,
                    ]);

                    $typelabel = isset($criteriatypelabels[$criterion->criteriatype])
                        ? $criteriatypelabels[$criterion->criteriatype]
                        : get_string('criteria', 'completion');

                    $criteriadesc = $typelabel . ': ' . $criterion->get_title_detailed();

                    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                    $tooltiplink = html_writer::link($courseurl, $criteriadesc);

                    if ($criteriacompletion->is_complete()) {
                        $completed += 1;
                        $completedcriteria[] = $tooltiplink;
                    } else {
                        $uncompletedcriteria[] = $tooltiplink;
                    }
                }
            }

            if (\format_designer\helper::has_pro()) {
                $sectiontooltiplink = '';
                if (
                    isset($course->calcourseprogress) && $course->calcourseprogress == DESIGNER_PROGRESS_SECTIONS &&
                    !empty($modinfo->sections)
                ) {
                    foreach ($modinfo->sections as $sectionno => $modnumbers) {
                        $section = course_get_format($course)->get_section($sectionno);
                        if ($section->visible) {
                            $sectionname = get_section_name($course, $section);
                            $sectionurl = course_get_url($section->course, $section->section, ['navigation' => true]);
                            $sectiontooltiplink = html_writer::link(
                                $sectionurl,
                                get_string('strsection', 'format_designer') . ": " . $sectionname
                            );
                            $realtiveactivities = isset($course->calsectionprogress) &&
                                    ($course->calsectionprogress == DESIGNER_PROGRESS_RELEVANTACTIVITIES) ? true : false;
                            if (
                                \format_designer\options::is_section_completed(
                                    $section,
                                    $course,
                                    $modinfo,
                                    true,
                                    $realtiveactivities
                                )
                            ) {
                                $completed += 1;
                                $completedcriteria[] = $sectiontooltiplink;
                            } else {
                                if (
                                    \format_designer\options::is_vaild_section_completed(
                                        $section,
                                        $course,
                                        $modinfo,
                                        $realtiveactivities
                                    ) == "true"
                                ) {
                                    $uncompletedcriteria[] = $sectiontooltiplink;
                                }
                            }
                        }
                    }
                }
                $completedcriteria[] = $sectiontooltiplink;
            }

            $percent = ($count > 0) ? (($completed / $count) * 100) : 0;
            $completioncriteriahtml = '';
            $uncompletioncriteriahtml = '';

            if (!empty($completedcriteria)) {
                $completioncriteriahtml = html_writer::start_div('completion-criteria-toolblock designer-criteria-tooltip');
                    $completioncriteriahtml .= html_writer::start_div('head-block');
                        $completioncriteriahtml .= get_string('struppercompleted', 'format_designer');
                    $completioncriteriahtml .= html_writer::end_div();
                    $completioncriteriahtml .= html_writer::start_div('info-block');
                    $completioncriteriahtml .= implode("<br>", $completedcriteria);
                    $completioncriteriahtml .= html_writer::end_div();

                $completioncriteriahtml .= html_writer::end_div();
            }

            if (!empty($uncompletedcriteria)) {
                $uncompletioncriteriahtml = html_writer::start_div('uncompletion-criteria-toolblock designer-criteria-tooltip');
                $uncompletioncriteriahtml .= html_writer::start_div('head-block');
                    $uncompletioncriteriahtml .= get_string('strtodo', 'format_designer');
                $uncompletioncriteriahtml .= html_writer::end_div();
                $uncompletioncriteriahtml .= html_writer::start_div('info-block');
                    $uncompletioncriteriahtml .= implode("<br>", $uncompletedcriteria);
                $uncompletioncriteriahtml .= html_writer::end_div();
                $uncompletioncriteriahtml .= html_writer::end_div();
            }

            $cachedata = [
                'count' => $count,
                'completed' => $completed,
                'percent' => round($percent),
                'remain' => 100 - $percent,
                'completioncriteriahtml' => $completioncriteriahtml,
                'uncompletioncriteriahtml' => $uncompletioncriteriahtml,
            ];

            if (!$cache->get($cachekey)) {
                $cache->set($cachekey, $cachedata);
            }
        }
        return $cache->get($cachekey);
    }



    /**
     * Genreate the class for the sections to deifine the width of sections.
     * It contains bootstrap grid classes, for desktop, laptop and mobile.
     *
     * @param stdclass $section Record object.
     * @return string classes list.
     */
    protected function generate_section_widthclass($section) {
        if (!isset($section->tabletwidth)) {
            return '';
        }
        $tablet = isset($section->tabletwidth) ? $section->tabletwidth : '';
        $mobile = isset($section->mobilewidth) ? $section->mobilewidth : '';
        $desktop = isset($section->desktopwidth) ? $section->desktopwidth : '';

        $widthclasses = [0 => 12, 1 => 6, 2 => 4, 3 => 3, 4 => 2 ];
        $classes = [];
        foreach (['desktop' => 'md', 'tablet' => 'sm', 'mobile' => ''] as $device => $size) {
            $class = 'col-';
            $class .= ($size) ? $size . '-' : '';
            $class .= (isset($widthclasses[$$device])) ? $widthclasses[$$device] : 12;
            $classes[] = $class;
        }
        return ' ' . implode(' ', $classes);
    }

    /**
     * Find the given section layout template exists in designer. If template not available returns default section layout
     *
     * @param string $templatename
     * @return string $templatename If not exists it returns default layout.
     */
    public function is_template_exists($templatename) {
        try {
            $this->get_mustache()->loadTemplate($templatename);
        } catch (\Exception $exception) {
            debugging('Missing section mustache template: ' . $templatename);
            $templatename = 'format_designer/section_layout_default';
        }
        return $templatename;
    }

    /**
     * Course type classes.
     *
     * @param stdclass $course
     * @return void
     */
    public function course_type_class($course) {
        if (!isset($course->coursetype)) {
            return '';
        }
        if ($course->coursetype == DESIGNER_TYPE_NORMAL && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
            $class = 'course-type-normal';
        } else if ($course->coursetype == DESIGNER_TYPE_COLLAPSIBLE) {
            $class = 'course-type-collapsible';
        } else if ($course->coursetype == DESIGNER_TYPE_KANBAN) {
            $class = 'course-type-kanbanboard kanban-board';
        } else if ($course->coursetype == DESIGNER_TYPE_FLOW) {
            $class = (!$this->page->user_is_editing()) ? 'course-type-flow' : '';
        } else {
            $class = "course-type-default";
        }

        $id = isset($course->accordion) && $course->accordion && !$this->page->user_is_editing() ? 'section-course-accordion' : '';

        return [$id, [$class] ];
    }

    /**
     * Section classes based on different courses.
     *
     * @param stdclass $course
     * @param stdclass $section
     * @param cminfo $modinfo
     * @return array list of classes.
     */
    public function course_type_sectionclasses($course, $section, $modinfo) {
        $attrs = $contentattrs = [];
        $contentclass = $activityclass = '';
        $class = "";
        if ($course->coursetype == DESIGNER_TYPE_COLLAPSIBLE) {
            $attrs[] = 'data-toggle="collapse"';
            $contentattrs[] = 'data-parent="#section-course-accordion"';
            $class = "collapsible-section";
        } else if ($course->coursetype == DESIGNER_TYPE_FLOW) {
            $modnumber = isset($modinfo->sections[$section->section]) ? $modinfo->sections[$section->section] : 0;
            $class = ($modnumber > 0 ) ? 'has-modules flow-stack' : 'flow-none';
            $contentclass = 'flow-open';
            if (count($modinfo->sections) <= 1 && $section->section == '0') {
                $class .= ' flow-head-hide';
            }
        }
        return [
            'classes' => $class,
            'content' => [
                'classes' => $contentclass,
            ],
            'activity' => [
                'classes' => $activityclass,
            ],
        ];
    }

    /**
     * Render the section info.
     *
     * @param \section_info $section
     * @param stdClass $course
     * @param bool $onsectionpage
     * @param bool $sectionheader
     * @param int $sectionreturn
     * @param bool $sectioncontent
     * @return void|string
     */
    public function render_section_data(
        section_info $section,
        stdClass $course,
        $onsectionpage,
        $sectionheader = false,
        $sectionreturn = 0,
        $sectioncontent = false
    ) {
        global $CFG;
        $sectionurl = course_get_url($section->course, $section->section, ['navigation' => true]);

        if (
            \format_designer\helper::has_pro() && !$section->uservisible && $section->availableinfo &&
            !empty($section->sectioncardredirect)
        ) {
            $sectionurl = $section->sectioncardredirect;
        }

        /** @var format_designer $format */
        $format = course_get_format($course);
        $sectionstyle = '';
        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            }
            if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $sectionname = html_writer::tag('span', $this->section_title($section, $course));

        $sectionrestrict = (!$section->uservisible && $section->availableinfo) ? true : false;

        if (
            $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE && $sectionheader &&
            $format->is_section_visible($section, false)
        ) {
            $gotosection = true;
        }

        // CM LIST.
        $cmlist = [];
        $modinfo = get_fast_modinfo($course);
        $displayoptions = [];

        // Calculate to the section progress.
        $realtiveactivities = isset($course->calsectionprogress) &&
            ($course->calsectionprogress == DESIGNER_PROGRESS_RELEVANTACTIVITIES) ? true : false;
        $sectiondata = \format_designer\options::is_section_completed($section, $course, $modinfo, false, $realtiveactivities);

        [$issectioncompletion, $sectionprogress, $sectionprogresscomp] = $sectiondata;

        $sectionbackgroundstyle = '';
        $sectioncontainerwidth = '';
        $sectioncontentwidth = '';
        $sectiondesigntextcolor = '';
        $sectiondesignheader = false;
        $sectiondesignwhole = false;
        $sectioncontainerlayout = '';
        $sectioncontentlayout = '';
        $bgoverlay = false;
        $prodata = [];

        $sectionlayoutclass = 'link-layout';
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: get_config('format_designer', 'sectiontype');
        if ($sectiontype == 'list') {
            $sectionlayoutclass = "list-layout";
        } else if ($sectiontype == 'cards') {
            $sectionlayoutclass = 'card-layout';
        }

        if ($course->coursetype == DESIGNER_TYPE_FLOW) {
            $sectionlayoutclass = 'card-layout';
            $sectiontype = 'cards';
        }
        $sectioncollapsestatus = '';
        if (isset($course->initialstate) && $course->initialstate == SECTION_COLLAPSE) {
            $sectioncollapsestatus = '';
        } else {
            $sectioncollapsestatus = (isset($course->initialstate) && $course->initialstate == FIRST_EXPAND)
            ? (($section->section == 0) ? 'show' : '') : 'show';
        }
        // Disable the collapsible for kanban board.
        if (
            !($course->coursedisplay == 1 && !$onsectionpage) &&
            ($course->coursetype == DESIGNER_TYPE_COLLAPSIBLE || $course->coursetype == DESIGNER_TYPE_FLOW)
        ) {
            $sectioncollapse = true;
        }

        // IN flow if general section only available then the general heading hidden. So we need to disable the collapsible.
        if ($course->coursetype == DESIGNER_TYPE_FLOW && count($modinfo->sections) <= 1) {
            $sectioncollapsestatus = 'show';
        }

        // Calculate section width for single section format.
        $sectionwidthclass = ($course->coursedisplay && !$this->page->user_is_editing() && !$onsectionpage && $sectionheader)
            ? $this->generate_section_widthclass($section) : '';

        if ($course->coursedisplay && !$onsectionpage) {
            $sectioncollapse = false;
        }
        // Set list width for kanban board sections.
        $sectionstylerules = ($course->coursetype == DESIGNER_TYPE_KANBAN)
            ? (isset($course->listwidth) && $section->section != 0
            ? sprintf('width: %s;', $course->listwidth) : '') : '';

        $showprerequisites = ($section->section == 0) || $format->get_sectionid() ? true : false;
        $templatecontext = [
            'sectionvisible' => $format->is_section_visible($section, false),
            'sectiontype' => $sectiontype,
            'sectionlayoutclass' => $sectionlayoutclass,
            'sectionstyle' => $sectionstyle,
            'sectionreturn' => $sectionreturn,
            'sectionname' => $sectionname,
            'sectionrestrict' => $sectionrestrict,
            'courseid' => $course->id,
            'hasviewsectionprogress' => !isguestuser() ? true : false,
            'sectionprogress' => isset($sectionprogress) ? round($sectionprogress) : '',
            'sectionprogresscomp' => isset($sectionprogresscomp) ? round($sectionprogresscomp) : '',
            'sectioncategorisetitle' => $format->get_section_option($section->id, 'categorisetitle') ?? null,
            'sectionbackgroundstyle' => $sectionbackgroundstyle,
            'sectioncontainerwidth' => $sectioncontainerwidth,
            'sectioncontentwidth' => $sectioncontentwidth,
            'sectiondesignwhole' => $sectiondesignwhole,
            'showprerequisites' => $showprerequisites,
            'prerequisitesnewtab' => isset($course->prerequisitesnewtab) ? $course->prerequisitesnewtab : false,
            'sectiondesignheader' => $sectiondesignheader,
            'sectiondesigntextcolor' => $sectiondesigntextcolor,
            'sectioncontainerlayout' => $sectioncontainerlayout,
            'sectioncontentlayout' => $sectioncontentlayout,
            'sectionheader' => $sectionheader,
            'bgoverlay' => $bgoverlay,
            'issectioncompletion' => $issectioncompletion,
            'gotosection' => (isset($gotosection) ? $gotosection : false),
            'sectionurl' => $sectionurl,
            'sectioncardcontentdirect' => (\format_designer\helper::has_pro() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE)
                && !empty($section->sectioncardredirect) ? $section->sectioncardtab : '',
            'sectioncollapse' => isset($sectioncollapse) ? $sectioncollapse : false,
            'sectionshow' => $sectioncollapsestatus,
            'sectionaccordion' => isset($course->accordion) && !$this->page->user_is_editing() ? $course->accordion : false,
            'coursetype' => $this->course_type_sectionclasses($course, $section, $modinfo),
            'stylerules' => $sectionstylerules,
            'flowcourse' => isset($course->coursetype) && $course->coursetype == DESIGNER_TYPE_FLOW ? true : false,
            'maskimage' => ($format->get_section_option($section->id, 'sectiondesignermaskimage'))
                ? true : false,
            'flowsizeclass' => (isset($course->flowsize) && $course->coursetype == DESIGNER_TYPE_FLOW &&
            !$this->page->user_is_editing()) ? $this->get_flow_size($course) : '',
            'datatarget' => ($CFG->branch >= 500) ? 'data-bs-target' : 'data-target',
            'datatoggle' => ($CFG->branch >= 500) ? 'data-bs-toggle' : 'data-toggle',
        ];
        $zerotohero = $course->sectionzeroactivities;
        if ($zerotohero == DESIGNER_HERO_ZERO_HIDE && $section->section == 0 && !$this->page->user_is_editing()) {
            $templatecontext['hidesection'] = true;
        }

        if ($course->coursedisplay == COURSE_DISPLAY_MULTIPAGE && $course->coursetype == DESIGNER_TYPE_NORMAL && $sectionheader) {
            // Static cache for module icons to reduce file operations.
            static $modiconscache = [];

            $mods = [];
            $cmids = $modinfo->sections[$section->section] ?? [];

            $displayunavailableactivities = isset($course->displayunavailableactivities) ?
                $course->displayunavailableactivities : false;

            foreach ($cmids as $cmid) {
                $thismod = $modinfo->cms[$cmid];
                if (!$thismod->get_course_module_record()->deletioninprogress) {
                    if (!$thismod->is_visible_on_course_page() && !$displayunavailableactivities) {
                        continue;
                    }
                    if (
                        \format_designer\helper::has_pro() && isset($course->activitydisplaymode) &&
                        ($course->activitydisplaymode == 'bypurpose')
                    ) {
                            \local_designer\options::process_purpose_modules($mods, $course, $thismod);
                    } else {
                        if (isset($mods[$thismod->modname])) {
                            $mods[$thismod->modname]['name'] = $thismod->modplural;
                            // Use cached icon data.
                            if (isset($modiconscache[$thismod->modname])) {
                                if (isset($modiconscache[$thismod->modname]['activityimgsvg'])) {
                                    $mods[$thismod->modname]['activityimgsvg'] =
                                        $modiconscache[$thismod->modname]['activityimgsvg'];
                                } else if (isset($modiconscache[$thismod->modname]['img'])) {
                                    $mods[$thismod->modname]['img'] = $modiconscache[$thismod->modname]['img'];
                                }
                            }
                            $mods[$thismod->modname]['count']++;
                        } else {
                            $mods[$thismod->modname]['name'] = $thismod->modfullname;
                            // Cache icon on first access per module type.
                            if (!isset($modiconscache[$thismod->modname])) {
                                if (file_exists($CFG->dirroot . '/mod/' . $thismod->modname . '/pix/monologo.svg')) {
                                    $modiconscache[$thismod->modname]['activityimgsvg'] = file_get_contents(
                                        $CFG->dirroot . '/mod/' . $thismod->modname . '/pix/monologo.svg'
                                    );
                                } else if (file_exists($CFG->dirroot . '/mod/' . $thismod->modname . '/pix/icon.png')) {
                                    $modiconscache[$thismod->modname]['img'] =
                                        $CFG->wwwroot . '/mod/' . $thismod->modname . '/pix/icon.png';
                                }
                            }
                            // Copy from cache.
                            if (isset($modiconscache[$thismod->modname]['activityimgsvg'])) {
                                $mods[$thismod->modname]['activityimgsvg'] = $modiconscache[$thismod->modname]['activityimgsvg'];
                            } else if (isset($modiconscache[$thismod->modname]['img'])) {
                                $mods[$thismod->modname]['img'] = $modiconscache[$thismod->modname]['img'];
                            }
                            $mods[$thismod->modname]['count'] = 1;
                        }
                    }
                }
            }

            $templatecontext['sectioncountstatus'] = true;
            $templatecontext['sectionmodcount'] = array_values($mods);
            $templatecontext['sectionsingle'] = true;
        }

        if (\format_designer\helper::has_pro() && $showprerequisites) {
            require_once($CFG->dirroot . "/local/designer/lib.php");
            if (
                $course->displaycourseprerequisites == DESIGNER_PREREQUISITES_ABOVECOURSE &&
                class_exists('\local_designer\helper') &&
                method_exists('\local_designer\helper', 'import_prerequisites_courses')
            ) {
                $templatecontext += \local_designer\helper::import_prerequisites_courses($course);
            }
        }

        if (\format_designer\helper::has_pro()) {
            $prodata = \local_designer\options::render_section(
                $section,
                $course,
                $modinfo,
                $templatecontext
            );
        }
        if (\format_designer\helper::has_pro()) {
            $sectionbackgroundcolor = $format->get_section_option($section->id, 'sectiondesignerbackgroundcolor') ?? '';
            $templatecontext += \local_designer\courseheader::create($format)
                ->section_progress_type(round($sectionprogress), $sectionprogresscomp, $sectionbackgroundcolor);
        }
        if ($sectioncontent) {
            $contenttemplatename = 'format_designer/section_content_' . $sectiontype;
            return $this->render_from_template($contenttemplatename, $templatecontext);
        }
        $sectionclass = ' section-type-' . $sectiontype;
        $sectionclass .= ($sectionrestrict) ? 'restricted' : '';
        $sectionclass .= $sectionwidthclass;
        $sectionclass .= ($templatecontext['sectionstyle']) ?? ' ' . $templatecontext['sectionstyle'];
        $sectionclass .= isset($templatecontext['onlysummary']) && $templatecontext['onlysummary'] ? ' section-summary ' : '';
        $sectionclass .= isset($templatecontext['ishidden']) && $templatecontext['ishidden'] ? ' hidden ' : '';
        $sectionclass .= isset($templatecontext['iscurrent']) && $templatecontext['iscurrent'] ? ' current ' : '';
        $sectionclass .= isset($templatecontext['isstealth']) && $templatecontext['isstealth'] ? ' orphaned ' : '';
        $modnumber = isset($modinfo->sections[$section->section]) ? $modinfo->sections[$section->section] : 0;
        if ($templatecontext['flowcourse']) {
            $sectionclass .= ($modnumber > 0 ) ? '' : ' section-flow-none';
        }
        $style = isset($templatecontext['stylerules']) ? ' ' . $templatecontext['stylerules'] : '';

        $templatecontext['sectionhead'] = html_writer::start_tag('li', [
            'id' => 'section-' . $section->section,
            'class' => 'section main clearfix' . $sectionclass,
            'role' => 'region',
            'aria-labelledby' => "sectionid-{$section->id}-title",
            'data-sectionid' => $section->section,
            'data-sectionreturnid' => $sectionreturn,
            'data-id' => $section->id,
            'data-for' => "section",
            'data-number' => $section->section,
            'style' => $style,
        ]);
        $templatecontext += [
            'style' => $style,
            'sectionclass' => $sectionclass,
        ];
        $templatecontext['sectionend'] = html_writer::end_tag('li');
        return $templatecontext;
    }

    /**
     * Render the mod info.
     *
     * @param object $mod
     * @param int $sectionreturn
     * @param array $displayoptions
     * @param stdclass $section section record data.
     * @param array $cmdata Course module data.
     * @param string $sectiontype
     * @return void|string
     */
    public function render_course_module(
        $mod,
        $sectionreturn,
        $displayoptions = [],
        $section = null,
        $cmdata = [],
        $sectiontype = ''
    ) {
        global $DB, $USER, $CFG;
        // Static caches to reduce DB queries - persists across multiple calls within same request.
        static $modvisitscache = [];
        static $videotimecache = [];
        static $vimeovideocache = [];
        static $completioncache = []; // Add completion cache.
        static $completionbulkloaded = false; // Track if bulk loaded.

        $course = course_get_format($mod->get_course())->get_course();
        if (!$mod->is_visible_on_course_page()) {
            return [];
        }
        $dbman = $DB->get_manager();
        $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;

        // Add course type flow animation class.
        if ($course->coursetype == DESIGNER_TYPE_FLOW && !$this->page->user_is_editing()) {
            if ((isset($course->showanimation) && $course->showanimation)) {
                $modclasses .= ' flow-animation ';
                $modstyle = sprintf('animation-delay: %ss;', $this->flowdelay);
                $duration = get_config('format_designer', 'flowanimationduration');
                $modstyle .= sprintf('animation-duration: %ss;', ($duration) ? $duration : '1');
                $this->flowdelay = $this->flowdelay + 0.5;
            }
            $modclasses .= isset($course->flowsize) ? $this->get_flow_size($course) : '';
        }

        $ispopupactivities = isset($course->popupactivities) && $course->popupactivities;
        if ($ispopupactivities) {
            $class = '\\format_popups\\local\\mod_' . $mod->modname;
            if (
                class_exists($class) &&
                has_capability('format/popups:view', \context_module::instance($mod->id))
            ) {
                $modclasses .= ' popmodule ';
            }
        }

        $cmcompletion = new cm_completion($mod);
        $cmcompletionhtml = '';
        if (empty($displayoptions['hidecompletion'])) {
            if ($cmcompletion->is_visible()) {
                $cmcompletionhtml = $this->render($cmcompletion);
            }
        }
        $url = $mod->url;

        // If there is content AND a link, then display the content here.
        // (AFTER any icons). Otherwise it was displayed before.
        $cmtext = '';
        $videotime = $mod->modname == 'videotime';
        $isvideotimelabel = false;
        $videoinstance = null;
        $useactivityimagestatus = false;
        $useactivityimage = '';
        $enableactivityimage = false;

        // Cache module options early to reuse throughout function.
        $options = null;
        if (\format_designer\helper::has_pro()) {
            $options = \local_designer\options::get_options($mod->id);
            if ($mod->modname == 'videotime' && $dbman->table_exists('videotimeplugin_pro')) {
                if ($videorecord = $DB->get_record('videotimeplugin_pro', ['videotime' => $mod->instance])) {
                    if (isset($videorecord->label_mode) && $videorecord->label_mode == 2) {
                        $useactivityimage = $options->useactivityimage ?? '';
                    } else if ($videorecord->label_mode == 1) {
                        $isvideotimelabel = true;
                    }
                }
            }
            $useactivityimagestatus = ($videotime && $useactivityimage);
            $enableactivityimage = $options->useactivityimage ?? false;
        }

        if (!empty($url) && !$videotime) {
            $cmtext = $mod->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
            if (isset($videotime) && $videotime) {
                $videoinstance = $DB->get_record('videotime', ['id' => $mod->instance]);
                $cmtext = $videoinstance->intro;
            }
            $cmtextcontent = format_string($cmtext);
            $cmtextlength = get_config('format_designer', 'activitydesclength');
            $modcontent = '';
            if (!empty($cmtextcontent)) {
                if ($cmtextlength == DESIGNER_MOD_TEXT_TRIMM) {
                    $trimlenght = get_config('format_designer', 'modtrimlength');
                    if (str_word_count($cmtextcontent) > $trimlenght) {
                        $modcontenthtml = '';
                        $modcontenthtml .= html_writer::start_tag('div', ['class' => 'trim-summary']);
                        $modcontenthtml .= \format_designer\helper::modcontent_trim_char($cmtextcontent, $trimlenght);
                        $modcontenthtml .= \html_writer::link(
                            'javascript:void(0)',
                            get_string('more'),
                            ['class' => 'mod-description-action']
                        );
                        $modcontenthtml .= html_writer::end_tag('div');
                        $modcontenthtml .= html_writer::start_tag('div', ['class' => 'fullcontent-summary summary-hide']);
                        $modcontenthtml .= $cmtextcontent;
                        $modcontenthtml .= " " . \html_writer::link(
                            'javascript:void(0)',
                            get_string('less', 'format_designer'),
                            ['class' => 'mod-description-action']
                        );
                        $modcontenthtml .= html_writer::end_tag('div');
                        $modcontent = $modcontenthtml;
                    } else {
                        $modcontent = html_writer::tag('p', $cmtextcontent);
                    }
                } else {
                    $modcontent = html_writer::tag('p', $cmtextcontent);
                }
            }
        } else {
            if (!$useactivityimagestatus) {
                $modcontent = $mod->get_formatted_content(
                    ['overflowdiv' => true, 'noclean' => true]
                );
            }
        }

        // Bulk load ALL visit counts in ONE query on first call.
        static $visitsbulkloaded = false;

        if (!$visitsbulkloaded) {
            // Get all course module IDs from modinfo to use IN clause instead of LIKE.
            $modinfo = get_fast_modinfo($course);
            $cmids = array_keys($modinfo->get_cms());

            if (!empty($cmids)) {
                // Use IN clause for much better performance than LIKE on path.
                [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');

                $params['userid'] = $USER->id;
                $params['action'] = 'viewed';
                $params['target'] = 'course_module';
                // Optional: Only get logs from last 6 months to reduce dataset.
                $params['timefrom'] = time() - (6 * 30 * 24 * 60 * 60);

                $sql = "SELECT l.contextinstanceid, COUNT(*) as visitcount
                        FROM {logstore_standard_log} l
                        WHERE l.userid = :userid
                        AND l.action = :action
                        AND l.target = :target
                        AND l.contextinstanceid $insql
                        AND l.timecreated >= :timefrom
                        GROUP BY l.contextinstanceid";

                // Use get_records_sql() since we already filtered by course modules.
                $visits = $DB->get_records_sql($sql, $params);
                foreach ($visits as $visit) {
                    $modvisitscache[$visit->contextinstanceid . '_' . $USER->id] = $visit->visitcount;
                }
            }
            $visitsbulkloaded = true;
        }

        // Use cached visits count.
        $cachekey = $mod->id . '_' . $USER->id;
        $modvisits = !empty($modvisitscache[$cachekey]) ? get_string('modvisit', 'format_designer', $modvisitscache[$cachekey]) :
            get_string('notvisit', 'format_designer');

        $calltoactionhtml = $this->render(new call_to_action($mod));
        $modrestricted = ($mod->availableinfo) ?: false;

        $activitylink = html_writer::empty_tag('img', ['src' => $mod->get_icon_url(),
                'class' => 'iconlarge activityicon', 'alt' => '', 'role' => 'presentation', 'aria-hidden' => 'true',
        ]);

        if ($mod->uservisible) {
            if (empty($url)) {
                $url = $this->get_cmurl($mod);
            }
            $modiconurl = html_writer::link($url, $activitylink, ['class' => 'mod-icon-url']);
        } else {
            $modiconurl = html_writer::start_div('mod-icon-url');
            $modiconurl .= $activitylink;
            $modiconurl .= html_writer::end_div();
        }

        $videotimeduration = '';
        $durationformatted = '';
        if ($mod->modname == 'videotime') {
            if ($videoinstance === null) {
                // Use cached videotime instance.
                if (!isset($videotimecache[$mod->instance])) {
                    $videotimecache[$mod->instance] = $DB->get_record('videotime', ['id' => $mod->instance]);
                }
                $videoinstance = $videotimecache[$mod->instance];
            }
            $dbman = $DB->get_manager();
            if ($videoinstance && $dbman->table_exists('videotime_vimeo_video')) {
                // Use cached vimeo video data.
                if (!isset($vimeovideocache[$videoinstance->vimeo_url])) {
                    $vimeovideocache[$videoinstance->vimeo_url] =
                        $DB->get_record('videotime_vimeo_video', ['link' => $videoinstance->vimeo_url]);
                }
                if ($vimeovideocache[$videoinstance->vimeo_url]) {
                    $videotimeduration = $vimeovideocache[$videoinstance->vimeo_url]->duration;
                }
            }
        }

        if ($videotimeduration) {
            if ($videotimeduration >= 3600) {
                $durationformatted = gmdate('H:i:s', $videotimeduration);
            } else {
                $durationformatted = gmdate('i:s', $videotimeduration);
            }
        }

        $altcontent = $mod->get_formatted_content(
            ['overflowdiv' => true, 'noclean' => true]
        );

        $afterlink = $mod->afterlink;

        // Use cached options (already loaded earlier in the function).
        if (\format_designer\helper::has_pro() && $options) {
            if (isset($options->subcoursedisplayprogress) && $options->subcoursedisplayprogress) {
                $afterlink = "";
            }
        }

        $cmlist = [
            'id' => 'module-' . $mod->id,
            'hasname' => ($mod->name) ? true : false,
            'cm' => $mod,
            'cmid' => $mod->id,
            'modhiddenfromstudents' => (!$mod->visible) ? true : false,
            'modstealth' => $mod->is_stealth(),
            'modtype' => $mod->get_module_type_name(),
            'modclasses' => $modclasses,
            'colorclass' => $cmcompletion->get_color_class(),
            'cmurl' => $this->get_cmurl($mod),
            'cmcompletion' => $cmcompletion,
            'cmcompletionhtml' => $cmcompletionhtml,
            'calltoactionhtml' => $calltoactionhtml,
            'afterlink' => $afterlink,
            'altcontent' => $altcontent,
            'cmtext' => $cmtext,
            'isrestricted' => !empty($mod->availableinfo),
            'modcontent' => isset($modcontent) ? $modcontent : '',
            'modcontentclass' => !empty($modcontent) ? 'ismodcontent' : '',
            'modvisits' => $this->get_cmurl($mod, $options) ? $modvisits : false,
            'modiconurl' => $modiconurl,
            'modrestricted' => $modrestricted,
            'elementstate' => $this->get_activity_elementclasses($mod, $options),
            'modstyle' => isset($modstyle) ? $modstyle : '',
            'useactivityimage' => $useactivityimage,
            'duration_formatted' => $durationformatted,
            'enableactivityimage' => $enableactivityimage ?? false,
            'hascmbulk' => class_exists('core_courseformat\output\local\content\bulkedittoggler') ? true : false,
            'haspro' => \format_designer\helper::has_pro(),
        ];
        $cmlist = array_merge($cmlist, $cmdata);
        if (\format_designer\helper::has_pro()) {
            $prodata = \local_designer\options::render_course_module($mod, $cmlist, $section, $sectiontype);
            $cmlist = array_merge($cmlist, $prodata);
        }
        return $cmlist;
    }

    /**
     * Get the course index drawer with placeholder.
     *
     * The default course index is loaded after the page is ready. Format plugins can override
     * this method to provide an alternative course index.
     *
     * If the format is not compatible with the course index, this method will return an empty string.
     *
     * @param course_format $format the course format
     * @return String the course index HTML.
     */
    public function course_index_drawer(course_format $format): ?string {
        if ($format->uses_course_index()) {
            include_course_editor($format);
            return $this->render_from_template('format_designer/courseformat/courseindex/drawer', []);
        }
        return '';
    }

    /**
     * Get the course index drawer with placeholder.
     *
     * The default course index is loaded after the page is ready. Format plugins can override
     * this method to provide an alternative course index.
     *
     * If the format is not compatible with the course index, this method will return an empty string.
     *
     * @param course_format $format the course format
     * @return String the course index HTML.
     */

    /**
     * Generate the classes for the activity elements visibility classes.
     * It used to show or hide, or show, hide during the activity hover.
     * @param \modinfo $mod
     * @param object|null $options Cached module options to avoid extra DB query
     * @return void
     */
    public function get_activity_elementclasses($mod, $options = null) {
        // Use cached options if provided, otherwise fetch (for backward compatibility).
        if ($options === null) {
            $option = \format_designer\options::get_option($mod->id, 'activityelements');
        } else {
            $option = $options->activityelements ?? null;
        }

        if ($option) {
            $classes = [
                0 => 'content-hide', 1 => 'content-show', 2 => 'content-show-hover',
                3 => 'content-hide-hover', 4 => 'content-remove',
            ];

            $elementclasses = array_map(function ($v) use ($classes) {
                return (isset($classes[$v])) ? $classes[$v] : $v;
            }, (array) $option);
            return $elementclasses;
        }
        return [];
    }

    /**
     * Get course module URL.
     *
     * @param cminfo $mod Course Module Info.
     * @param object|null $options Cached module options to avoid extra DB query
     * @return string
     */
    public function get_cmurl($mod, $options = null) {
        // Use cached options if provided, otherwise fetch (for backward compatibility).
        if ($options === null && \format_designer\helper::has_pro()) {
            $options = \local_designer\options::get_options($mod->id);
        }
        if ($mod->url) {
            return $mod->url;
        } else if ($mod->modname == 'videotime' && $options && isset($options->useactivityimage) && $options->useactivityimage) {
            return new moodle_url('/mod/videotime/view.php', ['id' => $mod->id]);
        }
        return '';
    }

    /**
     * Returns the CSS classes for the activity name/content
     *
     * For items which are hidden, unavailable or stealth but should be displayed
     * to current user ($mod->is_visible_on_course_page()), we show those as dimmed.
     * Students will also see as dimmed activities names that are not yet available
     * but should still be displayed (without link) with availability info.
     *
     * @param cm_info $mod
     * @return array array of two elements ($linkclasses, $textclasses)
     */
    public function course_section_cm_classes(cm_info $mod) {
        $linkclasses = '';
        $textclasses = '';
        if ($mod->uservisible) {
            $conditionalhidden = $this->is_cm_conditionally_hidden($mod);
            $accessiblebutdim = (!$mod->visible || $conditionalhidden) &&
                has_capability('moodle/course:viewhiddenactivities', $mod->context);
            if ($accessiblebutdim) {
                $linkclasses .= ' dimmed';
                $textclasses .= ' dimmed_text';
                if ($conditionalhidden) {
                    $linkclasses .= ' conditionalhidden';
                    $textclasses .= ' conditionalhidden';
                }
            }
            if ($mod->is_stealth()) {
                // Stealth activity is the one that is not visible on course page.
                // It still may be displayed to the users who can manage it.
                $linkclasses .= ' stealth';
                $textclasses .= ' stealth';
            }
        } else {
            $linkclasses .= ' dimmed';
            $textclasses .= ' dimmed dimmed_text';
        }
        return [$linkclasses, $textclasses];
    }

    /**
     * Checks if course module has any conditions that may make it unavailable for
     * all or some of the students
     *
     * This function is internal and is only used to create CSS classes for the module name/text
     *
     * @param cm_info $mod
     * @return bool
     */
    public function is_cm_conditionally_hidden(cm_info $mod) {
        global $CFG;
        $conditionalhidden = false;
        if (!empty($CFG->enableavailability)) {
            $info = new \core_availability\info_module($mod);
            $conditionalhidden = !$info->is_available_for_all();
        }
        return $conditionalhidden;
    }


    /**
     * Get the updated rendered version of a section.
     *
     * This method will only be used when the course editor requires to get an updated cm item HTML
     * to perform partial page refresh. It will be used for supporting the course editor webservices.
     *
     * By default, the template used for update a section is the same as when it renders initially,
     * but format plugins are free to override this method to provide extra effects or so.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @return string the rendered element
     */
    public function course_section_updated(
        course_format $format,
        section_info $section
    ): string {
        $output = $this->page->get_renderer('format_designer');
        $sectionclass = $format->get_output_classname('content\\section');
        $sectionobj = new $sectionclass($format, $section);
        return $this->render_from_template(
            'format_designer/section',
            $sectionobj->export_for_template($output)
        );
    }

    /**
     * Get the updated rendered version of a cm list item.
     *
     * This method is used when an activity is duplicated or copied in on the client side without refreshing the page.
     * It replaces the course renderer course_section_cm_list_item method but it's scope is different.
     * Note that the previous method is used every time an activity is rendered, independent of it is the initial page
     * loading or an Ajax update. In this case, course_section_updated_cm_item will only be used when the course editor
     * requires to get an updated cm item HTML to perform partial page refresh. It will be used for suporting the course
     * editor webservices.
     *
     * By default, the template used for update a cm_item is the same as when it renders initially, but format plugins are
     * free to override this methos to provide extra affects or so.
     *
     * @param course_format $format the course format
     * @param section_info $section the section info
     * @param cm_info $cm the course module ionfo
     * @param array $displayoptions optional extra display options
     * @return string the rendered element
     */
    public function course_section_updated_cm_item(
        course_format $format,
        section_info $section,
        cm_info $cm,
        array $displayoptions = []
    ) {

        $cmlistclass = $format->get_output_classname('content\\section\\cmitem');
        $cmlist = new $cmlistclass($format, $section, $cm, $displayoptions);
        $output = $this->page->get_renderer('format_designer');
        $cmlistdata = $cmlist->export_for_template($this);
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: get_config('format_designer', 'sectiontype');
        $templatename = 'format_designer/cm/module_layout_' . $sectiontype;
        $prolayouts = \format_designer\helper::get_pro_layouts();
        if (in_array($sectiontype, $prolayouts)) {
            if (\format_designer\helper::has_pro()) {
                $templatename = 'layouts_' . $sectiontype . '/cm/module_layout_' . $sectiontype;
            }
        }
        return $this->render_from_template($templatename, $cmlistdata);
    }

    /**
     * Return the flow course type size classes.
     *
     * @param stdclass $course course.
     * @return string $flowsizeclass flow size class.
     */
    public function get_flow_size($course) {
        $sizeclass = '';
        if ($course->flowsize == 1) {
            $sizeclass = 'flow-card-medium ';
        } else if ($course->flowsize == 2) {
            $sizeclass = 'flow-card-large ';
        } else {
            $sizeclass = 'flow-card-small ';
        }
        $flowsizeclass = isset($course->flowsize) ? $sizeclass : '';
        return $flowsizeclass;
    }
}
