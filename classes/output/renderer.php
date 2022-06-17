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
use html_writer;
use moodle_page;
use moodle_url;
use section_info;
use stdclass;

use format_designer\output\call_to_action;
use format_designer\output\cm_completion;

require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/designer/lib.php');

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
        $data = $widget->export_for_template($this);
        $course = $data->course;
        $this->modinfo = course_get_format($course)->get_modinfo();

        list($startid, $startclass) = $this->course_type_class($course);
        $startclass[] = ($course->coursedisplay && !$this->page->user_is_editing()) ? 'row' : '';
        // If kanban board enabled remove the row.
        if ($course->coursetype == DESIGNER_TYPE_KANBAN) {
            $startclass[] = 'kanban-board';
            $data->kanbanmode = true;
        }
        $data->startclass = implode(' ', $startclass);
        $data->startid = $startid;

        $data->timemanagement = $this->timemanagement_details($course);

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
        if (format_designer_has_pro()) {
            require_once($CFG->dirroot. "/local/designer/lib.php");
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
                        'data-action' => 'removemarker'
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
                        'data-action' => 'setmarker'
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
            return array();
        }

        $sectionreturn = $onsectionpage ? $section->section : null;

        $coursecontext = context_course::instance($course->id);
        $numsections = course_get_format($course)->get_last_section_number();
        $isstealth = $section->section > $numsections;

        $baseurl = course_get_url($course, $sectionreturn);
        $baseurl->param('sesskey', sesskey());

        $controls = array();

        if (!$isstealth && has_capability('moodle/course:update', $coursecontext)) {
            if ($section->section > 0
                && get_string_manager()->string_exists('editsection', 'format_'.$course->format)) {
                $streditsection = get_string('editsection', 'format_'.$course->format);
            } else {
                $streditsection = get_string('editsection');
            }

            $controls['edit'] = array(
                'url'   => new moodle_url('/course/editsection.php', array('id' => $section->id, 'sr' => $sectionreturn)),
                'icon' => 'i/settings',
                'name' => $streditsection,
                'pixattr' => array('class' => ''),
                'attr' => array('class' => 'dropdown-item edit menu-action'));
        }

        if ($section->section) {
            $url = clone($baseurl);
            if (!$isstealth) {
                if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
                    if ($section->visible) { // Show the hide/show eye.
                        $strhidefromothers = get_string('hidefromothers', 'format_'.$course->format);
                        $url->param('hide', $section->section);
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/hide',
                            'name' => $strhidefromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'hide'));
                    } else {
                        $strshowfromothers = get_string('showfromothers', 'format_'.$course->format);
                        $url->param('show',  $section->section);
                        $controls['visiblity'] = array(
                            'url' => $url,
                            'icon' => 'i/show',
                            'name' => $strshowfromothers,
                            'pixattr' => array('class' => ''),
                            'attr' => array('class' => 'dropdown-item editing_showhide menu-action',
                                'data-sectionreturn' => $sectionreturn, 'data-action' => 'show'));
                    }
                }

                if (!$onsectionpage) {
                    if (has_capability('moodle/course:movesections', $coursecontext)) {
                        $url = clone($baseurl);
                        if ($section->section > 1) { // Add a arrow to move section up.
                            $url->param('section', $section->section);
                            $url->param('move', -1);
                            $strmoveup = get_string('moveup');
                            $controls['moveup'] = array(
                                'url' => $url,
                                'icon' => 'i/up',
                                'name' => $strmoveup,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'dropdown-item moveup menu-action'));
                        }

                        $url = clone($baseurl);
                        if ($section->section < $numsections) { // Add a arrow to move section down.
                            $url->param('section', $section->section);
                            $url->param('move', 1);
                            $strmovedown = get_string('movedown');
                            $controls['movedown'] = array(
                                'url' => $url,
                                'icon' => 'i/down',
                                'name' => $strmovedown,
                                'pixattr' => array('class' => ''),
                                'attr' => array('class' => 'dropdown-item movedown menu-action'));
                        }
                    }
                }
            }

            if (course_can_delete_section($course, $section)) {
                if (get_string_manager()->string_exists('deletesection', 'format_'.$course->format)) {
                    $strdelete = get_string('deletesection', 'format_'.$course->format);
                } else {
                    $strdelete = get_string('deletesection');
                }
                $url = new moodle_url('/course/editsection.php', array(
                    'id' => $section->id,
                    'sr' => $sectionreturn,
                    'delete' => 1,
                    'sesskey' => sesskey()));
                $controls['delete'] = array(
                    'url' => $url,
                    'icon' => 'i/delete',
                    'name' => $strdelete,
                    'pixattr' => array('class' => ''),
                    'attr' => array('class' => 'dropdown-item editing_delete menu-action'));
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
        if ($completioninfo->is_enabled() && !$this->page->user_is_editing()
            && $completioninfo->is_tracked_user($USER->id) && isloggedin() && !isguestuser()) {
            $result .= html_writer::tag('div', get_string('yourprogress', 'completion') .
                    $this->output->help_icon('completionicons', 'format_designer'), array(
                        'id' => 'completionprogressid', 'class' => 'completionprogress'
                    ));
        }
        return $result;
    }

    /**
     * Get course time mananagment details user current course progress and due modules course.
     *
     * @param stdclass $course
     * @return string
     */
    public function timemanagement_details(stdclass $course): string {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/enrol/locallib.php');

        $context = context_course::instance($course->id);
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
        $data = [
            'course' => $course,
            'enrolmentstartdate' => ($course->enrolmentstartdate) ? $enrolstartdate : '',
            'enrolmentenddate' => $course->enrolmentenddate ? $enrolenddate : '',
        ];
        $courseprogress = $this->activity_progress($course, $USER->id);
        $data['courseprogress'] = ($course->activityprogress) ? $courseprogress : '';

        if ($courseprogress != null) {
            $sql = "SELECT * FROM {course_completions}
                WHERE course = :course AND userid = :userid AND timecompleted IS NOT NULL";
            $completion = $DB->get_record_sql($sql, ['userid' => $USER->id, 'course' => $course->id]);
            $data += [
                'showcompletiondate' => ($course->coursecompletiondate) ?: '',
                'completiondate' => (!empty($completion) ? $completion->timecompleted : ''),
            ];
        }
        // Find the course due date. only if the timemanagement installed.
        if (format_designer_timemanagement_installed() && function_exists('ltool_timemanagement_cal_course_duedate')) {
            $coursedatesinfo = $DB->get_record('ltool_timemanagement_course', array('course' => $course->id));
            if ($course->courseduedate && $coursedatesinfo) {
                $data['courseduedate'] = ltool_timemanagement_cal_course_duedate($coursedatesinfo, $enrolstartdate);
            }
        }

        $data['due'] = $this->due_overdue_activities_count();

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
        $duecount = $overduecount = 0;
        $modinfo = $this->modinfo;
        $completion = new \completion_info($this->modinfo->get_course());

        foreach ($modinfo->sections as $modnumbers) {
            foreach ($modnumbers as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!empty($mod) && $DB->record_exists('course_modules', array('id' => $mod->id, 'deletioninprogress' => 0))
                         && $mod->uservisible) {
                    $data = $completion->get_data($mod, true, $USER->id);
                    if ($data->completionstate != COMPLETION_COMPLETE) {
                        $cmcompletion = new cm_completion($mod);
                        $overduecount = ($cmcompletion->is_overdue()) ? $overduecount + 1 : $overduecount;
                        $duecount = ($cmcompletion->is_due_today()) ? $duecount + 1 : $duecount;
                    }
                }
            }
        }
        return ['dues' => $duecount, 'overdues' => $overduecount];
    }

    /**
     * Get current course module progress. count of completion enable modules and count of completed modules.
     *
     * @param stdclass $course
     * @param int $userid
     * @return array Modules progress
     */
    protected function activity_progress($course, $userid) {
        $completion = new \completion_info($course);
        // First, let's make sure completion is enabled.
        if (!$completion->is_enabled()) {
            return null;
        }
        $result = [];

        // Get the number of modules that support completion.
        $modules = $completion->get_activities();
        $completionactivities = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY);

        $count = count($completionactivities);
        if (!$count) {
            return null;
        }
        // Get the number of modules that have been completed.
        $completed = 0;
        foreach ($completionactivities as $activity) {
            $cmid = $activity->moduleinstance;

            if (isset($modules[$cmid])) {
                $data = $completion->get_data($modules[$cmid], true, $userid);
                $completed += $data->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
            }
        }
        $percent = ($completed / $count) * 100;

        return ['count' => $count, 'completed' => $completed, 'percent' => $percent] + $result;
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
            $class .= ($size) ? $size.'-' : '';
            $class .= (isset($widthclasses[$$device])) ? $widthclasses[$$device] : 12;
            $classes[] = $class;
        }
        return ' '.implode(' ', $classes);
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
        if ($course->coursetype == DESIGNER_TYPE_COLLAPSIBLE) {
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
        $contentclass = $actvitiyclass = '';
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
                'classes' => $actvitiyclass
            ]
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
    public function render_section_data(section_info $section, stdClass $course, $onsectionpage,
        $sectionheader = false, $sectionreturn = 0, $sectioncontent = false) {

        $sectionurl = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->section]);
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

        if ($course->coursedisplay == COURSE_DISPLAY_MULTIPAGE && $sectionheader && $section->section > 0) {
            $gotosection = true;
        }

        // CM LIST.
        $cmlist = [];
        $modinfo = get_fast_modinfo($course);
        $displayoptions = [];

        // Calculate to the section progress.
        $sectiondata = \format_designer\options::is_section_completed($section, $course, $modinfo);
        list($issectioncompletion, $sectionprogress, $sectionprogresscomp) = $sectiondata;

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
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
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
        if ( !($course->coursedisplay == 1 && !$onsectionpage)
            && ($course->coursetype == DESIGNER_TYPE_COLLAPSIBLE || $course->coursetype == DESIGNER_TYPE_FLOW) ) {
            $sectioncollapse = true;
        }

        // IN flow if general section only available then the general heading hidden. So we need to disable the collapsible.
        if ($course->coursetype == DESIGNER_TYPE_FLOW && count($modinfo->sections) <= 1) {
            $sectioncollapsestatus = 'show';
        }

        // Calculate section width for single section format.
        $section->widthclass = ($course->coursedisplay && !$this->page->user_is_editing() && !$onsectionpage && $sectionheader)
            ? $this->generate_section_widthclass($section) : '';

        if ($course->coursedisplay && !$onsectionpage) {
            $sectioncollapse = false;
        }

        // Set list width for kanban board sections.
        $sectionstylerules = ($course->coursetype == DESIGNER_TYPE_KANBAN)
            ? (isset($course->listwidth) && $section->section != 0
            ? sprintf('width: %s;', $course->listwidth) : '') : '';

        $templatecontext = [
            'section' => $section,
            'sectiontype' => $sectiontype,
            'sectionlayoutclass' => $sectionlayoutclass,
            'sectionstyle' => $sectionstyle,
            'sectionreturn' => $sectionreturn,
            'sectionname' => $sectionname,
            'sectionrestrict' => $sectionrestrict,
            'courseid' => $course->id,
            'sectionprogress' => isset($sectionprogress) ? round($sectionprogress) : '',
            'sectionprogresscomp' => isset($sectionprogresscomp) ? round($sectionprogresscomp) : '',
            'sectioncategorisetitle' => isset($section->categorisetitle) ? $section->categorisetitle : '',
            'sectionbackgroundstyle' => $sectionbackgroundstyle,
            'sectioncontainerwidth' => $sectioncontainerwidth,
            'sectioncontentwidth' => $sectioncontentwidth,
            'sectiondesignwhole' => $sectiondesignwhole,
            'sectiondesignheader' => $sectiondesignheader,
            'sectiondesigntextcolor' => $sectiondesigntextcolor,
            'sectioncontainerlayout' => $sectioncontainerlayout,
            'sectioncontentlayout' => $sectioncontentlayout,
            'sectionheader' => $sectionheader,
            'bgoverlay' => $bgoverlay,
            'issectioncompletion' => $issectioncompletion,
            'gotosection' => (isset($gotosection) ? $gotosection : false),
            'sectionurl' => $sectionurl,
            'sectioncollapse' => isset($sectioncollapse) ? $sectioncollapse : false,
            'sectionshow' => $sectioncollapsestatus,
            'sectionaccordion' => isset($course->accordion) && !$this->page->user_is_editing() ? $course->accordion : false,
            'coursetype' => $this->course_type_sectionclasses($course, $section, $modinfo),
            'stylerules' => $sectionstylerules,
            'flowcourse' => isset($course->coursetype) && $course->coursetype == DESIGNER_TYPE_FLOW ? true : false,
            'maskimage' => ($section->sectiondesignermaskimage) ? true : false,
        ];

        if (format_designer_has_pro()) {
            $prodata = \local_designer\options::render_section(
                $section, $course, $modinfo, $templatecontext
            );
        }

        if ($sectioncontent) {
            $contenttemplatename = 'format_designer/section_content_' . $sectiontype;
            return $this->render_from_template($contenttemplatename, $templatecontext);
        }
        $sectionclass = ' section-type-'.$sectiontype;
        $sectionclass .= ($sectionrestrict) ? 'restricted' : '';
        $sectionclass .= $section->widthclass;
        $sectionclass .= ($templatecontext['sectionstyle']) ?? ' '.$templatecontext['sectionstyle'];
        $sectionclass .= isset($templatecontext['onlysummary']) && $templatecontext['onlysummary'] ? ' section-summary ' : '';
        $sectionclass .= isset($templatecontext['ishidden']) && $templatecontext['ishidden'] ? ' hidden ' : '';
        $sectionclass .= isset($templatecontext['iscurrent']) && $templatecontext['iscurrent'] ? ' current ' : '';
        $sectionclass .= isset($templatecontext['isstealth']) && $templatecontext['isstealth'] ? ' orphaned ' : '';
        $modnumber = isset($modinfo->sections[$section->section]) ? $modinfo->sections[$section->section] : 0;
        if ($templatecontext['flowcourse']) {
            $sectionclass .= ($modnumber > 0 ) ? '' : ' section-flow-none';
        }
        $style = isset($templatecontext['stylerules']) ? ' '.$templatecontext['stylerules'] : '';

        $templatecontext['sectionhead'] = html_writer::start_tag('li', [
            'id' => 'section-'.$section->section,
            'class' => 'section main clearfix'.$sectionclass,
            'role' => 'region',
            'aria-labelledby' => "sectionid-{$section->id}-title",
            'data-sectionid' => $section->section,
            'data-sectionreturnid' => $sectionreturn,
            'data-id' => $section->id,
            'data-for' => "section",
            'data-number' => $section->section,
            'style' => $style
        ]);
        $templatecontext += [
            'style' => $style,
            'sectionclass' => $sectionclass
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
     * @return void|string
     */
    public function render_course_module($mod, $sectionreturn, $displayoptions = [], $section=null, $cmdata=[]) {
        global $DB, $USER, $CFG;
        if (!$mod->is_visible_on_course_page()) {
            return [];
        }

        $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;

        // Add course type flow animation class.
        // TODO: check the animation settings.
        $course = course_get_format($mod->get_course())->get_course();
        if ($course->coursetype == DESIGNER_TYPE_FLOW && !$this->page->user_is_editing()) {
            if ((isset($course->showanimation) && $course->showanimation)) {
                $modclasses .= ' flow-animation ';
                $modstyle = sprintf('animation-delay: %ss;', $this->flowdelay);
                $duration = get_config('format_designer', 'flowanimationduration');
                $modstyle .= sprintf('animation-duration: %ss;', ($duration) ? $duration : '1');
                $this->flowdelay = $this->flowdelay + 0.5;
            }
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
        if (format_designer_has_pro()) {
            $useactivityimage = \format_designer\options::get_option($mod->id, 'useactivityimage');
            $videotime = ($mod->modname == 'videotime' && $useactivityimage);
        }
        if (!empty($url) || (isset($videotime) && $videotime)) {
            $cmtext = $mod->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
            if (isset($videotime) && $videotime) {
                $videotime = $DB->get_record('videotime', ['id' => $mod->instance]);
                $cmtext = $videotime->intro;
            }
            $cmtextcontent = format_string($cmtext);
            $modcontent = '';
            if (!empty($cmtextcontent)) {
                if (str_word_count($cmtextcontent) >= 23) {
                    $modcontenthtml = '';
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'trim-summary'));
                    $modcontenthtml .= format_designer_modcontent_trim_char($cmtextcontent, 24);
                    $modcontenthtml .= \html_writer::link('javascript:void(0)', get_string('more'),
                    array('class' => 'mod-description-action'));
                    $modcontenthtml .= html_writer::end_tag('div');
                    $modcontenthtml .= html_writer::start_tag('div', array('class' => 'fullcontent-summary summary-hide'));
                    $modcontenthtml .= $cmtextcontent;
                    $modcontenthtml .= " " .\html_writer::link('javascript:void(0)', get_string('less', 'format_designer'),
                    array('class' => 'mod-description-action'));
                    $modcontenthtml .= html_writer::end_tag('div');
                    $modcontent = $modcontenthtml;
                } else {
                    $modcontent = html_writer::tag('p', $cmtextcontent);
                }
            }
        }

        $modvisits = $DB->count_records('logstore_standard_log', array('contextinstanceid' => $mod->id,
            'userid' => $USER->id, 'action' => 'viewed', 'target' => 'course_module'));
        $modvisits = !empty($modvisits) ? get_string('modvisit', 'format_designer', $modvisits) :
            get_string('notvisit', 'format_designer');
        $calltoactionhtml = $this->render(new call_to_action($mod));
        $modrestricted = ($mod->availableinfo) ?: false;

        $activitylink = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                'class' => 'iconlarge activityicon', 'alt' => '', 'role' => 'presentation', 'aria-hidden' => 'true'));
        if ($mod->uservisible) {
            $modiconurl = html_writer::link($url, $activitylink, array('class' => 'mod-icon-url'));
        } else {
            $modiconurl = html_writer::start_div('mod-icon-url');
            $modiconurl .= $activitylink;
            $modiconurl .= html_writer::end_div();
        }

        $cmlist = [
            'id' => 'module-' . $mod->id,
            'cm' => $mod,
            'modtype' => $mod->get_module_type_name(),
            'modclasses' => $modclasses,
            'colorclass' => $cmcompletion->get_color_class(),
            'cmurl' => $this->get_cmurl($mod),
            'cmcompletion' => $cmcompletion,
            'cmcompletionhtml' => $cmcompletionhtml,
            'calltoactionhtml' => $calltoactionhtml,
            'afterlink' => $mod->afterlink,
            'cmtext' => $cmtext,
            'isrestricted' => !empty($mod->availableinfo),
            'modcontent' => isset($modcontent) ? $modcontent : '',
            'modcontentclass' => !empty($modcontent) ? 'ismodcontent' : '',
            'modvisits' => ($mod->url) ? $modvisits : false,
            'modiconurl' => $modiconurl,
            'modrestricted' => $modrestricted,
            'elementstate' => $this->get_activity_elementclasses($mod),
            'modstyle' => isset($modstyle) ? $modstyle : '',
        ];

        if (format_designer_has_pro()) {
            require_once($CFG->dirroot. "/local/designer/lib.php");
            $prodata = \local_designer\options::render_course_module($mod, $cmlist, $section);
            $cmlist = array_merge($cmlist, $prodata);
        }
        return $cmlist;
    }

    /**
     * Generate the classes for the activity elements visibility classes.
     * It used to show or hide, or show, hide during the activity hover.
     * @param \modinfo $mod
     * @return void
     */
    public function get_activity_elementclasses($mod) {

        $option  = \format_designer\options::get_option($mod->id, 'activityelements');
        if (!empty($option)) {
            $element = json_decode($option, true);
            $classes = [
                0 => 'content-hide', 1 => 'content-show', 2 => 'content-show-hover',
                3 => 'content-hide-hover', 4 => 'content-remove'
            ];

            $elementclasses = array_map(function($v) use ($classes) {
                return (isset($classes[$v])) ? $classes[$v] : $v;
            }, $element);
            return $elementclasses;
        }
        return [];
    }

    /**
     * Get course module URL.
     *
     * @param cminfo $mod Course Module Info.
     * @return string
     */
    public function get_cmurl($mod) {
        $options = (format_designer_has_pro()) ? \local_designer\options::get_options($mod->id) : [];
        if ($mod->url) {
            return $mod->url;
        } else if ($mod->modname == 'videotime' && $options && $options->useactivityimage) {
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
        return array($linkclasses, $textclasses);
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
        $format->set_section_number($section->section);
        return $this->render_from_template('format_designer/section',
            $sectionobj->export_for_template($output));
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
        $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';
        $templatename = 'format_designer/cm/module_layout_' . $sectiontype;
        $prolayouts = format_designer_get_pro_layouts();
        if (in_array($sectiontype, $prolayouts)) {
            if (format_designer_has_pro()) {
                $templatename = 'layouts_' . $sectiontype . '/cm/module_layout_' . $sectiontype;
            }
        }
        return $this->render_from_template($templatename, $cmlistdata);
    }
}
