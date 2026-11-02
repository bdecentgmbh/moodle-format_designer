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
 * designer course format related unit tests.
 *
 * @package    format_designer
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_designer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * designer course format related unit tests.
 *
 * @package    format_designer
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test setup.
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_course.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        parent::setUp();

        $this->setAdminUser();
        $this->resetAfterTest(true);
    }

    /**
     * A section layout without a template, e.g. a pro layout after the pro plugin was removed, falls back to
     * the default layout template instead of crashing the course page.
     *
     * @covers \format_designer\output\renderer::is_template_exists
     */
    public function test_is_template_exists_falls_back_to_default_layout(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('format_designer');

        $existing = 'format_designer/layout/section_layout_cards';
        $this->assertEquals($existing, $renderer->is_template_exists($existing));

        $fallback = $renderer->is_template_exists('layouts_missing/layout/section_layout_missing');
        $this->assertDebuggingCalled();
        $this->assertEquals('format_designer/layout/section_layout_default', $fallback);
        $this->assertStringContainsString('designer-section-content', $renderer->render_from_template($fallback, []));
    }

    /**
     * The card width setting offers "fill the row" (the default) and "keep the column width".
     *
     * @covers ::format_designer_settings
     */
    public function test_card_width_setting(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->setAdminUser();
        $page = admin_get_root(true, true)->locate('formatsettingdesigner');
        $setting = $page->settings->cardwidth;

        $this->assertInstanceOf(\admin_setting_configselect::class, $setting);
        $this->assertEquals(['fill', 'column'], array_keys($setting->choices));
        $this->assertEquals('fill', $setting->defaultsetting);
    }

    /**
     * Course pages get a body class only when cards are set to keep their column width.
     *
     * @covers \format_designer::page_set_course
     */
    public function test_card_width_body_class(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);

        $page = new \moodle_page();
        $page->set_course($course);
        $this->assertStringNotContainsString('format-designer-cards-keep-width', $page->bodyclasses);

        set_config('cardwidth', 'column', 'format_designer');
        $page = new \moodle_page();
        $page->set_course($course);
        $this->assertStringContainsString('format-designer-cards-keep-width', $page->bodyclasses);
    }

    /**
     * Tests for format_designer::get_section_name method with default section names.
     * @covers ::get_section_name
     * @return void
     */
    public function test_get_section_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'designer'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Test get_section_name with default section names.
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            // Assert that with unmodified section names, get_section_name returns the same result as get_default_section_name.
            $this->assertEquals($courseformat->get_default_section_name($section), $courseformat->get_section_name($section));
        }
    }

    /**
     * Tests for format_designer::get_section_name method with modified section names.
     * @covers ::get_section_name_customised
     * @return void
     */
    public function test_get_section_name_customised(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'designer'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Modify section names.
        $customname = "Custom Section";
        foreach ($coursesections as $section) {
            $section->name = "$customname $section->section";
            $DB->update_record('course_sections', $section);
        }

        // Requery updated section names then test get_section_name.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            // Assert that with modified section names, get_section_name returns the modified section name.
            $this->assertEquals($section->name, $courseformat->get_section_name($section));
        }
    }

    /**
     * Tests for format_designer::get_default_section_name.
     * @covers ::get_default_section_name
     * @return void
     */
    public function test_get_default_section_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'designer'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Test get_default_section_name with default section names.
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            if ($section->section == 0) {
                $sectionname = get_string('section0name', 'format_designer');
                $this->assertEquals($sectionname, $courseformat->get_default_section_name($section));
            } else {
                $sectionname = get_string('sectionname', 'format_designer') . ' ' . $section->section;
                $this->assertEquals($sectionname, $courseformat->get_default_section_name($section));
            }
        }
    }

    /**
     * Test web service updating section name.
     * @covers \core_external::update_inplace_editable
     * @return void
     */
    public function test_update_inplace_editable(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/lib/external/externallib.php');

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 5, 'format' => 'designer'],
            ['createsections' => true]
        );
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call webservice without necessary permissions.
        try {
            \core_external::update_inplace_editable('format_designer', 'sectionname', $section->id, 'New section name');
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertEquals(
                'Course or activity not accessible. (Not enrolled)',
                $e->getMessage()
            );
        }

        // Change to teacher and make sure that section name can be updated using web service update_inplace_editable().
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);

        $res = \core_external::update_inplace_editable('format_designer', 'sectionname', $section->id, 'New section name');
        if (class_exists('\core_external\external_api')) {
            $res = \core_external\external_api::clean_returnvalue(\core_external::update_inplace_editable_returns(), $res);
        } else {
            $res = \external_api::clean_returnvalue(\core_external::update_inplace_editable_returns(), $res);
        }
        $this->assertEquals('New section name', $res['value']);
        $this->assertEquals('New section name', $DB->get_field('course_sections', 'name', ['id' => $section->id]));
    }

    /**
     * Test callback updating section name.
     * @covers ::inplace_editable
     * @return void
     */
    public function test_inplace_editable(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 5, 'format' => 'designer'],
            ['createsections' => true]
        );
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);
        $this->setUser($user);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call callback format_designer_inplace_editable() directly.
        $tmpl = component_callback('format_designer', 'inplace_editable', ['sectionname', $section->id, 'Rename me again']);
        $this->assertInstanceOf('core\output\inplace_editable', $tmpl);
        $res = $tmpl->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals('Rename me again', $res['value']);
        $this->assertEquals('Rename me again', $DB->get_field('course_sections', 'name', ['id' => $section->id]));

        // Try updating using callback from mismatching course format.
        try {
            component_callback('format_weeks', 'inplace_editable', ['sectionname', $section->id, 'New name']);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertEquals(1, preg_match('/^Can\'t find data record in database/', $e->getMessage()));
        }
    }

    /**
     * Test get_default_course_enddate.
     * @covers ::get_default_course_enddate
     * @return void
     */
    public function test_default_course_enddate(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $this->setTimezone('UTC');

        $params = ['format' => 'designer', 'numsections' => 5, 'startdate' => 1445644800];
        $course = $this->getDataGenerator()->create_course($params);
        $category = $DB->get_record('course_categories', ['id' => $course->category]);

        $args = [
            'course' => $course,
            'category' => $category,
            'editoroptions' => [
                'context' => \context_course::instance($course->id),
                'subdirs' => 0,
            ],
            'returnto' => new \moodle_url('/'),
            'returnurl' => new \moodle_url('/'),
        ];

        $courseform = new \testable_course_edit_form(null, $args);
        $courseform->definition_after_data();

        $enddate = $params['startdate'] + get_config('moodlecourse', 'courseduration');

        $weeksformat = course_get_format($course->id);
        $this->assertEquals($enddate, $weeksformat->get_default_course_enddate($courseform->get_quick_form()));
    }

    /**
     * Test the module content trim character.
     * @covers ::\format_designer\helper::modcontent_trim_char
     * @return void
     */
    public function test_modcontent_trim_char(): void {

        $str1 = "Lorem Ipsum is simply dummy text of the printing and typesetting industry.
        Lorem Ipsum has been the industry's standard dummy text ever since the 1500s,
        when an unknown printer took a galley of type and scrambled it to make a type specimen book.";
        $resstr1 = \format_designer\helper::modcontent_trim_char($str1, 30);
        $this->assertEquals(23, str_word_count($resstr1));

        $str2 = "Lorem Ipsum is simply dummy text of the printing and typesetting industry.";
        $resstr2 = \format_designer\helper::modcontent_trim_char($str2, 30);
        $this->assertEquals(str_word_count($str2), str_word_count($resstr2));
    }

    /**
     * Test desginer format date method.
     * @covers ::\format_designer\helper::format_date
     * @return void
     */
    public function test_format_date(): void {
        $timestamp = 1642861536;
        $dateformat = \format_designer\helper::format_date($timestamp);
        $this->assertEquals("Jan 22", $dateformat);
    }

    /**
     * Test the kanban board setup changes the section type.
     * @covers ::setup_kanban_board
     * @return void
     */
    public function test_kanban_setup(): void {
        global $DB;
        $this->resetAfterTest();
        $record = ['format' => 'designer', 'coursetype' => '0', 'numsections' => 3];
        $course = $this->getDataGenerator()->create_course($record);

        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);
        foreach ($coursesections as $section) {
            $format->set_section_option($section->id, 'sectiontype', 'list');
        }

        $data = ['id' => $course->id, 'coursetype' => DESIGNER_TYPE_KANBAN];
        $format->update_course_format_options($data);

        foreach ($coursesections as $section) {
            if ($section->section == 0) {
                continue;
            }
            $sectiontype = $format->get_section_option($section->id, 'sectiontype');
            $this->assertEquals('cards', $sectiontype);
        }
    }

    /**
     * Test the Staffs for the course header.
     * @covers ::format_designer_show_staffs_header
     * @return void
     */
    public function test_format_designer_show_staffs_header(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $record = ['format' => 'designer'];
        $course = $this->getDataGenerator()->create_course($record);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);
        $result = helper::create()->get_course_staff_users($course);
        $this->assertEquals(1, count($result));
        $this->assertEquals($user->id, $result[0]->userid);
    }

    /**
     * Test the Critera progress function.
     * @covers ::critera_progress
     * @return void
     */
    public function test_critera_progress(): void {
        global $DB;
        $this->resetAfterTest();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['format' => 'designer', 'enablecompletion' => 1]);
        $course1 = $this->getDataGenerator()->create_course(['format' => 'designer', 'enablecompletion' => 1]);
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course1->id], ['completion' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, $studentrole->id);

        // Set completion criteria and mark the user to complete the criteria.
        $criteriadata = (object) [
            'id' => $course1->id,
            'criteria_activity' => [$assign2->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        // Set completion criteria and mark the user to complete the criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_course' => [$course1->id],
        ];
        $criterion = new \completion_criteria_course();
        $criterion->update_config($criteriadata);

        // Set completion criteria and mark the user to complete the criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [$assign1->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);
        $result = \format_designer\output\renderer::criteria_progress($course, $user->id);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(0, $result['completed']);
        $this->assertEquals(0, $result['percent']);
        $cmassign = get_coursemodule_from_id('assign', $assign1->cmid);
        $completion = new \completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);

        $result = \format_designer\output\renderer::criteria_progress($course, $user->id);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals(1, $result['completed']);
        $this->assertEquals(50, $result['percent']);
    }

    /**
     * Custom activity titles are plain text and must be escaped before templates and navigation use them.
     *
     * @covers \format_designer::get_cm_secondary_title
     * @covers ::format_designer_extend_navigation_course
     */
    public function test_custom_activity_title_is_formatted(): void {
        global $PAGE, $COURSE;

        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $module = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Week & review',
        ]);
        $raw = "Tom's <script>alert(1)</script> \"quoted\"";
        \format_designer\options::insert_option($module->cmid, $course->id, 'secondarytype', 'custom');
        \format_designer\options::insert_option($module->cmid, $course->id, 'secondarycustomtitle', $raw);
        \format_designer\options::insert_option($module->cmid, $course->id, 'customtitleusecourseindex', 1);
        \format_designer\options::insert_option($module->cmid, $course->id, 'heroactivity', DESIGNER_HERO_ACTIVITY_EVERYWHERE);
        \format_designer\options::insert_option($module->cmid, $course->id, 'heroactivitypos', 1);
        \format_designer\options::$optionspercmid = [];
        \format_designer\options::$bulkloaded = [];

        $PAGE->set_course($course);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($module->cmid);
        $format = course_get_format($course);
        $title = $format->get_cm_secondary_title($cm);

        $expected = format_string($raw, true, ['context' => \context_module::instance($cm->id)]);
        $this->assertSame($expected, $title);
        $this->assertStringNotContainsString('<script>', $title);
        $this->assertStringNotContainsString("'", $title);
        $this->assertStringNotContainsString('"', $title);
        $this->assertNotSame($raw, $title);

        $index = $PAGE->get_renderer('core')->render_from_template('format_designer/courseformat/courseindex/cm', [
            'id' => $cm->id,
            'url' => (new \moodle_url('/mod/page/view.php', ['id' => $cm->id]))->out(false),
            'uservisible' => 1,
            'designercmname' => $title,
            'name' => 'fallback',
            'anchor' => 'module-' . $cm->id,
            'visible' => 1,
        ]);
        $this->assertStringContainsString($title, $index);
        $this->assertStringNotContainsString($raw, $index);

        $activity = $PAGE->get_renderer('core')->render_from_template('format_designer/courseformat/content/cm/title', [
            'url' => (new \moodle_url('/mod/page/view.php', ['id' => $cm->id]))->out(false),
            'instancename' => $cm->get_formatted_name(),
            'uservisible' => true,
            'designercmname' => $title,
            'altname' => '',
            'linkclasses' => '',
            'onclick' => '',
        ]);
        $this->assertStringContainsString($title, $activity);
        $this->assertStringNotContainsString($raw, $activity);

        // Hero navigation interpolates the link into an inline script. The title must not break that string.
        $COURSE = $course;
        $PAGE->set_cm($cm);
        $navigation = new \navigation_node(['text' => 'Course', 'key' => 'course']);
        format_designer_extend_navigation_course($navigation, $course, \context_course::instance($course->id));

        $reflection = new \ReflectionObject($PAGE->requires);
        $property = $reflection->getProperty('amdjscode');
        $property->setAccessible(true);
        $jscode = implode("\n", $property->getValue($PAGE->requires));
        $this->assertStringContainsString('designer-hero-activity', $jscode);
        $this->assertStringNotContainsString($raw, $jscode);
        $this->assertStringNotContainsString('<script>', $jscode);
        $this->assertDoesNotMatchRegularExpression("/\\.append\\('.*<script>/s", $jscode);

        // Activity-name mode must ignore the stored custom markup.
        \format_designer\options::insert_option($module->cmid, $course->id, 'secondarytype', 'activitytitle');
        \format_designer\options::$optionspercmid = [];
        \format_designer\options::$bulkloaded = [];
        $activityname = $format->get_cm_secondary_title($cm);
        $this->assertSame(
            format_string('Week & review', true, ['context' => \context_module::instance($cm->id)]),
            $activityname
        );
        $this->assertStringNotContainsString('<script>', $activityname);
    }

    /**
     * Selected textarea profile fields are cleaned before the staff header template renders them.
     *
     * @covers \format_designer\helper::get_course_staff_users
     */
    public function test_textarea_profile_field_is_formatted(): void {
        global $DB, $PAGE;

        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'textarea',
            'shortname' => 'aboutme',
            'name' => 'About me',
        ]);
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'nickname',
            'name' => 'Nickname',
        ]);
        $textarea = $DB->get_record('user_info_field', ['shortname' => 'aboutme'], '*', MUST_EXIST);
        $text = $DB->get_record('user_info_field', ['shortname' => 'nickname'], '*', MUST_EXIST);
        $markup = '<script>alert(1)</script><b>Hello</b><img src="x" onerror="alert(1)">' .
            '<a href="javascript:alert(1)">x</a>';
        $DB->insert_record('user_info_data', [
            'userid' => $user->id,
            'fieldid' => $textarea->id,
            'data' => $markup,
            'dataformat' => FORMAT_HTML,
        ]);
        $DB->insert_record('user_info_data', [
            'userid' => $user->id,
            'fieldid' => $text->id,
            'data' => '<img src=x onerror=alert(1)>',
            'dataformat' => FORMAT_MOODLE,
        ]);

        $course = course_get_format($course)->get_course();
        $course->coursestaff = '1';
        $course->profile_field_aboutme = 1;
        $course->profile_field_nickname = 1;

        $PAGE->set_url('/');
        $PAGE->set_course($course);

        $helper = new class extends helper {
            /** @var int[] User ids to return as course staff. */
            public array $forcestaffids = [];

            /**
             * Return the test user instead of resolving role assignments.
             *
             * @param object $course Course.
             * @return int[] Staff user ids.
             */
            protected function get_staffs_users($course) {
                if (empty($course->id)) {
                    return [];
                }
                return $this->forcestaffids;
            }
        };
        $helper->forcestaffids = [$user->id];
        $staffs = $helper->get_course_staff_users($course);

        $this->assertCount(1, $staffs);
        $values = array_column($staffs[0]->customfield, 'value');
        $this->assertCount(2, $values);

        $usercontext = \context_user::instance($user->id);
        $expectedtextarea = format_text($markup, FORMAT_HTML, [
            'context' => $usercontext,
            'overflowdiv' => true,
        ]);
        $this->assertContains($expectedtextarea, $values);
        $this->assertStringNotContainsString('<script', $expectedtextarea);
        $this->assertStringNotContainsString('onerror', $expectedtextarea);
        $this->assertStringNotContainsString('javascript:', $expectedtextarea);
        $this->assertStringContainsString('Hello', $expectedtextarea);

        $expectedtext = null;
        foreach (profile_get_user_fields_with_data($user->id) as $field) {
            if ($field->field->shortname === 'nickname') {
                $expectedtext = (string) $field->display_data();
            }
        }
        $this->assertNotNull($expectedtext);
        $this->assertContains($expectedtext, $values);
        $this->assertStringNotContainsString('<img', $expectedtext);

        $html = $PAGE->get_renderer('core')->render_from_template('format_designer/course_time_management', [
            'statuscoursestaffinfo' => true,
            'coursestaffinfo' => $staffs,
            'dataride' => 'data-ride',
        ]);
        $this->assertStringContainsString('profile-field', $html);
        $this->assertStringContainsString($expectedtextarea, $html);
        $this->assertStringContainsString($expectedtext, $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
