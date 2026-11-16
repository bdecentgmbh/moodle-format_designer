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

namespace format_designer\output;

/**
 * Unit tests for the Designer course renderer.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\output\renderer
 */
final class renderer_test extends \advanced_testcase {
    /** @var \stdClass Designer course. */
    protected \stdClass $course;

    /** @var \stdClass Page that shows its visits. */
    protected \stdClass $shown;

    /** @var \stdClass Page whose visits element is set to Remove. */
    protected \stdClass $removed;

    /**
     * Create a course with two pages and enable the standard log store.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['format' => 'designer']);
        $this->shown = $generator->create_module('page', [
            'course' => $this->course, 'designer_activityelements' => $this->activity_elements(1),
        ]);
        $this->removed = $generator->create_module('page', [
            'course' => $this->course, 'designer_activityelements' => $this->activity_elements(4),
        ]);

        // Other tests may have loaded the options of reused course module ids.
        \format_designer\options::$optionspercmid = [];
        \format_designer\options::$bulkloaded = [];
    }

    /**
     * A user sees their own views of the last 6 months, not those of other users.
     *
     * @covers ::render_course_module
     */
    public function test_visits_of_the_user(): void {
        global $USER;
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_user();

        $this->add_view($this->shown, $USER->id);
        $this->add_view($this->shown, $USER->id);
        $this->add_view($this->shown, $other->id);
        $this->add_view($this->shown, $USER->id, time() - YEARSECS);

        $this->assertEquals(get_string('modvisit', 'format_designer', 2), $this->render_visits($this->shown));
    }

    /**
     * An activity without views shows that it wasn't visited yet.
     *
     * @covers ::render_course_module
     */
    public function test_not_visited(): void {
        $this->setAdminUser();
        $this->assertEquals(get_string('notvisit', 'format_designer'), $this->render_visits($this->shown));
    }

    /**
     * An activity whose visits element is set to Remove shows no count.
     *
     * @covers ::render_course_module
     */
    public function test_visits_removed(): void {
        global $USER;
        $this->setAdminUser();
        $this->add_view($this->removed, $USER->id);

        $this->assertFalse($this->render_visits($this->removed));
    }

    /**
     * Guests share one user id, so they get no count, not the views of all guests.
     *
     * @covers ::render_course_module
     */
    public function test_visits_guest(): void {
        global $USER;
        $this->setGuestUser();
        $this->add_view($this->shown, $USER->id);

        $this->assertFalse($this->render_visits($this->shown));
    }

    /**
     * Without the standard log store there is nothing to count.
     *
     * @covers ::render_course_module
     */
    public function test_visits_log_store_disabled(): void {
        global $USER;
        $this->setAdminUser();
        $this->add_view($this->shown, $USER->id);
        set_config('enabled_stores', '', 'tool_log');

        $this->assertFalse($this->render_visits($this->shown));
    }

    /**
     * Activity elements setting of an activity.
     *
     * @param int $visits State of the visits element: 0 hide, 1 show, 2 show on hover, 3 hide on hover, 4 remove.
     * @return array
     */
    protected function activity_elements(int $visits): array {
        return [
            'icon' => 1, 'visits' => $visits, 'calltoaction' => 4, 'title' => 1,
            'description' => 1, 'modname' => 4, 'completionbadge' => 1,
        ];
    }

    /**
     * Log a view of an activity in the standard log store.
     *
     * @param \stdClass $module Module record from the generator.
     * @param int $userid
     * @param int|null $time
     */
    protected function add_view(\stdClass $module, int $userid, ?int $time = null): void {
        global $DB;
        $context = \context_module::instance($module->cmid);
        $DB->insert_record('logstore_standard_log', [
            'eventname' => '\mod_page\event\course_module_viewed',
            'component' => 'mod_page',
            'action' => 'viewed',
            'target' => 'course_module',
            'objecttable' => 'page',
            'objectid' => $module->id,
            'crud' => 'r',
            'edulevel' => \core\event\base::LEVEL_PARTICIPATING,
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE,
            'contextinstanceid' => $module->cmid,
            'userid' => $userid,
            'courseid' => $this->course->id,
            'anonymous' => 0,
            'timecreated' => $time ?? time(),
        ]);
    }

    /**
     * Render an activity for the current user and return its visit count.
     *
     * @param \stdClass $module Module record from the generator.
     * @return string|false The visit count text, false when no count is shown.
     */
    protected function render_visits(\stdClass $module) {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_course($this->course);
        $PAGE->set_url(course_get_url($this->course));
        $modinfo = get_fast_modinfo($this->course);
        $renderer = $PAGE->get_renderer('format_designer');
        $cm = $modinfo->get_cm($module->cmid);
        return $renderer->render_course_module($cm, 0, [], $modinfo->get_section_info($cm->sectionnum))['modvisits'];
    }
}
