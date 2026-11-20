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


namespace format_designer;

/**
 * Tests for the Designer helper class.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\helper
 */
final class helper_test extends \advanced_testcase {
    /**
     * Pro only counts as installed when its installed version is at least the minimum version (DES-994).
     */
    public function test_has_pro_needs_minimum_version(): void {
        $this->resetAfterTest();
        if (!helper::pro_present()) {
            $this->assertFalse(helper::has_pro());
            $this->assertFalse(helper::pro_outdated());
            return;
        }

        set_config('version', helper::MIN_PRO_VERSION, 'local_designer');
        $this->assertTrue(helper::has_pro());
        $this->assertFalse(helper::pro_outdated());

        // Pro 1.6 R3.
        set_config('version', 2025041100, 'local_designer');
        $this->assertFalse(helper::has_pro());
        $this->assertTrue(helper::pro_outdated());

        // Pro code present, but not installed yet.
        unset_config('version', 'local_designer');
        $this->assertFalse(helper::has_pro());
        $this->assertTrue(helper::pro_outdated());
    }

    /**
     * Site admins get a notice in Designer courses and on admin pages while Pro is outdated (DES-994).
     */
    public function test_outdated_pro_notice(): void {
        $this->resetAfterTest();
        if (!helper::pro_present()) {
            $this->markTestSkipped('Designer Pro is not installed.');
        }
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);
        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $message = get_string('prooutdated', 'format_designer', helper::MIN_PRO_RELEASE);

        set_config('version', 2025041100, 'local_designer');
        $this->setAdminUser();
        $this->assertSame([$message], $this->notices_for($course, 'course'));
        $this->assertSame([$message], $this->notices_for(get_site(), 'admin'));
        $this->assertSame([], $this->notices_for($topics, 'course'));

        $this->setUser($student);
        $this->assertSame([], $this->notices_for($course, 'course'));

        set_config('version', helper::MIN_PRO_VERSION, 'local_designer');
        $this->setAdminUser();
        $this->assertSame([], $this->notices_for($course, 'course'));
    }

    /**
     * Run the before_http_headers callback for a page and return the queued notices.
     *
     * @param \stdClass $course Course of the page.
     * @param string $layout Page layout.
     * @return string[] Messages of the queued notices.
     */
    private function notices_for(\stdClass $course, string $layout): array {
        global $PAGE;
        \core\notification::fetch();
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_pagelayout($layout);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        hook_callbacks::before_http_headers(new \core\hook\output\before_http_headers($PAGE->get_renderer('core')));
        return array_map(fn($notice) => $notice->get_message(), \core\notification::fetch());
    }

    /**
     * The functions that Designer 1.7 moved into the helper still exist for Designer Pro 1.6 (DES-994).
     *
     * @covers ::format_designer_get_cache_object
     * @covers ::format_designer_has_pro
     * @covers ::format_designer_get_pro_layouts
     * @covers ::format_designer_fill_custom_values
     */
    public function test_pro_16_functions(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/designer/lib.php');

        $this->assertSame(helper::has_pro(), format_designer_has_pro());
        $this->assertSame(helper::get_pro_layouts(), format_designer_get_pro_layouts());
        $this->assertInstanceOf(\cache::class, format_designer_get_cache_object());

        $data = (object) ['bg' => 'custom', 'bgcustom' => '#123456', 'empty' => ''];
        $this->assertSame('background: #123456;', format_designer_fill_custom_values($data, 'bg', 'bgcustom', 'background'));
        $this->assertSame('', format_designer_fill_custom_values($data, 'empty', 'bgcustom', 'background'));
    }
}
