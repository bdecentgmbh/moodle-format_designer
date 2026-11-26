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
 * Tests for the per-request preload of Video Time records used on the course page (DES-962).
 *
 * @package    format_designer
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_designer\helper
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_designer\helper::class)]
final class videotime_preload_test extends \advanced_testcase {
    /**
     * The cache is static for the request; tests share the process, so start each one clean.
     */
    public function setUp(): void {
        parent::setUp();
        helper::reset_videotime_records();
    }

    /**
     * Without Video Time installed (or without activities) the cache is empty and the accessors return null.
     */
    public function test_preload_without_videotime_activities(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'designer']);

        $records = helper::preload_videotime_records($course->id);
        $this->assertSame(['instances' => [], 'pro' => [], 'vimeo' => []], $records);
        $this->assertNull(helper::get_videotime_instance($course->id, 1));
        $this->assertNull(helper::get_videotime_pro_record($course->id, 1));
        $this->assertNull(helper::get_videotime_vimeo_video($course->id, 'https://vimeo.com/1'));
    }

    /**
     * All Video Time records of a course are loaded with a fixed number of queries, whatever the number of
     * activities, and the accessors serve them from the per-request cache.
     */
    public function test_preload_is_bounded_and_cached(): void {
        global $DB;

        if (!array_key_exists('videotime', \core_component::get_plugin_list('mod'))) {
            $this->markTestSkipped('mod_videotime is not installed.');
        }
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'designer']);
        $othercourse = $generator->create_course(['format' => 'designer']);

        $instances = [];
        for ($i = 1; $i <= 5; $i++) {
            $instances[] = $generator->create_module('videotime', [
                'course' => $course->id,
                'vimeo_url' => 'https://vimeo.com/10000000' . $i,
            ]);
        }
        // An activity in another course must not leak into this course's cache.
        $generator->create_module('videotime', ['course' => $othercourse->id, 'vimeo_url' => 'https://vimeo.com/999']);

        $dbman = $DB->get_manager();
        $hasvimeolibrary = $dbman->table_exists('videotime_vimeo_video');
        if ($hasvimeolibrary) {
            $DB->insert_record('videotime_vimeo_video', (object) [
                'uri' => '/videos/100000001',
                'link' => 'https://vimeo.com/100000001',
                'name' => 'First video',
                'duration' => 321,
                'state' => 1,
                'source' => 1,
            ]);
        }

        $readsbefore = $DB->perf_get_reads();
        $records = helper::preload_videotime_records($course->id);
        $preloadreads = $DB->perf_get_reads() - $readsbefore;
        // One query per table (videotime, videotimeplugin_pro, videotime_vimeo_video) at most, plus the
        // table existence checks on the first call.
        $this->assertLessThanOrEqual(6, $preloadreads);

        $this->assertCount(5, $records['instances']);
        $this->assertEqualsCanonicalizing(
            array_map(fn($instance) => (int) $instance->id, $instances),
            array_map('intval', array_keys($records['instances']))
        );

        // Serving every activity from the cache must not touch the database again.
        $readsbefore = $DB->perf_get_reads();
        foreach ($instances as $instance) {
            $record = helper::get_videotime_instance($course->id, $instance->id);
            $this->assertEquals($instance->vimeo_url, $record->vimeo_url);
            helper::get_videotime_pro_record($course->id, $instance->id);
            helper::get_videotime_vimeo_video($course->id, $instance->vimeo_url);
        }
        $this->assertSame(0, $DB->perf_get_reads() - $readsbefore);

        if ($hasvimeolibrary) {
            $video = helper::get_videotime_vimeo_video($course->id, 'https://vimeo.com/100000001');
            $this->assertNotNull($video);
            $this->assertEquals(321, $video->duration);
            $this->assertNull(helper::get_videotime_vimeo_video($course->id, 'https://vimeo.com/100000002'));
        }

        if ($dbman->table_exists('videotimeplugin_pro')) {
            // The pro record exists only if the subplugin created one; when it did, it must be served by id.
            $prorecord = $DB->get_record('videotimeplugin_pro', ['videotime' => $instances[0]->id]);
            if ($prorecord) {
                $this->assertEquals($prorecord->id, helper::get_videotime_pro_record($course->id, $instances[0]->id)->id);
            }
        }

        // Nothing from the other course.
        $otherinstanceid = $DB->get_field('videotime', 'id', ['course' => $othercourse->id]);
        $this->assertNull(helper::get_videotime_instance($course->id, $otherinstanceid));
    }
}
