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
require_once($CFG->dirroot . '/course/format/designer/lib.php');

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
        $this->assertCount(30, \format_designer\helper::get_words($resstr1));
        $this->assertStringEndsWith('the 1500s, when an unknown printer took...', $resstr1);

        $str2 = "Lorem Ipsum is simply dummy text of the printing and typesetting industry.";
        $resstr2 = \format_designer\helper::modcontent_trim_char($str2, 30);
        $this->assertEquals($str2, $resstr2);
    }

    /**
     * Words counted and kept by the activity description trim.
     *
     * @return array
     */
    public static function trim_words_provider(): array {
        $words = function (int $count, string $separator = ' '): string {
            $numbers = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
            $list = [];
            for ($i = 0; $i < $count; $i++) {
                $list[] = $numbers[$i % 10];
            }
            return implode($separator, $list);
        };
        return [
            '22 words' => [$words(22), 22, false],
            '23 words' => [$words(23), 23, false],
            '24 words' => [$words(24), 24, true],
            '60 words' => [$words(60), 60, true],
            '24 words with a non-breaking space entity' => [$words(12) . '&nbsp;' . $words(12), 24, true],
            '23 words with non-breaking space entities' => [$words(23, '&nbsp; '), 23, false],
            '24 words with non-breaking space characters' => [$words(24, "\u{00A0}"), 24, true],
            '24 words over three lines' => [$words(8) . "\n" . $words(8) . "\r\n\t" . $words(8), 24, true],
            'German words count once' => [str_repeat('Übung für Prüfung ', 7) . 'Größe Ärger', 23, false],
            'digits are words' => [trim(str_repeat('Step 1 then ', 8)), 24, true],
        ];
    }

    /**
     * The activity description trim counts and cuts the same words.
     *
     * @dataProvider trim_words_provider
     * @covers ::\format_designer\helper::get_words
     * @covers ::\format_designer\helper::modcontent_trim_char
     * @param string $text Description text, as returned by format_string().
     * @param int $count Expected number of words.
     * @param bool $trimmed Whether a trim length of 23 cuts the text.
     * @return void
     */
    public function test_trim_words(string $text, int $count, bool $trimmed): void {
        $this->assertCount($count, \format_designer\helper::get_words($text));

        $result = \format_designer\helper::modcontent_trim_char($text, 23);
        if ($trimmed) {
            $this->assertStringEndsWith('...', $result);
            $this->assertCount(23, \format_designer\helper::get_words(substr($result, 0, -3)));
        } else {
            $this->assertEquals($text, $result);
        }
    }

    /**
     * The course page trims a description by its real words, also when the editor stored &nbsp; or paragraphs.
     *
     * @covers \format_designer\output\renderer::render_course_module
     * @return void
     */
    public function test_render_trimmed_description(): void {
        global $PAGE;

        $this->setAdminUser();
        set_config('activitydesclength', DESIGNER_MOD_TEXT_TRIMM, 'format_designer');
        set_config('modtrimlength', 23, 'format_designer');
        $words = 'one two three four five six seven eight nine ten one two';
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $rest = 'three four five six seven eight nine ten one two';
        $short = $this->getDataGenerator()->create_module('page', [
            'course' => $course, 'showdescription' => 1, 'intro' => "<p>$words</p><p>$rest</p>",
        ]);
        $exact = $this->getDataGenerator()->create_module('page', [
            'course' => $course, 'showdescription' => 1, 'intro' => "<p>$words&nbsp;$rest three</p>",
        ]);
        $long = $this->getDataGenerator()->create_module('page', [
            'course' => $course, 'showdescription' => 1, 'intro' => "<p>$words&nbsp;$words</p>",
        ]);

        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url(course_get_url($course));
        $renderer = $PAGE->get_renderer('format_designer');
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(0);
        $render = fn($module) => $renderer->render_course_module($modinfo->get_cm($module->cmid), 0, [], $section)['modcontent'];

        // 22 words in two paragraphs: shown in full, with its paragraphs.
        $html = $render($short);
        $this->assertStringNotContainsString('trim-summary', $html);
        $this->assertStringContainsString("<p>$words</p><p>$rest</p>", $html);

        // 23 words with a non-breaking space: shown in full.
        $this->assertStringNotContainsString('trim-summary', $render($exact));

        // 24 words with a non-breaking space: cut after 23 words.
        $this->assertStringContainsString("<div class=\"trim-summary\">$words one two three four five six " .
            'seven eight nine ten one...', $render($long));

        // Rendering cached the format options and the completion info of these ids, which the next tests reuse.
        \format_designer\options::reset_caches();
        \format_designer\output\cm_completion::reset_caches();
    }

    /**
     * The course page shows a description with its paragraphs, line breaks and images, only the trimmed teaser is plain text.
     *
     * @covers \format_designer\output\renderer::render_course_module
     * @return void
     */
    public function test_render_description_formatting(): void {
        global $PAGE;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $image = '<img src="https://example.com/image.png" alt="Example image">';
        $formatted = $this->getDataGenerator()->create_module('page', [
            'course' => $course, 'showdescription' => 1,
            'intro' => "<p>First paragraph with a line<br>break.</p><ul><li>First item</li><li>Second item</li></ul>" .
                "<p>$image</p>",
        ]);
        $imageonly = $this->getDataGenerator()->create_module('page', [
            'course' => $course, 'showdescription' => 1, 'intro' => "<p>$image</p>",
        ]);

        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url(course_get_url($course));
        $renderer = $PAGE->get_renderer('format_designer');
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(0);
        $render = fn($module) => $renderer->render_course_module($modinfo->get_cm($module->cmid), 0, [], $section)['modcontent'];
        $assertformatted = function (string $html): void {
            $this->assertMatchesRegularExpression('~<p>First paragraph with a line<br\s*/?>break\.</p>~', $html);
            $this->assertStringContainsString('<ul><li>First item</li><li>Second item</li></ul>', $html);
            $this->assertMatchesRegularExpression('~<img [^>]*src="https://example\.com/image\.png"~', $html);
        };

        // Do not trim: the description as formatted for the course.
        set_config('activitydesclength', 1, 'format_designer');
        $html = $render($formatted);
        $this->assertStringNotContainsString('trim-summary', $html);
        $assertformatted($html);
        $this->assertStringContainsString('src="https://example.com/image.png"', $render($imageonly));

        // Trimmed: a plain text teaser, the description as formatted for the course behind "more".
        set_config('activitydesclength', DESIGNER_MOD_TEXT_TRIMM, 'format_designer');
        set_config('modtrimlength', 3, 'format_designer');
        $html = $render($formatted);
        $this->assertMatchesRegularExpression('~<div class="trim-summary">First paragraph with\.\.\.<a ~', $html);
        [, $fullcontent] = explode('<div class="fullcontent-summary summary-hide">', $html, 2);
        $assertformatted($fullcontent);

        // Trimmed, but without words to cut: the image is shown.
        $this->assertStringContainsString('src="https://example.com/image.png"', $render($imageonly));

        // Rendering cached the format options and the completion info of these ids, which the next tests reuse.
        \format_designer\options::reset_caches();
        \format_designer\output\cm_completion::reset_caches();
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
     * Course index visibility options with whether the section page shows its own navigation.
     *
     * @return array
     */
    public static function section_navigation_provider(): array {
        return [
            'Course index shown' => [0, false],
            'Course index hidden on course pages' => [\format_designer::HIDE_ON_COURSEPAGE, true],
            'Course index hidden everywhere' => [\format_designer::HIDE_EVERYWHERE, true],
        ];
    }

    /**
     * A section page shows the previous and next section links when the course index is hidden.
     *
     * @dataProvider section_navigation_provider
     * @covers \format_designer\output\courseformat\content::export_for_template
     * @param int $courseindex Course index visibility option.
     * @param bool $navigation Whether the section page shows the section navigation.
     * @return void
     */
    public function test_single_section_navigation(int $courseindex, bool $navigation): void {
        global $PAGE;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'designer', 'numsections' => 3, 'coursedisplay' => COURSE_DISPLAY_MULTIPAGE,
            'courseindex' => $courseindex,
        ]);
        $format = course_get_format($course);
        $format->set_sectionid(get_fast_modinfo($course)->get_section_info(2)->id);

        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url('/course/section.php', ['id' => $format->get_sectionid()]);
        $this->assertTrue($PAGE->theme->usescourseindex);

        $outputclass = $format->get_output_classname('content');
        $data = (new $outputclass($format))->export_for_template($PAGE->get_renderer('format_designer'));

        $this->assertTrue($data->hasnavigation);
        $this->assertEquals($navigation, !empty($data->sectionnavigation));
        $this->assertEquals($navigation, !empty($data->sectionselector));
        if ($navigation) {
            $this->assertTrue($data->sectionnavigation->hasprevious);
            $this->assertTrue($data->sectionnavigation->hasnext);
        }
    }

    /**
     * Course layouts and URL options with the page the section URL leads to.
     *
     * @return array
     */
    public static function view_url_provider(): array {
        return [
            'One section per page' => [COURSE_DISPLAY_MULTIPAGE, 2, [], 'section', 2],
            'One section per page, section 0' => [COURSE_DISPLAY_MULTIPAGE, 0, [], 'section', 0],
            'One section per page, navigation' => [COURSE_DISPLAY_MULTIPAGE, 2, ['navigation' => true], 'section', 2],
            'One section per page, return to the course page' => [COURSE_DISPLAY_MULTIPAGE, 2, ['sr' => 0], 'course', 2],
            'All sections on one page' => [COURSE_DISPLAY_SINGLEPAGE, 2, ['navigation' => true], 'course', 2],
            'All sections on one page, return to a section page' => [COURSE_DISPLAY_SINGLEPAGE, 1, ['sr' => 2], 'section', 2],
        ];
    }

    /**
     * A section with a page of its own links to the section page of core, the course index and the section
     * navigation link there too. Other sections link to their place on the course page.
     *
     * @dataProvider view_url_provider
     * @covers ::get_view_url
     * @param int $coursedisplay Course layout.
     * @param int $sectionnum Number of the section to link to.
     * @param array $options Options of the URL.
     * @param string $page Page the URL leads to, section or course.
     * @param int $pagesection Number of the section on that page.
     * @return void
     */
    public function test_get_view_url(int $coursedisplay, int $sectionnum, array $options, string $page, int $pagesection): void {
        $course = $this->getDataGenerator()->create_course([
            'format' => 'designer', 'numsections' => 3, 'coursedisplay' => $coursedisplay,
        ]);
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);

        if ($page == 'section') {
            $expected = new \moodle_url('/course/section.php', ['id' => $modinfo->get_section_info($pagesection)->id]);
        } else {
            $expected = new \moodle_url('/course/view.php', ['id' => $course->id], 'section-' . $pagesection);
        }
        $this->assertEquals($expected->out(false), $format->get_view_url($sectionnum, $options)->out(false));
        $section = $modinfo->get_section_info($sectionnum);
        $this->assertEquals($expected->out(false), $format->get_view_url($section, $options)->out(false));
        $this->assertEquals(
            (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            $format->get_view_url(null, $options)->out(false)
        );
    }

    /**
     * Course types with whether the section page leaves the section title to the page heading.
     *
     * @return array
     */
    public static function section_title_provider(): array {
        return [
            'Normal' => [DESIGNER_TYPE_NORMAL, true],
            'Collapsible' => [DESIGNER_TYPE_COLLAPSIBLE, true],
            'Flow, the title is on the section card' => [DESIGNER_TYPE_FLOW, false],
        ];
    }

    /**
     * The section page of core has the section name as its heading, the section doesn't repeat it.
     *
     * @dataProvider section_title_provider
     * @covers \format_designer\output\courseformat\content\section\header::export_for_template
     * @param int $coursetype Course type.
     * @param bool $inpageheading Whether the section page leaves the title to the page heading.
     * @return void
     */
    public function test_section_title_on_section_page(int $coursetype, bool $inpageheading): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course([
            'format' => 'designer', 'numsections' => 2, 'coursedisplay' => COURSE_DISPLAY_MULTIPAGE, 'coursetype' => $coursetype,
        ]);
        $format = course_get_format($course);
        $section = get_fast_modinfo($course)->get_section_info(2);
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $renderer = $PAGE->get_renderer('format_designer');
        $headerclass = $format->get_output_classname('content\\section\\header');

        $this->assertFalse((new $headerclass($format, $section))->export_for_template($renderer)->titleinpageheading);

        $format->set_sectionid($section->id);
        $data = (new $headerclass($format, $section))->export_for_template($renderer);
        $this->assertEquals($inpageheading, $data->titleinpageheading);
        $other = (new $headerclass($format, get_fast_modinfo($course)->get_section_info(1)))->export_for_template($renderer);
        $this->assertFalse($other->titleinpageheading);
    }

    /**
     * Custom activity titles are plain text and must pass through format_string() before templates and navigation use them.
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

        if (helper::has_pro()) {
            $this->markTestSkipped('Designer Pro builds the staff profile fields itself.');
        }

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

    /**
     * The menu HTML built from language strings is JSON-encoded before it goes into the inline navigation script.
     *
     * @covers ::format_designer_extend_navigation_course
     */
    public function test_secondary_menu_script_encodes_menu_html(): void {
        global $PAGE, $COURSE;

        $label = get_string('strsecondarymenucourse', 'format_designer');
        if (method_exists($this, 'get_mocked_string_manager')) {
            // Moodle 4.5.1 and later: use a label with an apostrophe, which broke the single-quoted script string.
            $label = "Course's home";
            $this->get_mocked_string_manager()->mock_string('strsecondarymenucourse', 'format_designer', $label);
        }

        $course = $this->getDataGenerator()->create_course(['format' => 'designer', 'secondarymenutocourse' => 1]);
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);

        $COURSE = $course;
        $PAGE->set_course($course);
        $PAGE->set_cm($cm);
        $navigation = new \navigation_node(['text' => 'Course', 'key' => 'course']);
        format_designer_extend_navigation_course($navigation, $course, \context_course::instance($course->id));

        $property = (new \ReflectionObject($PAGE->requires))->getProperty('amdjscode');
        $jscode = implode("\n", $property->getValue($PAGE->requires));

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $this->assertStringContainsString('designercoursehome', $jscode);
        $this->assertStringContainsString(trim(json_encode($label, $flags), '"'), $jscode);
        // Encoded markup keeps no tags, so nothing can end a string or the script early.
        $this->assertStringNotContainsString('<li', $jscode);
        $this->assertStringNotContainsString('<div', $jscode);
        $this->assertStringNotContainsString('<a ', $jscode);
        if (strpos($label, "'") !== false) {
            $this->assertStringNotContainsString($label, $jscode);
        }
    }

    /**
     * Video Time activity names go through format_string() like other activity names before the title template prints them.
     *
     * @covers \format_designer\output\courseformat\content\cm\title::get_title_displayvalue
     */
    public function test_videotime_title_is_formatted(): void {
        global $PAGE;

        if (!\core_component::get_component_directory('mod_videotime')) {
            $this->markTestSkipped('mod_videotime is not installed.');
        }

        $raw = 'Video <script>alert(1)</script> & notes';
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $module = $this->getDataGenerator()->create_module('videotime', [
            'course' => $course->id,
            'name' => $raw,
        ]);

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($module->cmid);
        $title = new \format_designer\output\courseformat\content\cm\title(
            $format,
            $modinfo->get_section_info($cm->sectionnum),
            $cm,
            [],
            false
        );
        $data = $title->export_for_template($PAGE->get_renderer('core'));

        $expected = format_string($raw, true, ['context' => \context_module::instance($cm->id)]);
        $this->assertStringContainsString($expected, $data['displayvalue']);
        $this->assertStringNotContainsString('<script>', $data['displayvalue']);
    }

    /**
     * Get the course header staff list as the current user, indexed by user id.
     *
     * @param \stdClass $course Course with the designer format options.
     * @return \stdClass[] Staff data by user id.
     */
    protected function get_staff_by_userid(\stdClass $course): array {
        $staffs = [];
        foreach (helper::create()->get_course_staff_users($course) as $staff) {
            $staffs[$staff->userid] = $staff;
        }
        return $staffs;
    }

    /**
     * The staff header shows an email address only to users who may see it, and never exports the user record.
     *
     * @covers \format_designer\helper::get_course_staff_users
     * @covers \format_designer\helper::get_visible_user_details
     */
    public function test_course_staff_email_follows_user_visibility(): void {
        global $PAGE;

        set_config('hiddenuserfields', '');
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $hidden = $this->getDataGenerator()->create_user(['maildisplay' => \core_user::MAILDISPLAY_HIDE]);
        $everyone = $this->getDataGenerator()->create_user(['maildisplay' => \core_user::MAILDISPLAY_EVERYONE]);
        $members = $this->getDataGenerator()->create_user(['maildisplay' => \core_user::MAILDISPLAY_COURSE_MEMBERS_ONLY]);
        $student = $this->getDataGenerator()->create_user();
        foreach ([$hidden, $everyone, $members] as $teacher) {
            $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        }
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $course = course_get_format($course)->get_course();
        $PAGE->reset_theme_and_output();
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $this->setUser($student);
        $staffs = $this->get_staff_by_userid($course);
        $this->assertCount(3, $staffs);
        $this->assertSame('', $staffs[$hidden->id]->email);
        $this->assertSame($everyone->email, $staffs[$everyone->id]->email);
        $this->assertSame($members->email, $staffs[$members->id]->email);
        foreach (['password', 'username', 'auth', 'lastip', 'phone1', 'address'] as $field) {
            $this->assertArrayNotHasKey($field, (array) $staffs[$hidden->id]);
        }

        // The template leaves out the envelope icon when there is no email address.
        $html = $PAGE->get_renderer('core')->render_from_template('format_designer/course_time_management', [
            'statuscoursestaffinfo' => true,
            'coursestaffinfo' => [$staffs[$hidden->id]],
            'dataride' => 'data-ride',
        ]);
        $this->assertStringNotContainsString('fa-envelope', $html);
        $this->assertStringNotContainsString($hidden->email, $html);

        // Users always see their own email address.
        $this->setUser($hidden);
        $this->assertSame($hidden->email, $this->get_staff_by_userid($course)[$hidden->id]->email);

        // Hidden user fields hide the email address even when the user shows it to everyone.
        set_config('hiddenuserfields', 'email');
        $this->setUser($student);
        $this->assertSame('', $this->get_staff_by_userid($course)[$everyone->id]->email);
    }

    /**
     * The staff header shows selected profile fields only when their visibility setting allows it.
     *
     * @covers \format_designer\helper::get_course_staff_users
     */
    public function test_course_staff_profile_fields_follow_field_visibility(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (helper::has_pro()) {
            $this->markTestSkipped('Designer Pro builds the staff profile fields itself.');
        }

        $visibility = [
            'everyonefield' => PROFILE_VISIBLE_ALL,
            'privatefield' => PROFILE_VISIBLE_PRIVATE,
            'nobodyfield' => PROFILE_VISIBLE_NONE,
        ];
        $userdata = [];
        foreach ($visibility as $shortname => $visible) {
            $this->getDataGenerator()->create_custom_profile_field([
                'datatype' => 'text',
                'shortname' => $shortname,
                'name' => $shortname,
                'visible' => $visible,
            ]);
            $userdata['profile_field_' . $shortname] = 'Value of ' . $shortname;
        }
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $teacher = $this->getDataGenerator()->create_user($userdata);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $course = course_get_format($course)->get_course();
        foreach (array_keys($visibility) as $shortname) {
            $course->{'profile_field_' . $shortname} = 1;
        }
        $PAGE->reset_theme_and_output();
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);

        $this->setUser($student);
        $values = array_column($this->get_staff_by_userid($course)[$teacher->id]->customfield, 'value');
        $this->assertCount(1, $values);
        $this->assertStringContainsString('Value of everyonefield', $values[0]);

        // The user also sees the private field. A field that is not visible stays hidden even from its owner.
        $this->setUser($teacher);
        $values = implode(' ', array_column($this->get_staff_by_userid($course)[$teacher->id]->customfield, 'value'));
        $this->assertStringContainsString('Value of everyonefield', $values);
        $this->assertStringContainsString('Value of privatefield', $values);
        $this->assertStringNotContainsString('Value of nobodyfield', $values);

        // Fields that are not selected for the course are left out.
        $course->profile_field_everyonefield = 0;
        $this->setUser($student);
        $this->assertSame([], $this->get_staff_by_userid($course)[$teacher->id]->customfield);
    }

    /**
     * Create a Designer course with a background image on section 1.
     *
     * @return array The course and the pluginfile.php arguments of the background.
     */
    protected function create_course_with_section_background(): array {
        $course = $this->getDataGenerator()->create_course(
            ['format' => 'designer', 'numsections' => 2, 'hiddensections' => 1],
            ['createsections' => true]
        );
        $section = get_fast_modinfo($course)->get_section_info(1);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'format_designer',
            'filearea' => 'sectiondesignbackground',
            'itemid' => $section->id,
            'filepath' => '/',
            'filename' => 'background.png',
        ], 'image');
        return [$course, [$section->id, 'background.png']];
    }

    /**
     * Enrolled users get the section background.
     *
     * @covers ::format_designer_get_section_background_file
     */
    public function test_section_background_file_enrolled_user(): void {
        [$course, $args] = $this->create_course_with_section_background();
        $context = \context_course::instance($course->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $file = format_designer_get_section_background_file($course, $context, 'sectiondesignbackground', $args);
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertEquals('background.png', $file->get_filename());

        // Only the section background areas in the course context are served.
        $this->assertNull(format_designer_get_section_background_file($course, $context, 'other', $args));
        $systemcontext = \context_system::instance();
        $this->assertNull(format_designer_get_section_background_file($course, $systemcontext, 'sectiondesignbackground', $args));
    }

    /**
     * Users who are not enrolled in the course do not get the section background.
     *
     * @covers ::format_designer_get_section_background_file
     */
    public function test_section_background_file_user_not_enrolled(): void {
        [$course, $args] = $this->create_course_with_section_background();
        $context = \context_course::instance($course->id);
        // Enrolled in another course only.
        $othercourse = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($othercourse, 'student'));

        // The course login redirects to the enrolment page, which PHPUnit reports as an exception.
        $this->expectException(\moodle_exception::class);
        format_designer_get_section_background_file($course, $context, 'sectiondesignbackground', $args);
    }

    /**
     * Students do not get the background of a hidden section, teachers do.
     *
     * @covers ::format_designer_get_section_background_file
     */
    public function test_section_background_file_hidden_section(): void {
        global $DB;
        [$course, $args] = $this->create_course_with_section_background();
        $context = \context_course::instance($course->id);
        $DB->set_field('course_sections', 'visible', 0, ['id' => $args[0]]);
        rebuild_course_cache($course->id, true);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($student);
        $this->assertNull(format_designer_get_section_background_file($course, $context, 'sectiondesignbackground', $args));

        $this->setUser($teacher);
        $file = format_designer_get_section_background_file($course, $context, 'sectiondesignbackground', $args);
        $this->assertInstanceOf(\stored_file::class, $file);
    }

    /**
     * Create a Designer course with a visible and a hidden activity with completion, a teacher and a student.
     *
     * @param array $options Course options.
     * @return array The course, the visible and the hidden activity, the teacher and the student.
     */
    protected function create_course_with_hidden_activity(array $options = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'designer', 'enablecompletion' => 1] + $options);
        $completion = ['completion' => COMPLETION_TRACKING_MANUAL];
        $visible = $generator->create_module('page', ['course' => $course->id, 'name' => 'Visible page'], $completion);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'name' => 'Hidden page'], $completion);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Page without completion']);
        set_coursemodule_visible($hidden->cmid, 0);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        return [$course, $visible, $hidden, $teacher, $student];
    }

    /**
     * The completion activities are the ones the user sees, whoever asked for them first.
     *
     * @covers \format_designer\output\renderer::get_completion_activities
     */
    public function test_get_completion_activities_per_user(): void {
        [$course, $visible, $hidden, $teacher, $student] = $this->create_course_with_hidden_activity();

        $this->setUser($teacher);
        $activities = \format_designer\output\renderer::get_completion_activities($course);
        $this->assertEqualsCanonicalizing([$visible->cmid, $hidden->cmid], array_keys($activities));

        $this->setUser($student);
        $activities = \format_designer\output\renderer::get_completion_activities($course);
        $this->assertEquals([$visible->cmid], array_keys($activities));
        $this->assertInstanceOf(\cm_info::class, $activities[$visible->cmid]);

        // The activities of another user follow the access of that user.
        $this->setUser($teacher);
        $activities = \format_designer\output\renderer::get_completion_activities($course, $student->id);
        $this->assertEquals([$visible->cmid], array_keys($activities));
    }

    /**
     * The progress of a student does not list activities hidden from the student, even right after a teacher.
     *
     * @covers \format_designer\output\renderer::criteria_progress
     */
    public function test_criteria_progress_skips_hidden_activities(): void {
        [$course, $visible, $hidden, $teacher, $student] = $this->create_course_with_hidden_activity();
        $criterion = new \completion_criteria_activity();
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [$visible->cmid => 1, $hidden->cmid => 1],
        ];
        $criterion->update_config($criteriadata);

        $this->setUser($teacher);
        $result = \format_designer\output\renderer::criteria_progress($course, $teacher->id);
        $this->assertStringContainsString('Hidden page', $result['uncompletioncriteriahtml']);

        $this->setUser($student);
        $result = \format_designer\output\renderer::criteria_progress($course, $student->id);
        $this->assertStringContainsString('Visible page', $result['uncompletioncriteriahtml']);
        $this->assertStringNotContainsString('Hidden page', $result['uncompletioncriteriahtml']);
        $this->assertStringNotContainsString('Hidden page', $result['completioncriteriahtml']);
    }

    /**
     * A criterion for an activity hidden from the student still counts, and is listed without its name (DES-998).
     *
     * @covers \format_designer\output\renderer::criteria_progress
     */
    public function test_criteria_progress_lists_unavailable_activities(): void {
        [$course, $visible, $hidden, , $student] = $this->create_course_with_hidden_activity();
        $criterion = new \completion_criteria_activity();
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [$visible->cmid => 1, $hidden->cmid => 1],
        ];
        $criterion->update_config($criteriadata);
        $unavailable = get_string('criteriaactivitynotavailable', 'format_designer');

        $this->setUser($student);
        $result = \format_designer\output\renderer::criteria_progress($course, $student->id);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals(0, $result['completed']);
        $this->assertStringContainsString('Visible page', $result['uncompletioncriteriahtml']);
        $this->assertStringContainsString($unavailable, $result['uncompletioncriteriahtml']);
        $this->assertStringNotContainsString('Hidden page', $result['uncompletioncriteriahtml']);

        // Completed before it was hidden: it counts as completed, as in course completion.
        $completion = new \completion_info($course);
        foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $activitycriterion) {
            if ($activitycriterion->moduleinstance == $hidden->cmid) {
                $completion->get_user_completion($student->id, $activitycriterion)->mark_complete();
            }
        }
        \format_designer\helper::get_cache_object()->purge();
        $result = \format_designer\output\renderer::criteria_progress($course, $student->id);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals(1, $result['completed']);
        $this->assertStringContainsString($unavailable, $result['completioncriteriahtml']);
        $this->assertStringNotContainsString($unavailable, $result['uncompletioncriteriahtml']);
    }

    /**
     * With Designer Pro's "all activities" progress, a student's progress only counts the activities the student sees.
     *
     * @covers \format_designer\output\renderer::criteria_progress
     */
    public function test_criteria_progress_all_activities_counts_visible_activities(): void {
        global $CFG;
        if (!\format_designer\helper::has_pro()) {
            $this->markTestSkipped('Designer Pro is not installed.');
        }
        require_once($CFG->dirroot . '/course/format/designer/lib.php');
        [$course, , , $teacher, $student] = $this->create_course_with_hidden_activity(
            ['calcourseprogress' => DESIGNER_PROGRESS_ALLACTIVITIES]
        );

        $this->setUser($teacher);
        $result = \format_designer\output\renderer::criteria_progress($course, $teacher->id);
        $this->assertEquals(2, $result['count']);

        $this->setUser($student);
        $result = \format_designer\output\renderer::criteria_progress($course, $student->id);
        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Visible page', $result['uncompletioncriteriahtml']);
        $this->assertStringNotContainsString('Hidden page', $result['uncompletioncriteriahtml']);
    }
}
