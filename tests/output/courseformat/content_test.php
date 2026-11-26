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

namespace format_designer\output\courseformat;

/**
 * Unit tests for the Designer course content output.
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\output\courseformat\content
 * @covers     \format_designer\output\courseformat\content::export_for_template
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_designer\output\courseformat\content::class)]
#[\PHPUnit\Framework\Attributes\CoversMethod(\format_designer\output\courseformat\content::class, 'export_for_template')]
final class content_test extends \advanced_testcase {
    /**
     * The content exports the values for the content JavaScript like core: strings instead of NULL.
     */
    public function test_export_for_template_section_return(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'designer', 'numsections' => 3]);
        $format = course_get_format($course);

        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $outputclass = $format->get_output_classname('content');
        $data = (new $outputclass($format))->export_for_template($PAGE->get_renderer('format_designer'));

        $this->assertSame('null', $data->sectionreturn);
        $this->assertSame('null', $data->pagesectionid);

        // On a section page the content passes the section id of the page.
        $sectionid = get_fast_modinfo($course)->get_section_info(2)->id;
        $format->set_sectionid($sectionid);
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url('/course/section.php', ['id' => $sectionid]);
        $data = (new $outputclass($format))->export_for_template($PAGE->get_renderer('format_designer'));

        $this->assertEquals($sectionid, $data->pagesectionid);
    }
}
