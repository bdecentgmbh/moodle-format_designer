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
 * This file contains main class for Designer course format.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/course/format/lib.php');

use core\output\inplace_editable;

/**
 * Collapsible format settings: Expand all the sections in intial state.
 */
define('SECTION_EXPAND', 1);
/**
 * Collapsible format settings: Collapse all the sections in intial state.
 */
define('SECTION_COLLAPSE', 2);
/**
 * Collapsible format settings: Expand the first the section only in intial state.
 */
define('FIRST_EXPAND', 3);

define('DESIGNER_ENABLE_POPUPACTIVITIES', 1);

define('DESIGNER_DISABLE_POPUPACTIVITIES', 0);

define('DESIGNER_TYPE_KANBAN', 1);

define('DESIGNER_TYPE_COLLAPSIBLE', 2);

define('DESIGNER_TYPE_FLOW', 3);

/**
 * Main class for the Designer course format.
 *
 * @package   format_designer
 * @copyright 2021 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_designer extends \core_courseformat\base {

    /**
     * Hide the course index bar in course pages only.
     */
    const HIDE_ON_COURSEPAGE = 1;

    /**
     * Hide the course index bar everywhere.
     */
    const HIDE_EVERYWHERE = 2;

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns true if this course format uses course index
     *
     * This function may be called without specifying the course id
     * i.e. in course_index_drawer()
     *
     * @return bool
     */
    public function uses_course_index() {
        global $PAGE;
        $course = $this->get_course();
        $index = isset($course->courseindex) ?
            ($course->courseindex == self::HIDE_EVERYWHERE ? false :
            ($course->courseindex == 1 && $PAGE->cm == null ? false : true) ) : true;
        return $index;
    }

    /**
     * Returns true if this course format uses activity indentation.
     *
     * Indentation is not supported by core formats anymore and may be deprecated in the future.
     * This method will keep a default return "true" for legacy reasons but new formats should override
     * it with a return false to prevent future deprecations.
     *
     * A message in a bottle: if indentation is finally deprecated, both behat steps i_indent_right_activity
     * and i_indent_left_activity should be removed as well. Right now no core behat uses them but indentation
     * is not officially deprecated so they are still available for the contrib formats.
     *
     * @return bool if the course format uses indentation.
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Returns true if this course format is compatible with content components.
     *
     * Using components means the content elements can watch the frontend course state and
     * react to the changes. Formats with component compatibility can have more interactions
     * without refreshing the page, like having drag and drop from the course index to reorder
     * sections and activities.
     *
     * @return bool if the format is compatible with components.
     */
    public function supports_components() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #").
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the Designer course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_designer');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode.
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * Designer format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        $courseformatoptions = self::course_format_options_list($foreditform);

        if ($foreditform) {
            $courseformatoptions['coursecompletiondate'] = [
                'label' => new lang_string('coursecompletiondate', 'format_designer'),
                'element_type' => $this->designer_completion_enabled() ? 'select' : 'hidden',
                'element_attributes' => [
                    [
                        1 => new lang_string('show'),
                        0 => new lang_string('hide'),
                    ],
                ],
                'help' => 'coursecompletiondate',
                'help_component' => 'format_designer',
                'disabledif' => [['enablecompletion', 'neq', 1]],

            ];

            if ($this->designer_completion_enabled()) {
                $courseformatoptionsedit['coursecompletiondateinfo'] = [
                    'element_type' => 'hidden',
                ];
            } else {
                $courseformatoptionsedit['coursecompletiondateinfo'] = [
                    'element_type' => 'static',
                ];
            }

            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
            // Set designer default options to course config.
            $design = \format_designer\options::get_default_options();
            foreach ($courseformatoptions as $name => $value) {
                if (isset($design->$name)) {
                    $courseformatoptions[$name]['default'] = $design->$name;
                }
                $adv = $name.'_adv';
                if (isset($design->$adv) && $design->$adv) {
                    $courseformatoptions[$name]['adv'] = true;
                }
            }
        }

        return $courseformatoptions;
    }

    /**
     * Designer course format options list.
     *
     * @param bool $foreditform
     * @return array List of format options.
     */
    public static function course_format_options_list($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'coursetype' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'popupactivities' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'popupactivitiesinfo' => [
                    'default' => get_string('popupactivitiesnotinstalled', 'format_designer'),
                    'type' => PARAM_RAW,
                    'label' => get_string('popupactivities', 'format_designer'),
                ],
                'coursedisplay' => [
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ],
                'hiddensections' => [
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ],
                'showanimation' => [
                    'default' => true,
                    'type' => PARAM_INT
                ],
                'accordion' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'initialstate' => [
                    'default' => 3,
                    'type' => PARAM_INT,
                ],
                'listwidth' => [
                    'default' => '400px',
                    'type' => PARAM_ALPHANUMEXT,
                ],
                'courseheader' => [
                    'default' => get_string('courseheader', 'format_designer'),
                    'type' => PARAM_TEXT,
                ],
                'activityprogress' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'enrolmentstartdate' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'enrolmentenddate' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'coursecompletiondate' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'coursecompletiondateinfo' => [
                    'default' => get_string('completiontrackingmissing', 'format_designer'),
                    'type' => PARAM_TEXT,
                    'label' => new lang_string('coursecompletiondate', 'format_designer'),
                ],
                'courseduedate' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ],
                'courseduedateinfo' => [
                    'default' => get_string('timemanagementmissing', 'format_designer'),
                    'type' => PARAM_RAW_TRIMMED,
                    'label' => new lang_string('courseduedate', 'format_designer'),
                ],
                'courseindex' => [
                    'default' => 0,
                    'type' => PARAM_INT
                ]
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        ],
                    ],
                ],
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                        ],
                    ],
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                    'disabledif' => [['coursetype', 'eq', DESIGNER_TYPE_KANBAN]],
                ],

                'accordion' => [
                    'label' => new lang_string('accordion', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                       array(
                           0 => new lang_string('disable'),
                           1 => new lang_string('enable')
                        )
                    ],
                    'disabledif' => [
                        ['coursetype', 'eq', DESIGNER_TYPE_KANBAN],
                        ['coursetype', 'eq', 0],
                    ]
                ],

                'initialstate' => [
                    'label' => new lang_string('initialstate', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            SECTION_EXPAND => new lang_string('expand', 'format_designer'),
                            SECTION_COLLAPSE => new lang_string('collapse', 'format_designer'),
                            FIRST_EXPAND => new lang_string('firstexpand', 'format_designer')
                        ],
                    ],
                    'disabledif' => [
                        ['coursetype', 'eq', DESIGNER_TYPE_KANBAN],
                        ['coursetype', 'eq', 0],
                    ]
                ],

                'enrolmentstartdate' => [
                    'label' => new lang_string('enrolmentstartdate', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => new lang_string('show'),
                            0 => new lang_string('hide'),
                        ],
                    ],
                    'help' => 'enrolmentstartdate',
                    'help_component' => 'format_designer',

                ],
                'enrolmentenddate' => [
                    'label' => new lang_string('enrolmentenddate', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => new lang_string('show'),
                            0 => new lang_string('hide'),
                        ],
                    ],
                    'help' => 'enrolmentenddate',
                    'help_component' => 'format_designer',

                ],
                'coursecompletiondate' => [
                    'label' => new lang_string('coursecompletiondate', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => new lang_string('show'),
                            0 => new lang_string('hide'),
                        ],
                    ],
                    'help' => 'coursecompletiondate',
                    'help_component' => 'format_designer',
                    'disabledif' => [['enablecompletion', 'neq', 1]],
                ],

                'activityprogress' => [
                    'label' => new lang_string('activityprogress', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => new lang_string('show'),
                            0 => new lang_string('hide'),
                        ],
                    ],
                    'help' => 'activityprogress',
                    'help_component' => 'format_designer',
                    'disabledif' => [['enablecompletion', 'neq', 1]],
                ],
                'coursetype' => [
                    'label' => new lang_string('coursetype', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('normal'),
                            DESIGNER_TYPE_KANBAN => new lang_string('kanbanboard', 'format_designer'),
                            DESIGNER_TYPE_COLLAPSIBLE => new lang_string('collapsiblesections', 'format_designer'),
                            DESIGNER_TYPE_FLOW => new lang_string('type_flow', 'format_designer')
                        ],
                    ],
                    'help' => 'coursetype',
                    'help_component' => 'format_designer',
                ],

                'showanimation' => [
                    'label' => new lang_string('showanimation', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        array(
                            0 => new lang_string('disable'),
                            1 => new lang_string('enable')
                        )
                    ],
                    'help' => 'showanimation',
                    'help_component' => 'format_designer',
                    'disabledif' => [['coursetype', 'neq', DESIGNER_TYPE_FLOW]]
                ],

                'courseheader' => [
                    'label' => new lang_string('courseheader', 'format_designer'),
                    'element_type' => 'header',
                ],

                'listwidth' => [
                    'label' => new lang_string('listwidth', 'format_designer'),
                    'element_type' => 'text',
                    'hideif' => ['coursetype', 'neq', DESIGNER_TYPE_KANBAN]
                ],
                'courseindex' => [
                    'label' => new lang_string('courseindex', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('show'),
                            self::HIDE_ON_COURSEPAGE => new lang_string('hideoncourses', 'format_designer'),
                            self::HIDE_EVERYWHERE => new lang_string('hideeverywhere', 'format_designer'),
                        ],
                    ],
                    'help' => 'courseindex',
                    'help_component' => 'format_designer',
                ]
            ];

            if (format_designer_popup_installed()) {
                $courseformatoptionsedit['popupactivities'] = [
                    'label' => new lang_string('popupactivities', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            DESIGNER_DISABLE_POPUPACTIVITIES => new lang_string('disable'),
                            DESIGNER_ENABLE_POPUPACTIVITIES => new lang_string('enable'),
                        ],
                    ],
                    'help' => 'popupactivities',
                    'help_component' => 'format_designer',
                ];
                $courseformatoptionsedit['popupactivitiesinfo'] = [
                    'element_type' => 'hidden',
                ];
            } else {
                $courseformatoptionsedit['popupactivitiesinfo'] = [
                    'element_type' => 'static',
                ];
                $courseformatoptionsedit['popupactivities'] = [
                    'element_type' => 'hidden',
                    'label' => get_string('popupactivities', 'format_designer'),
                ];
            }

            if (format_designer_timemanagement_installed()) {
                $courseformatoptionsedit['courseduedate'] = [
                    'label' => new lang_string('courseduedate', 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            1 => new lang_string('show'),
                            0 => new lang_string('hide'),
                        ],
                    ],
                    'help' => 'courseduedate',
                    'help_component' => 'format_designer',
                    'default' => 0
                ];
                $courseformatoptionsedit['courseduedateinfo'] = [
                    'element_type' => 'hidden',
                ];
            } else {
                $courseformatoptionsedit['courseduedate'] = [
                    'element_type' => 'hidden',
                    'label' => new lang_string('courseduedate', 'format_designer'),
                ];
                $courseformatoptionsedit['courseduedateinfo'] = [
                    'element_type' => 'static',
                    'help' => 'courseduedate',
                    'help_component' => 'format_designer',
                ];
            }
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);

        }
        return $courseformatoptions;
    }

    /**
     * Find the course completion enabled for the current course.
     *
     * @return void
     */
    public function designer_completion_enabled() {
        global $COURSE;
        if ($COURSE->enablecompletion) {
            return true;
        }
        return false;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@see course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE, $PAGE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);

        }
        if ($forsection) {
            $options = $this->section_format_options(true);
        } else {
            $options = $this->course_format_options(true);
        }
        foreach ($options as $optionname => $option) {
            if (isset($option['disabledif'])) {
                $disabledif = $option['disabledif'];
                foreach ($disabledif as $disable) {
                    if (isset($disable[2])) {
                        $mform->disabledif($optionname, $disable[0], $disable[1], $disable[2]);
                    }
                }
            }
            if (isset($option['hideif'])) {
                $hideif = $option['hideif'];
                if (isset($hideif[1])) {
                    $hide = (isset($hideif[2]))
                        ? $mform->hideif($optionname, $hideif[0], $hideif[1], $hideif[2])
                        : $mform->hideif($optionname, $hideif[0], $hideif[1]);
                }
            }
            if (isset($option['adv'])) {
                $mform->setAdvanced($optionname);
            }
        }

        if ($forsection) {
            $PAGE->requires->js_init_code('
                require(["core/config"], function(CFG) {
                    document.querySelectorAll("input[name$=\"width\"]").forEach((v) => {
                        var px = document.createElement("label");
                        px.classList.add("px-string");
                        px.innerHTML = "Px";
                        v.parentNode.append(px);
                    })

                    if (document.querySelectorAll("input[name=\"sectiontype\"]").length > 0) {
                        var sectionType = document.querySelectorAll("input[name=\"sectiontype\"]")[0].value;
                        if (sectionType !== "circles" && sectionType != "horizontal_circles"
                            && document.querySelectorAll("select[name=\"circlesize\"]").length > 0) {
                            document.querySelectorAll("select[name=\"circlesize\"]")[0].setAttribute("disabled", "disabled");
                        }
                    }
                })
            ');
        }

        return $elements;
    }

    /**
     * Definitions of the additional options that this course format uses for section
     *
     * See course_format::course_format_options() for return array definition.
     *
     * Additionally section format options may have property 'cache' set to true
     * if this option needs to be cached in get_fast_modinfo(). The 'cache' property
     * is recommended to be set only for fields used in course_format::get_section_name(),
     * course_format::extend_course_navigation() and course_format::get_view_url()
     *
     * For better performance cached options are recommended to have 'cachedefault' property
     * Unlike 'default', 'cachedefault' should be static and not access get_config().
     *
     * Regardless of value of 'cache' all options are accessed in the code as
     * $sectioninfo->OPTIONNAME
     * where $sectioninfo is instance of section_info, returned by
     * get_fast_modinfo($course)->get_section_info($sectionnum)
     * or get_fast_modinfo($course)->get_section_info_all()
     *
     * All format options for particular section are returned by calling:
     * $this->get_format_options($section);
     *
     * @param bool $foreditform
     * @return array
     */
    public function section_format_options($foreditform = false) {
        return self::section_format_options_list($foreditform);
    }

    /**
     * Config list for sections. Used in global settings and section edit.
     *
     * @param bool $foreditform
     * @return array List of section settings.
     */
    public static function section_format_options_list($foreditform) {
        global $CFG, $PAGE;
        $design = \format_designer\options::get_default_options();
        $sectionoptions = array(
            'sectiontype' => array(
                'type' => PARAM_ALPHANUMEXT,
                'label' => '',
                'element_type' => 'hidden',
                'default' => 'default',
            ),
        );
        $width = [
            0 => '100%',
            1 => '50%',
            2 => '33%',
            3 => '25%',
            4 => '20%'
        ];
        $sectionoptions['sectionlayoutheader'] = array(
            'type' => PARAM_TEXT,
            'element_type' => 'header',
            'default' => get_string('sectionlayouts', 'format_designer'),
            'label' => '',
        );

        $course = course_get_format($PAGE->course)->get_course();
        $settingspage = ($PAGE->course->id == SITEID);
        if ($settingspage || (isset($course->coursetype) && $course->coursetype != DESIGNER_TYPE_FLOW)) {
            foreach (['desktop' => 5, 'tablet' => 3, 'mobile' => 2] as $name => $size) {
                $name = $name.'width';
                $availablewidth = array_slice($width, 0, $size);
                $sectionoptions[$name] = [
                    'default' => isset($design->$name) && $foreditform ? $design->$name : 0,
                    'type' => PARAM_INT,
                    'label' => new lang_string($name, 'format_designer'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        $availablewidth
                    ],
                    'help' => $name,
                    'help_component' => 'format_designer',
                ];

                $adv = $name.'_adv';
                if (isset($design->$adv) && $design->$adv) {
                    $sectionoptions[$name]['adv'] = true;
                }
            }
        }
        // Include pro feature options for section.
        if (format_designer_has_pro()) {
            require_once($CFG->dirroot."/local/designer/lib.php");
            $prosectionoptions = local_designer_get_pro_section_options($foreditform);
            $sectionoptions = array_merge($sectionoptions, $prosectionoptions);
        }
        return $sectionoptions;
    }

    /**
     * Updates format options for a section
     *
     * Section id is expected in $data->id (or $data['id'])
     * If $data does not contain property with the option name, the option will not be updated
     *
     * @param stdClass|array $data return value from {@see moodleform::get_data()} or array with data
     * @return bool whether there were any changes to the options values
     */
    public function update_section_format_options($data) {
        global $COURSE;
        $data = (array) $data;
        if (empty($data['sectionlayoutheader'])) {
            $data['sectionlayoutheader'] = get_string('sectionlayouts', 'format_designer');
        }
        if (format_designer_has_pro()) {
            local_designer\options::update_section_format_options($data);
        }
        return $this->update_format_options($data, $data['id']);
    }

    /**
     * Updates format options for a course.
     *
     * In case if course format was changed to 'designer', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@see moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@see update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
                if ($key == 'courseduedateinfo') {
                    $data[$key] = get_string('timemanagementmissing', 'format_designer');
                }
                if ($key == 'coursecompletiondateinfo') {
                    $data[$key] = get_string('completiontrackingmissing', 'format_designer');
                }
                if ($key == 'popupactivities' && !format_designer_popup_installed()) {
                    $data[$key] = false;
                }
            }
            if (isset($data['coursetype'])) {
                if ($data['coursetype'] == DESIGNER_TYPE_KANBAN && $oldcourse['coursetype'] != DESIGNER_TYPE_KANBAN) {
                    $this->setup_kanban_layouts($oldcourse);
                }
                if ($data['coursetype'] == DESIGNER_TYPE_KANBAN) {
                    $data['coursedisplay'] = 0;
                }
            }
        } else {
            if (isset($data['coursetype']) && $data['coursetype'] == DESIGNER_TYPE_KANBAN) {
                $this->setup_kanban_layouts($data);
                $data['coursedisplay'] = 0;
            }
        }
        unset($data['courseheader']);
        unset($data['popupactivitiesinfo']);

        return $this->update_format_options($data);
    }

    /**
     * Update the kanban layouts default options when layout changed to kanban mode.
     *
     * @param array $course
     * @return void
     */
    public function setup_kanban_layouts($course) {
        global $DB;
        $sections = $DB->get_records('course_sections', ['course' => $course['id']]);
        foreach ($sections as $section) {
            if ($section->section == 0) {
                continue;
            }
            $this->set_section_option($section->id, 'sectiontype', 'cards');
            $this->set_section_option($section->id, 'layoutmobilecolumn', '1');
            $this->set_section_option($section->id, 'layouttabletcolumn', '1');
            $this->set_section_option($section->id, 'layoutdesktopcolumn', '1');
        }
    }

    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@see course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name.
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
            $editable = null, $edithint = null, $editlabel = null) {
        global $USER;
        if ($editable === null) {
            $editable = !empty($USER->editing) && has_capability('moodle/course:update',
                    context_course::instance($section->course));
        }
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_designer');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_designer', $title);
        }
        $style = '';
        if (format_designer_has_pro()) {
            if (isset($section->sectiondesignertextcolor)) {
                if ($section->sectiondesignertextcolor) {
                    $style = "color: $section->sectiondesignertextcolor" . ";";
                }
            }
        }
        $displayvalue = $title = get_section_name($section->course, $section);
        if ($linkifneeded) {
            // Display link under the section name if the course format setting is to display one section per page.
            $url = course_get_url($section->course, $section->section, array('navigation' => true));
            if ($url) {
                $displayvalue = html_writer::link($url, $title, array('style' => $style));
            }
            $itemtype = 'sectionname';
        } else {
            // If $linkifneeded==false, we never display the link (this is used when rendering the section header).
            // Itemtype 'sectionnamenl' (nl=no link) will tell the callback that link should not be rendered -
            // there is no other way callback can know where we display the section name.
            $itemtype = 'sectionnamenl';
        }
        return new \core\output\inplace_editable('format_' . $this->format, $itemtype, $section->id, $editable,
            $displayvalue, $section->name, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;
        $modinfo = $this->get_modinfo();
        if (!($section instanceof section_info)) {
            $section = $modinfo->get_section_info($section->section);
        }

        if ($action == 'refresh') {
            $renderer = $this->get_renderer($PAGE);
            return [
                'content' => $renderer->course_section_updated($this, $section),
            ];
        } else {
            return parent::section_action($section, $action, $sr);
        }
    }


    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }

    /**
     * Set any arbitrary/custom option on this format, for a section.
     *
     * @param int $sectionid Course section number to set option for.
     * @param string $name Option name.
     * @param string $value Option value.
     * @return int Option record ID.
     * @throws dml_exception
     */
    public function set_section_option(int $sectionid, string $name, string $value): int {
        global $DB;

        $common = [
            'courseid' => $this->courseid,
            'format' => 'designer',
            'sectionid' => $sectionid,
            'name' => $name
        ];

        if ($existingoption = $DB->get_record('course_format_options', $common)) {
            $existingoption->value = $value;
            $DB->update_record('course_format_options', $existingoption);
            return $existingoption->id;
        } else {
            $option = (object)$common;
            $option->value = $value;
            return $DB->insert_record('course_format_options', $option);
        }
    }

    /**
     * Get section option.
     *
     * @param int $sectionid Course section number to get option for.
     * @param string $name Option name.
     * @return string|null
     * @throws dml_exception
     */
    public function get_section_option(int $sectionid, string $name): ?string {
        global $DB;

        return $DB->get_field('course_format_options', 'value', [
            'courseid' => $this->courseid,
            'format' => 'designer',
            'sectionid' => $sectionid,
            'name' => $name
        ]) ?: null;
    }

    /**
     * Get all options for section.
     *
     * @param int $sectionid
     * @return array Options
     */
    public function get_section_options(int $sectionid): array {
        global $DB;

        return $DB->get_records_menu('course_format_options', [
            'courseid' => $this->courseid,
            'format' => 'designer',
            'sectionid' => $sectionid
        ], '', 'name, value');
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_designer_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'designer'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Format date based on format defined in settings.
 *
 * @param int $timestamp
 * @return string
 * @throws coding_exception
 * @throws dml_exception
 */
function format_designer_format_date(int $timestamp) {
    if ($format = get_config('format_designer', 'dateformat')) {
        $component = strpos($format, 'strf') === 0 ? '' : 'format_designer';
    } else {
        $format = 'usstandarddate';
        $component = 'format_designer';
    }

    return userdate($timestamp, get_string($format, $component));
}

/**
 * Cut the Course content.
 *
 * @param string $str String to trim.
 * @param int $n
 * @return string
 */
function format_designer_modcontent_trim_char($str, $n = 25) {
    if (str_word_count($str) < $n) {
        return $str;
    }
    $arrstr = explode(" ", $str);
    $slicearr = array_slice($arrstr, 0, $n);
    $strarr = implode(" ", $slicearr);
    $strarr .= '...';
    return $strarr;
}

/**
 * Check if Designer Pro is installed.
 *
 * @return bool
 */
function format_designer_has_pro() {
    global $CFG;
    if (array_key_exists('designer', core_component::get_plugin_list('local'))) {
        require_once($CFG->dirroot.'/local/designer/lib.php');
        return true;
    }
    return false;
}

/**
 * Get the designer format custom layouts.
 * @return array list of available module pro layouts.
 */
function format_designer_get_pro_layouts() {
    $layouts = array_keys(core_component::get_plugin_list('layouts'));
    return $layouts;
}

/**
 * Get section background image url.
 *
 * @param \section_info $section section info class instance.
 * @param stdclass $course Course record object.
 * @param course_modinfo $modinfo Course module info class instance.
 * @return string Section background image URL.
 */
function format_designer_get_section_background_image($section, $course, $modinfo) {
    if (!empty($section->sectiondesignerbackgroundimage)) {
        $coursecontext = context_course::instance($course->id);
        $itemid = $section->sectiondesignerbackgroundimage;
        $filearea = 'sectiondesignbackground';
        if (\format_designer\options::is_section_completed($section, $course, $modinfo, true)
            && (isset($section->sectiondesignerusecompletionbg) && $section->sectiondesignerusecompletionbg)) {
            $filearea = 'sectiondesigncompletionbackground';
            $itemid = $section->sectiondesignercompletionbg;
        }
        $files = get_file_storage()->get_area_files(
            $coursecontext->id, 'format_designer', $filearea,
            $itemid, 'itemid, filepath, filename', false);

        if (empty($files)) {
            return '';
        }
        $file = current($files);
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(), false);
        return $fileurl->out(false);
    }
}


/**
 * Serves file from sectiondesignbackground_filearea
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context Context used in the file.
 * @param string $filearea Filearea the file stored
 * @param array $args Arguments
 * @param bool $forcedownload Force download the file instead of display.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function format_designer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    require_login();
    if ($context->contextlevel != CONTEXT_COURSE && $filearea != 'sectiondesignbackground') {
        return false;
    }

    $areas = ['sectiondesignbackground', 'sectiondesigncompletionbackground'];
    if (!in_array($filearea, $areas)) {
        return false;
    }
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'format_designer', $filearea, $args[0], '/', $args[1]);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, 0, $options);
}

/**
 * Include the pro settings
 *
 * @param admin_settingspage $settings Admin format settings.
 * @return void
 */
function format_designer_include_prosettings($settings) {
    global $CFG, $DB;
    if (format_designer_has_pro() && file_exists($CFG->dirroot.'/local/designer_pro/setting.php')) {
        require_once($CFG->dirroot.'/local/designer_pro/setting.php');
    }
}


/**
 * Inject the designer elements into all moodle module settings forms.
 *
 * @param moodleform $formwrapper The moodle quickforms wrapper object.
 * @param MoodleQuickForm $mform The actual form object (required to modify the form).
 */
function format_designer_coursemodule_standard_elements($formwrapper, $mform) {
    global $CFG, $DB;
    $cm = $formwrapper->get_coursemodule();
    $course = $formwrapper->get_course();
    if ($course->format == 'designer') {
        $design = $designadv = \format_designer\options::get_default_options();
        if (isset($cm->id) && $cm->id) {
            $design = \format_designer\options::get_options($cm->id);
        }

        // Activity elements list to manage the visibility.
        $elements = ['icon', 'visits', 'calltoaction', 'title', 'description', 'modname', 'completionbadge'];
        $choice = [
            0 => get_string('hide'),
            1 => get_string('show'),
            2 => get_string('showonhover', 'format_designer'),
            3 => get_string('hideonhover', 'format_designer'),
            4 => get_string('remove'),
        ];

        $mform->addElement('header', 'moduledesign', get_string('activitydesign', 'format_designer'));
        $mform->addElement('html', get_string('activityelementsdisplay', 'format_designer'));
        foreach ($elements as $element) {
            // Module background image repeat.
            $name = 'designer_activityelements['.$element.']';
            $title = get_string('activity:'.$element, 'format_designer');
            $mform->addElement('select', $name, $title, $choice);
            $mform->setType($name, PARAM_INT);
            $mform->setDefault($name, 1);
            if (isset($design->activityelements[$element])) {
                $mform->setDefault($name, $design->activityelements[$element]);
            }
            $adv = 'activityelements_'.$element.'_adv';
            if (isset($designadv->$adv) && $designadv->$adv) {
                $mform->setAdvanced($name);
            }
        }

        // Include the pro additional module fields.
        if (format_designer_has_pro()) {
            local_designer_coursemodule_standard_element($formwrapper, $mform);
        }
    }
}


/**
 * Hook the add/edit of the course module.
 *
 * @param stdClass $data Data from the form submission.
 * @param stdClass $course The course.
 */
function format_designer_coursemodule_edit_post_actions($data, $course) {
    global $DB;
    $cmid = $data->coursemodule;
    if ($course->format == 'designer') {
        $fields = ['designer_activityelements'];
        foreach ($fields as $field) {
            if (!isset($data->$field)) {
                continue;
            }
            $name = str_replace('designer_', '', $field);
            if (isset($data->$field)) {
                if (is_array($data->$field)) {
                    $value = json_encode($data->$field);
                }
                \format_designer\options::insert_option($cmid, $course->id, $name, $value);
            }
        }
    }
    return $data;
}

/**
 * Find the time management tool installed and enabled in the learningtools.
 *
 * @return bool result of the time management plugin availability.
 */
function format_designer_timemanagement_installed() {
    global $DB, $CFG;
    $tools = \core_plugin_manager::instance()->get_subplugins_of_plugin('local_learningtools');
    if (in_array('ltool_timemanagement', array_keys($tools))) {
        $status = $DB->get_field('local_learningtools_products', 'status', ['shortname' => 'timemanagement']);
        if ($status) {
            require_once($CFG->dirroot.'/local/learningtools/ltool/timemanagement/lib.php');
        }
        return ($status) ? true : false;
    }
    return false;
}


/**
 * Fix the edit settings dropdown menu. due to the Moodle CI, can't able to add it to the styles.css
 *
 * @param moodle_page $page
 * @return void
 */
function format_designer_editsetting_style($page) {
    if ($page->user_is_editing()) {
        // Fixed the overlapping issue by make this css rule as important. Moodle CI doesn't allow important.
        $style = '.format-designer .course-content ul.designer li.section .right .dropdown .dropdown-menu {';
        $style .= 'top: -40px !important;left: auto !important;right: 40px !important;transform: none !important;';
        $style .= '}';
        $style .= '.format-designer .designer .section .activity .actions .menubar .dropdown .dropdown-menu {';
        $style .= 'top: -40px !important;left: auto !important;right: 40px !important;transform: none !important;';
        $style .= '}';
        echo html_writer::tag('style', $style, []);
    }
}

/**
 * Get modules layout class Moodle CI not allowed to add li in mustache.
 *
 * @param object $format
 * @param section_info $section
 * @return string|null
 */
function format_designer_get_module_layoutclass($format, $section) {
    $sectiontype = $format->get_section_option($section->id, 'sectiontype') ?: 'default';

    if ($sectiontype == 'list') {
        $sectionlayoutclass = " position-relative ";
    } else if ($sectiontype == 'cards') {
        $sectionlayoutclass = ' card ';
    }
    $prolayouts = format_designer_get_pro_layouts();
    if (in_array($sectiontype, $prolayouts)) {
        if (format_designer_has_pro()) {
            if ($sectiontype == 'circles') {
                $sectionlayoutclass = ' circle-layout card ';
            } else if ($sectiontype == 'horizontal_circles') {
                $sectionlayoutclass = ' horizontal_circles-layout card ';
            }
        }
    }

    return $sectionlayoutclass ?? '';
}

/**
 * Find the plugin format_popup installed.
 *
 * @return bool
 */
function format_designer_popup_installed() {
    $pluginman = \core_plugin_manager::instance();
    $plugininfo = $pluginman->get_plugin_info('format_popups');
    return !empty($plugininfo) ? true : false;
}
